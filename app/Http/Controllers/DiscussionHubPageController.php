<?php

namespace App\Http\Controllers;
use App\Models\Notification;
use App\Models\GroupStudent;
use App\Models\Post;
use App\Models\Quiz;
use App\Models\QuizResult;
use App\Models\Recommendation;
use App\Models\Topic;
use App\Models\TopicClassification;
use App\Models\User;
use App\Models\Group;
use App\Models\Participation;
use App\Services\MlGatewayClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Dompdf\Dompdf;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use App\Models\Reply;

class DiscussionHubPageController extends Controller
{
    /**
     * Update participation score for a user based on their activity
     */
    private function updateParticipationScore(int $userId, ?int $parentPostId): void
    {
        $participation = Participation::where('UserID', $userId)->first();
        
        if (!$participation) {
            $participation = new Participation();
            $participation->UserID = $userId;
            $participation->PostCount = 0;
            $participation->ReplyCount = 0;
            $participation->ParticipationScore = 0;
        }
        
        if ($parentPostId) {
            // This is a reply
            $participation->ReplyCount++;
        } else {
            // This is a new post
            $participation->PostCount++;
        }
        
        // Calculate participation score: 2 points per post, 1 point per reply
        $participation->ParticipationScore = ($participation->PostCount * 2) + ($participation->ReplyCount * 1);
        $participation->save();
    }

    public function forum()
    {
        $user = Auth::user();
        if (!$user instanceof \App\Models\User) {
            $user = \App\Models\User::find(Auth::id());
        }
        $joinedGroups = $user->groups()->withCount(['students as member_count'])->get();

        $groupIds = $joinedGroups->pluck('GroupID');
        $memberIds = GroupStudent::whereIn('GroupID', $groupIds)->pluck('UserID')->unique();

        // Get topics for joined groups (including system-created topics)
        $topics = Topic::whereIn('GroupID', $groupIds)
            ->with('creator')
            ->latest('CreatedAt')
            ->get();

        return view('forum.index', compact('joinedGroups', 'topics'))->with('showSidebar', true);
    }

    public function messages(Request $request)
    {
        $user = Auth::user();
        if (!$user instanceof \App\Models\User) {
            $user = \App\Models\User::find(Auth::id());
        }
        $joinedGroups = $user->groups()->withCount(['students as member_count'])->get();
        
        // Auto-create topic if group is selected but no topic_id
        $topicId = $request->filled('topic_id') ? $request->topic_id : null;
        $groupId = $request->filled('group_id') ? $request->group_id : null;
        
        if ($groupId && !$topicId) {
            // Get the pre-assigned topic for this group (created by TopicSeeder)
            $topic = Topic::where('GroupID', $groupId)->orderBy('CreatedAt')->first();
            
            if (!$topic) {
                // Fallback: create a default topic if none exists
                $topic = Topic::create([
                    'Title' => 'General Discussion',
                    'GroupID' => $groupId,
                    'CreatedBy' => $user->UserID,
                ]);
            }
            $topicId = $topic->TopicID;
        } elseif (!$topicId && $joinedGroups->count() > 0) {
            // If no group selected, use the first group's pre-assigned topic
            $firstGroup = $joinedGroups->first();
            $topic = Topic::where('GroupID', $firstGroup->GroupID)->orderBy('CreatedAt')->first();
            
            if (!$topic) {
                $topic = Topic::create([
                    'Title' => 'General Discussion',
                    'GroupID' => $firstGroup->GroupID,
                    'CreatedBy' => $user->UserID,
                ]);
            }
            $topicId = $topic->TopicID;
            $groupId = $firstGroup->GroupID;
        }
        
        $groupIds = $joinedGroups->pluck('GroupID');
        $memberIds = GroupStudent::whereIn('GroupID', $groupIds)->pluck('UserID')->unique();

        $query = Post::with(['author', 'topic', 'parent.author', 'replies.author'])
            ->whereHas('topic', function ($topicQuery) use ($groupIds) {
                $topicQuery->whereIn('GroupID', $groupIds);
            })
            ->orderBy('CreatedAt');

        if ($topicId) {
            // Strictly scope to the selected topic (topic selection already implies group scope)
            $query->where('TopicID', $topicId);

            // If group is provided, also enforce that topic belongs to that group.
            if (!empty($groupId)) {
                $query->whereHas('topic', function ($t) use ($groupId) {
                    $t->where('GroupID', $groupId);
                });
            }
        } elseif ($groupId) {
            // If no topic selected but group is selected, filter by group's topics only
            $groupTopicIds = Topic::where('GroupID', $groupId)->pluck('TopicID');
            $query->whereIn('TopicID', $groupTopicIds);
        }

        $posts = $query->get();
        $threadedPosts = $posts->whereNull('ParentPostID')->values()->map(function ($post) use ($posts) {
            $post->setRelation('replies', $posts->where('ParentPostID', $post->PostID)->values());
            return $post;
        });

        // Get topics filtered by selected group, or all topics if no group selected
        $topics = Topic::when($groupId, function ($topicQuery) use ($groupId) {
                $topicQuery->where('GroupID', $groupId);
            }, function ($topicQuery) use ($groupIds) {
                $topicQuery->whereIn('GroupID', $groupIds);
            })
            ->latest('CreatedAt')
            ->get();
        
        $replyToPost = $request->filled('reply_to') ? Post::find($request->reply_to) : null;

       return view('messages.index', compact('joinedGroups', 'threadedPosts', 'topics', 'replyToPost'))->with([
            'showSidebar' => false,
            'topic_id' => $topicId,
            'group_id' => $groupId,
        ]);
    }

    public function storeMessage(Request $request)
    {
        \Log::info('StoreMessage called:', $request->all());
        
        // Auto-create or get topic_id if not provided - make topics completely transparent to users
        $topicId = $request->input('topic_id');
        $groupId = $request->input('group_id');
        
        // If no topic_id provided, get or create a default topic for the group
        if (!$topicId && $groupId) {
            $topic = Topic::where('GroupID', $groupId)
                ->orderBy('CreatedAt')
                ->first();
            
            if (!$topic) {
                // Create a default topic for the group
                $user = Auth::user();
                $topic = Topic::create([
                    'Title' => 'General Discussion',
                    'GroupID' => $groupId,
                    'CreatedBy' => $user->UserID,
                ]);
            }
            $topicId = $topic->TopicID;
        }
        
        // If still no topic_id, try to get any topic from user's groups
        if (!$topicId) {
            $user = Auth::user();
            $userGroups = $user->groups()->pluck('GroupID');
            $topic = Topic::whereIn('GroupID', $userGroups)->orderBy('CreatedAt')->first();
            
            if ($topic) {
                $topicId = $topic->TopicID;
            } else {
                // Create a default group and topic if user has no groups
                $defaultGroup = Group::first();
                if (!$defaultGroup) {
                    $defaultGroup = Group::create([
                        'GroupName' => 'General',
                        'Description' => 'General discussion group',
                        'CreatedBy' => $user->UserID,
                    ]);
                    GroupStudent::create([
                        'GroupID' => $defaultGroup->GroupID,
                        'UserID' => $user->UserID,
                        'Status' => 'active',
                    ]);
                }
                $topic = Topic::create([
                    'Title' => 'General Discussion',
                    'GroupID' => $defaultGroup->GroupID,
                    'CreatedBy' => $user->UserID,
                ]);
                $topicId = $topic->TopicID;
            }
        }
        
        // Merge the topic_id into request
        $request->merge(['topic_id' => $topicId]);
        
        // Validate request - topic_id will be auto-created if not provided
        $request->validate([
            'topic_id' => ['nullable', 'exists:Topic,TopicID'],
            'group_id' => ['nullable', 'exists:Group,GroupID'],
            'content' => ['nullable', 'string'],
            'attachment' => ['nullable', 'file', 'mimes:pdf,doc,docx,ppt,pptx,png,jpg,jpeg,zip', 'max:20480'],
            'parent_post_id' => ['nullable', 'exists:Post,PostID'],
        ]);


        // Enforce membership scoping at message creation time when group_id is provided.
        // Full topic<->group enforcement requires a GroupID column on Topic.
        $groupId = $request->input('group_id');
        if ($request->filled('group_id')) {
            $userId = Auth::id();
            $isMember = GroupStudent::where('GroupID', $groupId)
                ->where('UserID', $userId)
                ->exists();

            if (!$isMember) {
                abort(403, 'You are not a member of the selected group.');
            }

            // Enforce that the chosen topic belongs to the selected group.
            // If frontend sends a mismatched topic_id, we must switch it to a topic that belongs to this group.
            $topic = Topic::query()->where('TopicID', $request->topic_id)->first();
            if (!$topic || (string)($topic->GroupID ?? '') !== (string)$groupId) {
                $topic = Topic::where('GroupID', $groupId)->orderBy('CreatedAt')->first();
                if (!$topic) {
                    $topic = Topic::create([
                        'Title' => 'General Discussion',
                        'GroupID' => $groupId,
                        'CreatedBy' => Auth::user()->UserID,
                    ]);
                }
                $request->merge(['topic_id' => $topic->TopicID]);
            }

            // IMPORTANT: also update local $topicId variable used below for Post::create.
            $topicId = $request->input('topic_id');
        }

        if (blank($request->content) && !$request->hasFile('attachment')) {
            if ($request->wantsJson() || $request->header('Accept') === 'application/json') {
                return response()->json([
                    'success' => false,
                    'message' => 'Please enter a message or attach a file.',
                    'errors' => ['content' => ['Please enter a message or attach a file.']],
                ], 422);
            }

            return back()->withErrors(['content' => 'Please enter a message or attach a file.']);
        }


        $attachmentPath = null;
        $attachmentType = null;

        if ($request->hasFile('attachment')) {
            $file = $request->file('attachment');
            $extension = strtolower($file->getClientOriginalExtension());
            $attachmentPath = $file->store('discussions', 'public');
            $attachmentType = match ($extension) {
                'png', 'jpg', 'jpeg', 'gif', 'webp' => 'image',
                default => 'file',
            };
        }

        $post = Post::create([
            'TopicID' => $request->topic_id,
            'UserID' => Auth::id(),
            'Content' => $request->input('content', ''),
            'ParentPostID' => $request->input('parent_post_id'),
            'Attachment' => $attachmentPath,
            'AttachmentType' => $attachmentType,
        ]);
// Requirement #2: notify the original post's author when someone replies
if ($request->filled('parent_post_id')) {
    $parentPost = Post::find($request->input('parent_post_id'));

    if ($parentPost && $parentPost->UserID !== Auth::id()) {
        $replierName = Auth::user()->UserName ?? Auth::user()->name ?? 'Someone';
        $snippet = \Illuminate\Support\Str::limit($request->input('content', ''), 60);

        Notification::create([
            'UserID' => $parentPost->UserID,
            'Message' => "{$replierName} replied to your post: \"{$snippet}\"",
            'Status' => false, // unread
            'Type' => 'Reply',
        ]);
    }
}
        // Update participation points
        $this->updateParticipationScore(Auth::id(), $request->input('parent_post_id'));

        // For AJAX: return rendered bubble HTML (same structure as existing @foreach loop)
        if ($request->wantsJson() || $request->header('Accept') === 'application/json') {
            $post->load('author');

            // Determine mine wrapper (frontend logic uses AuthorID/isMine)
           //$isMine = (string) $post->AuthorID === (string) Auth::id();
           $isMine = (string) $post->UserID === (string) Auth::id();
           $senderName = $post->author?->UserName ?? $post->author?->name ?? 'Student';

            $loopParts = explode(' ', $senderName);
            $loopInitials = collect($loopParts)
                ->filter()
                ->map(fn($p) => mb_substr($p, 0, 1))
                ->take(2)
                ->implode('');

            // Basic escaping to keep JSON safe; markup matches bubble wrapper content
            $content = e($post->Content ?? '');

            $parentReplyText = $post->parent?->Content ?? null;
            $parentReplyTextHtml = !empty($parentReplyText)
                ? "<div style=\"background: rgba(0,0,0,0.05); border-left: 3px solid var(--primary-color); padding: 6px 10px; font-size: 0.8rem; border-radius: 4px; margin-bottom: 8px; color: var(--text-muted);\"><i class=\"fa-solid fa-quote-left\" style=\"font-size:0.65rem; margin-right:4px; opacity:0.5;\"></i> " . e($parentReplyText) . "</div>"
                : '';

            $attachmentHtml = !empty($post->Attachment)
                ? "<div style=\"margin-top: 8px; padding: 6px 10px; background: rgba(0,0,0,0.04); border-radius: 6px; display: flex; align-items: center; gap: 8px; font-size: 0.8rem;\">"
                    . "<i class=\"fa-solid fa-paperclip\" style=\"color: var(--text-muted);\"></i>"
                    . "<a href=\"" . route('messages.attachment', $post->PostID) . "\" target=\"_blank\" style=\"color: var(--primary-color); text-decoration: none; font-weight: 500;\">View Attached Document</a>"
                    . "</div>"
                : '';

            $wrapperClass = $isMine ? 'mine-wrapper' : 'theirs-wrapper';

            $html = "<div class=\"msg-bubble-wrapper {$wrapperClass}\" data-post-id=\"{$post->PostID}\" data-sender=\"" . e($senderName) . "\" data-role=\"Verified Contributor\" data-email=\"" . e($post->author?->email ?? 'unspecified@domain.edu') . "\">";

            if (!$isMine) {
                $html .= "<div class=\"avatar-circle-ui avatar-green view-sender-profile\" style=\"cursor: pointer;\">" . e($loopInitials ?: 'ST') . "</div>";
            }

            $snippet = trim((string)($post->Content ?? ''));
            $snippet = mb_substr($snippet, 0, 50);
            $escapedSnippetJs = addslashes($snippet);
            $html .= "<span class=\"reply-action-btn\" onclick=\"setReplyContext({$post->PostID}, '" . ($isMine ? 'You' : $senderName) . "', '{$escapedSnippetJs}')\"><i class=\"fa-solid fa-reply\"></i> Reply</span>";

            $radius = $isMine ? '12px 0px 12px 12px' : '0px 12px 12px 12px';
            $bg = $isMine ? '#d9fdd3' : '#ffffff';

            $html .= "<div style=\"padding: 12px 16px; border: none; box-shadow: 0 1px 2px rgba(0,0,0,0.1); border-radius: {$radius}; background-color: {$bg}; font-family: 'Inter', sans-serif; flex-grow: 1;\">";

            $html .= "<div style=\"display: flex; align-items: center; justify-content: space-between; gap: 24px; margin-bottom: 4px;\">";
            if (!$isMine) {
                $html .= "<span class=\"view-sender-profile\" style=\"font-weight: 600; font-size: 0.85rem; color: var(--primary-color); cursor: pointer;\">" . e($senderName) . "</span>";
            }
            $createdTs = $post->CreatedAt ? $post->CreatedAt->timestamp : time();
            $html .= "<span class=\"live-timestamp\" data-timestamp=\"{$createdTs}\" style=\"font-size: 0.7rem; color: var(--text-muted); margin-left: auto;\"></span>";
            $html .= "</div>";

            $html .= $parentReplyTextHtml;

            $html .= "<div class=\"message-actual-body\" style=\"color: #344054; line-height: 1.4; font-size: 0.92rem; word-break: break-word; white-space: pre-wrap;\">{$content}</div>";
            $html .= $attachmentHtml;
            $html .= "</div></div>";

            return response()->json([
                'success' => true,
                'html' => $html,
            ]);
        }

        $redirectParams = ['topic_id' => $request->topic_id];
        if ($request->filled('group_id')) {
            $redirectParams['group_id'] = $request->input('group_id');
        }
        if ($request->filled('search')) {
            $redirectParams['search'] = $request->input('search');
        }

        return redirect()->route('messages.index', $redirectParams)
            ->with('status', 'Message sent successfully.');

    }

    public function pollMessages(Request $request)
    {
        $request->validate([
            'topic_id' => ['required', 'exists:Topic,TopicID'],
            'group_id' => ['nullable', 'exists:Group,GroupID'],
            'newer_than' => ['required', 'integer', 'min:0'],
        ]);

        // Always derive the authoritative group from the topic itself.
        // This prevents cross-group leakage if the frontend sends stale/empty group_id.
        $topic = Topic::query()->where('TopicID', $request->topic_id)->first();
        if (!$topic) {
            abort(404, 'Topic not found.');
        }

        $derivedGroupId = $topic->GroupID ?? null;
        $groupId = $request->input('group_id');

        if (!empty($groupId) && (string)$derivedGroupId !== (string)$groupId) {
            // If client provided group_id doesn't match topic's group, trust topic and ignore client.
            $groupId = null;
        }

        // Enforce membership when we know which group the topic belongs to.
        if (!empty($derivedGroupId)) {
            $userId = Auth::id();
            $isMember = GroupStudent::where('GroupID', $derivedGroupId)
                ->where('UserID', $userId)
                ->exists();

            if (!$isMember) {
                abort(403, 'You are not a member of this group.');
            }
        }

        $effectiveGroupId = !empty($derivedGroupId) ? $derivedGroupId : $groupId;

        // newer_than is PostID
        $posts = Post::with(['author'])
            ->where('TopicID', $request->topic_id)
            ->when(!empty($effectiveGroupId), function ($q) use ($effectiveGroupId) {
                $q->whereHas('topic', function ($t) use ($effectiveGroupId) {
                    $t->where('GroupID', $effectiveGroupId);
                });
            })
            ->where('PostID', '>', $request->input('newer_than'))
            ->orderBy('CreatedAt')
            ->get();

        $html = '';
        $latestId = $request->input('newer_than');

        foreach ($posts as $post) {
            $post->load('author');
            $isMine = (string) $post->UserID === (string) Auth::id();
           // $isMine = (string)$post->AuthorID === (string)Auth::id();
            $senderName = $post->author?->UserName ?? $post->author?->name ?? 'Student';

            $loopParts = explode(' ', $senderName);
            $loopInitials = collect($loopParts)->filter()->map(fn($p) => mb_substr($p,0,1))->take(2)->implode('');

            $content = e($post->Content ?? '');

            $parentReplyText = $post->parent?->Content ?? null;
            $parentReplyTextHtml = !empty($parentReplyText)
                ? "<div style=\"background: rgba(0,0,0,0.05); border-left: 3px solid var(--primary-color); padding: 6px 10px; font-size: 0.8rem; border-radius: 4px; margin-bottom: 8px; color: var(--text-muted);\"><i class=\"fa-solid fa-quote-left\" style=\"font-size:0.65rem; margin-right:4px; opacity:0.5;\"></i> " . e($parentReplyText) . "</div>"
                : '';

            $attachmentHtml = !empty($post->Attachment)
                ? "<div style=\"margin-top: 8px; padding: 6px 10px; background: rgba(0,0,0,0.04); border-radius: 6px; display: flex; align-items: center; gap: 8px; font-size: 0.8rem;\"><i class=\"fa-solid fa-paperclip\" style=\"color: var(--text-muted);\"></i><a href=\"" . route('messages.attachment', $post->PostID) . "\" target=\"_blank\" style=\"color: var(--primary-color); text-decoration: none; font-weight: 500;\">View Attached Document</a></div>"
                : '';

            $wrapperClass = $isMine ? 'mine-wrapper' : 'theirs-wrapper';
            $snippet = mb_substr(trim((string)($post->Content ?? '')), 0, 50);
            $escapedSnippetJs = addslashes($snippet);

            $html .= "<div class=\"msg-bubble-wrapper {$wrapperClass}\" data-post-id=\"{$post->PostID}\" data-sender=\"" . e($senderName) . "\" data-role=\"Verified Contributor\" data-email=\"" . e($post->author?->email ?? 'unspecified@domain.edu') . "\">";
            if (!$isMine) {
                $html .= "<div class=\"avatar-circle-ui avatar-green view-sender-profile\" style=\"cursor: pointer;\">" . e($loopInitials ?: 'ST') . "</div>";
            }

           $html .= "<span class=\"reply-action-btn\" onclick=\"setReplyContext({$post->PostID}, '" . ($isMine ? 'You' : $senderName) . "', '{$escapedSnippetJs}')\"><i class=\"fa-solid fa-reply\"></i> Reply</span>";
            $radius = $isMine ? '12px 0px 12px 12px' : '0px 12px 12px 12px';
            $bg = $isMine ? '#d9fdd3' : '#ffffff';

            $createdTs = $post->CreatedAt ? $post->CreatedAt->timestamp : time();

            $html .= "<div style=\"padding: 12px 16px; border: none; box-shadow: 0 1px 2px rgba(0,0,0,0.1); border-radius: {$radius}; background-color: {$bg}; font-family: 'Inter', sans-serif; flex-grow: 1;\">";

            $html .= "<div style=\"display: flex; align-items: center; justify-content: space-between; gap: 24px; margin-bottom: 4px;\">";
            if (!$isMine) {
                $html .= "<span class=\"view-sender-profile\" style=\"font-weight: 600; font-size: 0.85rem; color: var(--primary-color); cursor: pointer;\">" . e($senderName) . "</span>";
            }
            $html .= "<span class=\"live-timestamp\" data-timestamp=\"{$createdTs}\" style=\"font-size: 0.7rem; color: var(--text-muted); margin-left: auto;\"></span>";
            $html .= "</div>";

            $html .= $parentReplyTextHtml;

            $html .= "<div class=\"message-actual-body\" style=\"color: #344054; line-height: 1.4; font-size: 0.92rem; word-break: break-word; white-space: pre-wrap;\">{$content}</div>";
            $html .= $attachmentHtml;

            $html .= "</div></div>";

            $latestId = max((int)$latestId, (int)$post->PostID);
        }

        return response()->json([
            'success' => true,
            'html' => $html,
            'latest_id' => (int)$latestId,
        ]);
    }

    public function exportTopic(Topic $topic)
    {

        $posts = Post::with(['author', 'parent.author', 'replies.author'])
            ->where('TopicID', $topic->TopicID)
            ->orderBy('CreatedAt')
            ->get();

        $html = view('messages.export_pdf', compact('topic', 'posts'))->render();
        $dompdf = new Dompdf();
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $filename = Str::slug($topic->Title ?: 'discussion') . '-discussion.pdf';
        $path = 'discussions/' . $filename;
        Storage::disk('public')->put($path, $dompdf->output());

        return response()->download(Storage::disk('public')->path($path), $filename, [
            'Content-Type' => 'application/pdf',
        ]);
    }

    public function exportGroup(Request $request, Group $group)
    {
        $userId = Auth::id();
        
        // Verify user is a member of this group
        $isMember = GroupStudent::where('GroupID', $group->GroupID)
            ->where('UserID', $userId)
            ->exists();

        if (!$isMember) {
            abort(403, 'You are not a member of this group.');
        }

        // Get all topics for this group
        $topics = Topic::where('GroupID', $group->GroupID)->get();
        $topicIds = $topics->pluck('TopicID');

        // Get all posts from all topics in this group
        $posts = Post::with(['author', 'parent.author', 'replies.author'])
            ->whereIn('TopicID', $topicIds)
            ->orderBy('CreatedAt')
            ->get();

        $html = view('messages.export_pdf', compact('group', 'posts'))->render();
        $dompdf = new Dompdf();
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $filename = Str::slug($group->GroupName ?: 'group') . '-discussion.pdf';
        $path = 'discussions/' . $filename;
        Storage::disk('public')->put($path, $dompdf->output());

        return response()->download(Storage::disk('public')->path($path), $filename, [
            'Content-Type' => 'application/pdf',
        ]);
    }

    public function deleteMessage(Post $post)
    {
        $userId = Auth::id();
        
        // Only allow the author to delete their own message
        if ($post->UserID !== $userId) {
            abort(403, 'You are not authorized to delete this message.');
        }

        // Delete attachment if exists
        if ($post->Attachment) {
            Storage::disk('public')->delete($post->Attachment);
        }

        // Delete the post
        $post->delete();

        if (request()->wantsJson()) {
            return response()->json(['success' => true, 'message' => 'Message deleted successfully.']);
        }

        return back()->with('status', 'Message deleted successfully.');
    }

    public function marks()
{
    if (Auth::user()->Role === 'Lecturer') {
        return redirect()->route('dashboard');
    }

    $user = Auth::user();
    
    // Get participation data
    $participation = Participation::where('UserID', $user->UserID)->first();
    $participationScore = $participation ? $participation->ParticipationScore : 0;
    $participationMarks = min(10, $participationScore); // Cap at 10 marks
    
    $marks = [
        'coursework' => 78,
        'cats' => 84,
        'exams' => 81,
        'gpa' => 4.2,
        'participation' => $participationMarks,
        'participation_details' => [
            'posts' => $participation->PostCount ?? 0,
            'replies' => $participation->ReplyCount ?? 0,
            'score' => $participationScore,
        ],
    ];

    return view('marks.index', compact('marks'));
}

    public function quizzes()
{
    $userId = Auth::id();
    $now = now();

    $allQuizzes = Quiz::orderByDesc('StartTime')->get();

    $completed = QuizResult::where('UserID', $userId)->with('quiz')->latest('SubmissionTime')->get();
    $completedQuizIds = $completed->pluck('QuizID')->unique();

    $available = collect();
    $upcoming = collect();
    $missed = collect();

    foreach ($allQuizzes as $quiz) {
        if ($completedQuizIds->contains($quiz->QuizID)) {
            continue;
        }

        $end = $quiz->StartTime->copy()->addMinutes($quiz->Duration);

        if ($quiz->StartTime > $now) {
            $upcoming->push($quiz);
        } elseif ($now <= $end) {
            $available->push($quiz);
        } else {
            $missed->push($quiz);
        }
    }

    $scores = QuizResult::where('UserID', $userId)->get();

    return view('quizzes.index', compact('available', 'upcoming', 'missed', 'completed', 'scores'));
}
    public function recommend()
    {
        if (Auth::user()->Role === 'Lecturer') {
            abort(403);
        }

        $user = Auth::user();
        if (!$user instanceof \App\Models\User) {
            $user = \App\Models\User::find(Auth::id());
        }

        $groupIds = $user->groups()->pluck('Group.GroupID');

        $forceRefresh = request()->boolean('refresh');
        if ($forceRefresh) {
            session()->forget('recommend_dismissed_at');
        }

        $hasSavedRecommendations = Recommendation::where('UserID', $user->UserID)->exists();
        $dismissed = session('recommend_dismissed_at') && !$forceRefresh;

        if ($forceRefresh || (!$hasSavedRecommendations && !$dismissed)) {
            $this->generateRecommendations($user, $groupIds);
        }

        $recommendedTopics = Recommendation::where('UserID', $user->UserID)
            ->orderByDesc('RelevanceScore')
            ->with(['topic.creator', 'topic.group'])
            ->get()
            ->filter(fn ($recommendation) => $recommendation->topic)
            ->map(function ($recommendation) {
                $topic = $recommendation->topic;
                $topic->RelevanceScore = $recommendation->RelevanceScore;
                return $topic;
            })
            ->values();

        $interests = Topic::whereIn('GroupID', $groupIds)
            ->whereNotNull('Category')
            ->distinct()
            ->pluck('Category')
            ->filter()
            ->values();

        $mlAvailable = $recommendedTopics->isNotEmpty()
            && Recommendation::where('UserID', $user->UserID)->where('RelevanceScore', '>', 0)->exists();

        return view('recommend.index', [
            'recommendedTopics' => $recommendedTopics,
            'interests' => $interests,
            'mlAvailable' => $mlAvailable,
        ])->with('showSidebar', true);
    }

    public function dismissRecommendations(): RedirectResponse
    {
        $user = Auth::user();
        Recommendation::where('UserID', $user->UserID)->delete();
        session(['recommend_dismissed_at' => now()]);

        return redirect()->route('recommend.index');
    }

    /**
     * Backfill topic classifications, ask the ML gateway to rank categories
     * for this user, then persist a fresh Recommendation set. Falls back to
     * the newest topics from joined groups when the gateway is unreachable.
     */
    private function generateRecommendations(User $user, $groupIds): void
    {
        $gateway = app(MlGatewayClient::class);

        $unclassifiedTopics = Topic::whereIn('GroupID', $groupIds)
            ->whereDoesntHave('classification')
            ->latest('CreatedAt')
            ->limit(15)
            ->get();

        foreach ($unclassifiedTopics as $topic) {
            $result = $gateway->classify($topic->Title, $topic->TopicID);
            if ($result && !empty($result['PredictedCategory'])) {
                TopicClassification::updateOrCreate(
                    ['TopicID' => $topic->TopicID],
                    [
                        'PredictedCategory' => $result['PredictedCategory'],
                        'ConfidenceScore' => $result['ConfidenceScore'] ?? 0,
                    ]
                );
            }
        }

        $interests = Topic::whereIn('GroupID', $groupIds)
            ->whereNotNull('Category')
            ->distinct()
            ->pluck('Category')
            ->filter()
            ->values()
            ->all();

        $recentMessages = Post::where('UserID', $user->UserID)
            ->latest('CreatedAt')
            ->limit(5)
            ->pluck('Content')
            ->filter()
            ->values()
            ->all();

        if (empty($recentMessages)) {
            $recentMessages = Topic::whereIn('GroupID', $groupIds)
                ->latest('CreatedAt')
                ->limit(5)
                ->pluck('Title')
                ->filter()
                ->values()
                ->all();
        }

        $ranked = $gateway->recommend($user->UserID, $interests, $recentMessages);
        $categoryScores = collect($ranked['Recommendations'] ?? [])
            ->pluck('RelevanceScore', 'Category');

        $candidateTopics = Topic::whereIn('GroupID', $groupIds)
            ->where('CreatedBy', '!=', $user->UserID)
            ->get();

        if ($categoryScores->isNotEmpty()) {
            $topics = $candidateTopics
                ->filter(fn ($topic) => $categoryScores->has($topic->Category))
                ->sortByDesc(fn ($topic) => $categoryScores->get($topic->Category))
                ->values();
        } else {
            $topics = $candidateTopics->sortByDesc('CreatedAt')->values();
        }

        $topics = $topics->take(8);

        Recommendation::where('UserID', $user->UserID)->delete();

        foreach ($topics as $topic) {
            Recommendation::create([
                'UserID' => $user->UserID,
                'TopicID' => $topic->TopicID,
                'RelevanceScore' => round($categoryScores->get($topic->Category, 0) * 100, 2),
            ]);
        }
    }

    public function settings()
    {
        return view('settings.index', [
            'user' => Auth::user(),
            'themeOptions' => [
                'luna' => 'Blue-Teal (Default)',
                'black' => 'Black',
                'brown' => 'Brown',
                'green' => 'Green',
            ],
        ])->with('showSidebar', true);
    }

    public function updateSettings(Request $request): RedirectResponse
    {
        $user = Auth::user();
        if (!$user instanceof \App\Models\User) {
            $user = \App\Models\User::find(Auth::id());
        }

        if ($request->filled('name')) {
            $user->name = $request->name;
        }

        if ($request->filled('username')) {
            $user->UserName = $request->username;
        }

        if ($request->filled('email')) {
            $user->email = $request->email;
        }

        if ($request->filled('current_password') && $request->filled('new_password')) {
            if (!Hash::check($request->current_password, $user->PasswordHash)) {
                return back()->withErrors(['current_password' => 'Current password is incorrect.']);
            }

            $user->PasswordHash = Hash::make($request->new_password);
        }

        if ($request->filled('theme_color') && in_array($request->theme_color, ['luna', 'black', 'brown', 'green'], true)) {
            $user->ThemeColor = $request->theme_color;
        }

        if ($user->Role === 'Lecturer' && $request->filled('default_quiz_duration')) {
            $user->DefaultQuizDurationMinutes = max(1, (int) $request->default_quiz_duration);
        }

        $user->save();

        return back()->with('status', 'Settings updated.');
    }

    public function logoutAllDevices(Request $request): RedirectResponse
    {
        $user = Auth::user();

        DB::table('sessions')->where('user_id', $user->UserID)->delete();

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('success', 'You have been logged out of all devices.');
    }
    public function groupTopics(Request $request, Group $group)
{
    $search = $request->input('search');
    $filter = $request->input('filter', 'all');

    $topics = Topic::where('GroupID', $group->GroupID)
        ->withCount('posts')
        ->with('creator')
        ->when($search, function ($q) use ($search) {
            $q->where('Title', 'like', "%{$search}%");
        })
        ->when($filter !== 'all', function ($q) use ($filter) {
            $q->where('Status', $filter);
        })
        ->orderByDesc('IsPinned')
        ->latest('CreatedAt')
        ->paginate(5)
        ->withQueryString();

    return view('forum.group', compact('group', 'topics', 'search', 'filter'))->with('showSidebar', true);


}

public function createTopic(Group $group)
{
    return view('topics.create', compact('group'))->with('showSidebar', false);
}

public function storeTopic(Request $request)
{
    $request->validate([
        'Title' => 'required|string|max:255',
        'GroupID' => 'required|exists:Group,GroupID',
        'Content' => 'required|string',
    ]);

    $group = Group::find($request->input('GroupID'));

    $topic = Topic::create([
        'Title' => $request->input('Title'),
        'GroupID' => $request->input('GroupID'),
        'CreatedBy' => Auth::id(),
        'Status' => 'open',
        'Category' => $group->GroupName,
    ]);

    Post::create([
        'TopicID' => $topic->TopicID,
        'UserID' => Auth::id(),
        'Content' => $request->input('Content'),
    ]);

    return redirect()->route('topics.show', $topic->TopicID);
}

public function showTopic(Topic $topic)
{
    $mainPost = Post::where('TopicID', $topic->TopicID)
        ->with(['author', 'replies.author'])
        ->oldest('CreatedAt')
        ->first();

    $participants = collect();
    if ($mainPost) {
        $participants->push($mainPost->author);
        foreach ($mainPost->replies as $reply) {
            $participants->push($reply->author);
        }
    }
    $participants = $participants->filter()->unique('UserID');

    $lastActivity = $mainPost?->replies->max('CreatedAt') ?? $mainPost?->CreatedAt;

    $recommended = Topic::where('GroupID', $topic->GroupID)
        ->where('TopicID', '!=', $topic->TopicID)
        ->withCount('posts')
        ->latest('CreatedAt')
        ->take(3)
        ->get();

    return view('topics.show', compact('topic', 'mainPost'))->with('showSidebar', false);

}

public function storeReply(Request $request, Post $post)
{
    $request->validate(['ReplyContent' => 'required|string']);

    Reply::create([
        'PostID' => $post->PostID,
        'UserID' => Auth::id(),
        'ReplyContent' => $request->input('ReplyContent'),
    ]);

    // Auto-mark topic as answered if a lecturer replies
    if (Auth::user()->Role === 'Lecturer') {
        $post->topic()->update(['Status' => 'answered']);
    }

    return redirect()->route('topics.show', $post->TopicID);
}

public function acceptAnswer(Reply $reply)
{
    // Only a lecturer can accept an answer
    if (Auth::user()->Role !== 'Lecturer') {
        abort(403, 'Only a lecturer can mark an answer as accepted.');
    }

    $post = $reply->post;

    Reply::where('PostID', $post->PostID)->update(['IsAccepted' => false]);
    $reply->update(['IsAccepted' => true]);
    $post->topic()->update(['Status' => 'answered']);

    return redirect()->route('topics.show', $post->TopicID);
}
    

public function pollNotifications(Request $request)
{
    $userId = Auth::id();

    $unreadCount = Notification::where('UserID', $userId)
        ->where('Status', false)
        ->count();

    $latest = Notification::where('UserID', $userId)
        ->orderByDesc('CreatedAt')
        ->take(10)
        ->get()
        ->map(function ($n) {
            return [
                'id' => $n->NotificationID,
                'message' => $n->Message,
                'type' => $n->Type,
                'status' => $n->Status,
                'time' => $n->CreatedAt->diffForHumans(),
            ];
        });

    return response()->json([
        'success' => true,
        'unread_count' => $unreadCount,
        'notifications' => $latest,
    ]);
}

public function markNotificationRead(Notification $notification)
{
    if ($notification->UserID !== Auth::id()) {
        abort(403);
    }

    $notification->update(['Status' => true]);

    return response()->json(['success' => true]);
}

public function markAllNotificationsRead()
{
    Notification::where('UserID', Auth::id())
        ->where('Status', false)
        ->update(['Status' => true]);

    return response()->json(['success' => true]);
    } }