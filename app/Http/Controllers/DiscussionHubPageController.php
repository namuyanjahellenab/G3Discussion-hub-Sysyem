<?php

namespace App\Http\Controllers;
use App\Models\Notification;
use App\Models\ReadState;
use App\Models\GroupStudent;
use App\Models\Post;
use App\Models\Quiz;
use App\Models\QuizResult;
use App\Models\Recommendation;
use App\Jobs\ClassifyTopicJob;
use App\Models\Topic;
use App\Models\TopicClassification;
use App\Models\TopicExclusion;
use App\Models\User;
use App\Models\Group;
use App\Services\AttachmentUploader;
use App\Services\MlGatewayClient;
use App\Services\ParticipationService;
use App\Services\RecommendationScorer;
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
     * Recompute a user's participation score for a group. Criteria-driven
     * (ParticipationCriteria, per-group or platform default) and recalculated
     * from source on every call rather than incrementally patched — see
     * ParticipationService for why.
     */
    private function updateParticipationScore(int $userId, ?int $groupId): void
    {
        if (!$groupId) {
            return;
        }

        app(ParticipationService::class)->recalculate($userId, $groupId);
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

        // Ask the ML gateway to categorize any newly created topics that
        // don't have a prediction yet, so "Latest Topics" can show a category.
        $this->backfillClassifications($groupIds);

        $totalTopicsCount = Topic::whereIn('GroupID', $groupIds)->visibleTo($user->UserID)->count();

        // Latest Topics feed: newest 5 only, including system-created topics.
        // visibleTo() excludes topics this user was excluded from at the
        // query level — they must not appear here even as a teaser row.
        $topics = Topic::whereIn('GroupID', $groupIds)
            ->visibleTo($user->UserID)
            ->with(['creator', 'classification'])
            ->latest('CreatedAt')
            ->limit(5)
            ->get();

        return view('forum.index', compact('joinedGroups', 'topics', 'totalTopicsCount'))->with('showSidebar', true);
    }

    /**
     * Ask the ML gateway to classify topics in these groups that don't have
     * a predicted category yet, and persist the result. Shared by the Latest
     * Topics feed and the recommendation generator so both stay in sync.
     */
    /**
     * Dispatches one ClassifyTopicJob per unclassified topic instead of
     * calling the ML gateway synchronously in a loop on every page load.
     * Topics without a category yet just render without one until the queue
     * worker catches up — classification is enrichment, not blocking.
     */
    private function backfillClassifications($groupIds, int $limit = 15): void
    {
        $unclassifiedTopicIds = Topic::whereIn('GroupID', $groupIds)
            ->whereDoesntHave('classification')
            ->latest('CreatedAt')
            ->limit($limit)
            ->pluck('TopicID');

        foreach ($unclassifiedTopicIds as $topicId) {
            ClassifyTopicJob::dispatch($topicId);
        }
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

        $moderation = app(MlGatewayClient::class)->moderateContent($request->input('content', ''));

        if (!$moderation['isEducational']) {
            $message = 'This group is for course discussion — please keep posts relevant to your studies.';

            if ($request->wantsJson() || $request->header('Accept') === 'application/json') {
                return response()->json([
                    'success' => false,
                    'message' => $message,
                    'errors' => ['content' => [$message]],
                ], 422);
            }

            return back()->withErrors(['content' => $message]);
        }

        $isSpam = $moderation['isSpam'];

        $attachmentPath = null;
        $attachmentType = null;

        if ($request->hasFile('attachment')) {
            $stored = AttachmentUploader::store($request->file('attachment'));
            $attachmentPath = $stored['path'];
            $attachmentType = $stored['type'];
        }

        $post = Post::create([
            'TopicID' => $request->topic_id,
            'UserID' => Auth::id(),
            'Content' => $request->input('content', ''),
            'ParentPostID' => $request->input('parent_post_id'),
            'Attachment' => $attachmentPath,
            'AttachmentType' => $attachmentType,
            'IsFlagged' => $isSpam,
            'FlaggedReason' => $isSpam ? 'Auto-flagged by spam detection' : null,
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
        // Update participation points (derive the group from the topic itself,
        // same pattern pollMessages() uses, rather than trusting client input)
        $this->updateParticipationScore(Auth::id(), $post->topic?->GroupID);

        // For AJAX: return rendered bubble HTML (same structure as existing @foreach loop)
        if ($request->wantsJson() || $request->header('Accept') === 'application/json') {
            $post->load('author');

            // Determine mine wrapper (frontend logic uses AuthorID/isMine)
           $isMine = (string) $post->UserID === (string) Auth::id();
           $senderName = $post->author?->UserName ?? $post->author?->name ?? 'Student';

            $loopInitials = Str::initials($senderName);

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
            $senderName = $post->author?->UserName ?? $post->author?->name ?? 'Student';

            $loopInitials = Str::initials($senderName);

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
        // Generated synchronously — the Python gateway is tried first
        // (richer rendering), falling back to local Dompdf if it's
        // unreachable, so the download always succeeds either way.
        $pdfBytes = app(MlGatewayClient::class)->exportTopicPdf($topic->TopicID);

        if ($pdfBytes === null) {
            $posts = Post::with(['author', 'parent.author', 'replies.author'])
                ->where('TopicID', $topic->TopicID)
                ->orderBy('CreatedAt')
                ->get();

            $html = view('messages.export_pdf', compact('topic', 'posts'))->render();
            $dompdf = new Dompdf();
            $dompdf->loadHtml($html);
            $dompdf->setPaper('A4', 'portrait');
            $dompdf->render();
            $pdfBytes = $dompdf->output();
        }

        $filename = Str::slug($topic->Title ?: 'discussion') . '-discussion.pdf';

        return response($pdfBytes, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
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

    public function quizzes()
{
    $userId = Auth::id();
    $now = now();

    $allQuizzes = Quiz::where('Status', 'scheduled')->orderByDesc('StartTime')->get();

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

        $this->backfillClassifications($groupIds);

        $interests = Topic::whereIn('GroupID', $groupIds)
            ->whereNotNull('Category')
            ->distinct()
            ->pluck('Category')
            ->filter()
            ->values()
            ->all();

        $recentMessages = app(RecommendationScorer::class)->buildRecentMessages($user->UserID, $groupIds);

        $candidateTopics = Topic::whereIn('GroupID', $groupIds)
            ->where('CreatedBy', '!=', $user->UserID)
            ->visibleTo($user->UserID)
            ->withCount('posts')
            ->get();

        $topicPayload = $candidateTopics->map(fn ($topic) => [
            'TopicID' => $topic->TopicID,
            'Title' => $topic->Title,
            'Category' => $topic->Category,
        ])->all();

        $ranked = $topicPayload !== []
            ? $gateway->recommendTopics($user->UserID, $interests, $recentMessages, $topicPayload)
            : null;
        $topicScores = collect($ranked['TopicScores'] ?? [])->pluck('RelevanceScore', 'TopicID');

        // Blend text relevance with real engagement (replies + distinct
        // repliers) so a topic that's actually "vibing" with discussion
        // outranks one that only happens to share vocabulary with the
        // user's interests. Degrades gracefully if the ML gateway is down:
        // every text score defaults to 0, so topics still rank by
        // engagement alone rather than falling back to plain recency.
        $topics = app(RecommendationScorer::class)
            ->score($candidateTopics, $topicScores)
            ->take(10); // max topics recommended to a user

        Recommendation::where('UserID', $user->UserID)->delete();

        foreach ($topics as $topic) {
            Recommendation::create([
                'UserID' => $user->UserID,
                'TopicID' => $topic->TopicID,
                // Clamp defensively: the blended score is meant to be 0-1,
                // but never trust the math to persist a percentage the UI
                // treats as a hard 0-100 range.
                'RelevanceScore' => min(round($topic->relevance_score * 100, 2), 100),
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
    $userId = Auth::id();

    // visibleTo() re-runs "which TopicIDs is this user excluded from" as a
    // fresh query every time it's chained onto a new builder - baseTopics
    // (for the filter-count tiles) and the main $topics query below each
    // used to trigger their own identical lookup. Computing the excluded
    // id list once and reusing it via whereNotIn() halves that.
    $excludedTopicIds = TopicExclusion::where('UserID', $userId)->pluck('TopicID');

    $baseTopics = Topic::where('GroupID', $group->GroupID)->whereNotIn('TopicID', $excludedTopicIds);

    $filterCounts = [
        'all' => (clone $baseTopics)->count(),
        'open' => (clone $baseTopics)->where('Status', 'open')->count(),
        'flagged' => (clone $baseTopics)->whereHas('replies', fn ($q) => $q->where('Reply.IsFlagged', true))->count(),
        'restricted' => (clone $baseTopics)->whereHas('exclusions')->count(),
    ];

    $topics = Topic::where('GroupID', $group->GroupID)
        ->whereNotIn('TopicID', $excludedTopicIds)
        // 'replies' is only ever read for ->count()/->max('CreatedAt') (see
        // forum/group.blade.php) plus the unread-badge computation below -
        // none of that needs Content/Attachment/IsFlagged etc, so the eager
        // load is trimmed to the 3 columns actually used instead of
        // hydrating every reply's full row (topics with dozens of replies
        // were pulling their entire text/attachment data just to count them).
        ->with(['creator', 'exclusions', 'replies' => fn ($q) => $q->select('Reply.ReplyID', 'Reply.PostID', 'Reply.CreatedAt')])
        ->withCount(['replies as flagged_replies_count' => fn ($q) => $q->where('Reply.IsFlagged', true)])
        ->when($search, function ($q) use ($search) {
            $q->where('Title', 'like', "%{$search}%");
        })
        ->when($filter === 'flagged', function ($q) {
            $q->whereHas('replies', fn ($rq) => $rq->where('Reply.IsFlagged', true));
        })
        ->when($filter === 'restricted', function ($q) {
            $q->whereHas('exclusions');
        })
        ->when(!in_array($filter, ['all', 'flagged', 'restricted'], true), function ($q) use ($filter) {
            $q->where('Status', $filter);
        })
        ->orderByDesc('IsPinned')
        ->latest('CreatedAt')
        ->paginate(5)
        ->withQueryString();

    // Per-topic unread reply count (see ReadState) - computed in-memory
    // from the already-eager-loaded 'replies' relation rather than one
    // extra query per topic. A never-visited topic (no ReadState row)
    // counts every existing reply as unread, same as the desktop client.
    $lastReadByTopic = ReadState::where('UserID', $userId)
        ->where('EntityType', 'Topic')
        ->whereIn('EntityID', $topics->pluck('TopicID'))
        ->pluck('LastReadItemId', 'EntityID');

    foreach ($topics as $topic) {
        $lastRead = $lastReadByTopic[$topic->TopicID] ?? 0;
        $topic->unreadRepliesCount = $topic->replies->where('ReplyID', '>', $lastRead)->count();
    }

    return view('forum.group', compact('group', 'topics', 'search', 'filter', 'filterCounts'))->with('showSidebar', true);


}

public function createTopic(Group $group)
{
    // Cached at the Group level (busted on join/leave) since the same
    // membership list is reused by every member who opens this form or the
    // announcement composer — filtering out the current user happens here,
    // after the cache read, so the cached list itself stays shared.
    $groupMembers = $group->activeMembers()->where('UserID', '!=', Auth::id())->values();

    return view('topics.create', compact('group', 'groupMembers'))->with('showSidebar', false);
}

public function storeTopic(Request $request)
{
    $request->validate([
        'Title' => 'required|string|max:255',
        'GroupID' => 'required|exists:Group,GroupID',
        'Content' => 'required|string',
        'attachment' => ['nullable', 'file', 'mimes:pdf,doc,docx,ppt,pptx,png,jpg,jpeg,zip', 'max:20480'],
        'audience' => 'nullable|in:everyone,custom',
        'exclude' => 'array',
        'exclude.*' => 'exists:User,UserID',
    ]);

    $moderation = app(MlGatewayClient::class)->moderateContent($request->input('Content'));

    if (!$moderation['isEducational']) {
        return back()
            ->withErrors(['Content' => 'This group is for course discussion — please keep posts relevant to your studies.'])
            ->withInput();
    }

    $isSpam = $moderation['isSpam'];

    $group = Group::find($request->input('GroupID'));

    $topic = Topic::create([
        'Title' => $request->input('Title'),
        'GroupID' => $request->input('GroupID'),
        'CreatedBy' => Auth::id(),
        'Status' => 'open',
        'Category' => $group->GroupName,
    ]);

    $attachmentPath = null;
    $attachmentType = null;

    if ($request->hasFile('attachment')) {
        $stored = AttachmentUploader::store($request->file('attachment'));
        $attachmentPath = $stored['path'];
        $attachmentType = $stored['type'];
    }

    Post::create([
        'TopicID' => $topic->TopicID,
        'UserID' => Auth::id(),
        'Content' => $request->input('Content'),
        'Attachment' => $attachmentPath,
        'AttachmentType' => $attachmentType,
        'IsFlagged' => $isSpam,
        'FlaggedReason' => $isSpam ? 'Auto-flagged by spam detection' : null,
    ]);

    if ($request->input('audience') === 'custom') {
        foreach (collect($request->input('exclude', []))->unique() as $excludedUserId) {
            TopicExclusion::create(['TopicID' => $topic->TopicID, 'UserID' => $excludedUserId]);
        }
    }

    return redirect()->route('topics.show', $topic->TopicID);
}

public function showTopic(Topic $topic)
{
    abort_if($topic->exclusions()->where('UserID', Auth::id())->exists(), 403);

    $mainPost = Post::where('TopicID', $topic->TopicID)
        ->with(['author', 'replies.author', 'replies.flags', 'replies.parentReply.author'])
        ->oldest('CreatedAt')
        ->first();

    // Frozen BEFORE marking read below, so the NEW MESSAGES divider (see
    // topics/show.blade.php) reflects "unread since your last visit", not
    // "unread since this request" - same idea as the desktop client's
    // unreadThresholdReplyId field.
    $lastReadReplyId = ReadState::lastRead(Auth::id(), 'Topic', $topic->TopicID);
    $maxReplyId = $mainPost?->replies->max('ReplyID') ?? 0;
    if ($maxReplyId > 0) {
        ReadState::markRead(Auth::id(), 'Topic', $topic->TopicID, $maxReplyId);
    }

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
        ->visibleTo(Auth::id())
        ->withCount('posts')
        ->latest('CreatedAt')
        ->take(3)
        ->get();

    // Share links are normally Python-generated (Flask builds the URL/text/
    // per-platform links), but that's a "richer text" nicety, not a hard
    // dependency — WhatsApp/X/Facebook/Email links only need the topic URL
    // and title, both available here. If the gateway is unreachable (already
    // logged inside MlGatewayClient), fall back to a plain PHP-built set
    // instead of the Share button disappearing outright.
    $shareLinks = app(MlGatewayClient::class)->topicShareLinks($topic->TopicID, url('/'))
        ?? $this->buildFallbackShareLinks($topic);

    return view('topics.show', compact('topic', 'mainPost', 'participants', 'lastActivity', 'recommended', 'shareLinks', 'lastReadReplyId'))->with('showSidebar', true);

}

/**
 * PHP-only share-link builder used when the ML gateway is unreachable.
 * Mirrors the shape/URLs Flask's share.py builds, just without the ability
 * to enrich the text (e.g. reply count) that only the Python side computes.
 */
private function buildFallbackShareLinks(Topic $topic): array
{
    $shareUrl = route('topics.show', $topic);
    $shareText = 'Check out "' . $topic->Title . '" on Discussion Hub';

    return [
        'TopicID' => $topic->TopicID,
        'ShareUrl' => $shareUrl,
        'ShareText' => $shareText,
        'Links' => [
            'whatsapp' => 'https://wa.me/?' . http_build_query(['text' => $shareText . ' ' . $shareUrl]),
            'twitter' => 'https://twitter.com/intent/tweet?' . http_build_query(['text' => $shareText, 'url' => $shareUrl]),
            'facebook' => 'https://www.facebook.com/sharer/sharer.php?' . http_build_query(['u' => $shareUrl]),
            'email' => 'mailto:?' . http_build_query(['subject' => $topic->Title, 'body' => $shareText . ' ' . $shareUrl]),
        ],
    ];
}

public function storeReply(Request $request, Post $post)
{
    $request->validate([
        'ReplyContent' => 'required|string',
        'parent_reply_id' => 'nullable|exists:Reply,ReplyID',
        'attachment' => ['nullable', 'file', 'mimes:pdf,doc,docx,ppt,pptx,png,jpg,jpeg,zip', 'max:20480'],
    ]);

    $attachmentPath = null;
    $attachmentType = null;

    if ($request->hasFile('attachment')) {
        $stored = AttachmentUploader::store($request->file('attachment'));
        $attachmentPath = $stored['path'];
        $attachmentType = $stored['type'];
    }

    Reply::create([
        'PostID' => $post->PostID,
        'UserID' => Auth::id(),
        'ReplyContent' => $request->input('ReplyContent'),
        'ParentReplyID' => $request->input('parent_reply_id'),
        'Attachment' => $attachmentPath,
        'AttachmentType' => $attachmentType,
    ]);

    // Notify the topic's original poster — this is what "My Questions" reads
    // to know a question has a new answer (same Type='Reply' pattern already
    // used for group-chat post replies, nothing new invented).
    if ($post->UserID !== Auth::id()) {
        $replierName = Auth::user()->UserName ?? Auth::user()->name ?? 'Someone';
        $snippet = \Illuminate\Support\Str::limit($request->input('ReplyContent'), 60);

        Notification::create([
            'UserID' => $post->UserID,
            'Message' => "{$replierName} replied to your question: \"{$snippet}\"",
            'Status' => false,
            'Type' => 'Reply',
        ]);
    }

    // Answering a question earns participation credit too — this used to
    // only fire from storeMessage() (group chat), so replying in a topic
    // thread earned nothing.
    $this->updateParticipationScore(Auth::id(), $post->topic?->GroupID);

    // Auto-mark topic as answered if a lecturer replies
    if (Auth::user()->Role === 'Lecturer') {
        $post->topic()->update(['Status' => 'answered']);
    }

    return redirect()->route('topics.show', $post->TopicID);
}

public function flagPost(Request $request, Post $post)
{
    $request->validate(['Reason' => 'nullable|string|max:250']);

    $post->flagBy(Auth::id(), $request->input('Reason'));

    if ($request->wantsJson()) {
        return response()->json(['success' => true]);
    }

    return back()->with('status', 'Post reported. A moderator will review it.');
}

public function flagReply(Request $request, Reply $reply)
{
    $request->validate(['Reason' => 'nullable|string|max:250']);

    $reply->flagBy(Auth::id(), $request->input('Reason'));

    if ($request->wantsJson()) {
        return response()->json(['success' => true]);
    }

    return back()->with('status', 'Reply reported. A moderator will review it.');
}

/**
 * The reply's own author, or a Lecturer/Administrator, can delete it. This
 * is deliberately separate from AdminFlaggedContentController::destroyReply
 * — that one is the moderation-queue action (admin-only middleware); this
 * is the general "delete from the topic thread" action reachable by the
 * author themselves, which the admin-only route can't serve.
 */
public function deleteReply(Reply $reply)
{
    $isOwner = $reply->UserID === Auth::id();
    $isModerator = in_array(Auth::user()->Role, ['Lecturer', 'Administrator'], true);
    abort_unless($isOwner || $isModerator, 403);

    $userId = $reply->UserID;
    $groupId = $reply->post?->topic?->GroupID;

    $reply->delete();

    if ($groupId) {
        app(ParticipationService::class)->recalculate($userId, $groupId);
    }

    if (request()->wantsJson()) {
        return response()->json(['success' => true]);
    }

    return back()->with('status', 'Reply deleted.');
}

/**
 * Every topic/question the student has posted, across all their groups,
 * with an Answered/Waiting status derived from Reply/IsAccepted plus the
 * existing Notification mechanism (Type='Reply') for "new" vs "read" —
 * Notification rows aren't tied to a specific topic ID, so "new" is an
 * approximation (any unread reply notification marks all answered topics
 * with replies as new) rather than a precise per-topic read receipt.
 */
public function myQuestions()
{
    $userId = Auth::id();
    $hasUnreadReplyNotification = Notification::where('UserID', $userId)
        ->where('Type', 'Reply')
        ->where('Status', false)
        ->exists();

    // Accepted replies are eager-loaded (constrained to IsAccepted=true) in
    // the same query set as everything else, instead of one Reply query per
    // topic in the map() below. Paginated so a prolific asker's history
    // doesn't load unbounded.
    $myTopics = Topic::where('CreatedBy', $userId)
        ->with(['group', 'replies' => fn ($q) => $q->where('IsAccepted', true)->with('author')])
        ->withCount('replies')
        ->latest('CreatedAt')
        ->paginate(20)
        ->through(function ($topic) use ($hasUnreadReplyNotification) {
            $topic->acceptedReply = $topic->replies->first();
            $topic->hasUnreadAnswer = $hasUnreadReplyNotification && $topic->replies_count > 0;

            return $topic;
        });

    return view('topics.my-questions', compact('myTopics'))->with('showSidebar', true);
}

public function acceptAnswer(Reply $reply)
{
    $post = $reply->post;

    // The question's original poster or a lecturer can accept an answer —
    // this was Lecturer-only in both the controller check and the button's
    // visibility condition, silently excluding the person who actually
    // asked the question from marking their own answer accepted.
    $isOriginalPoster = Auth::id() === $post->UserID;
    $isLecturer = Auth::user()->Role === 'Lecturer';
    abort_unless($isOriginalPoster || $isLecturer, 403, 'Only the person who asked the question, or a lecturer, can mark an answer as accepted.');

    Reply::where('PostID', $post->PostID)->update(['IsAccepted' => false]);
    $reply->update(['IsAccepted' => true]);
    $post->topic()->update(['Status' => 'answered']);

    // PointsPerAcceptedAnswer only takes effect once the accepting reply's
    // author gets recalculated — do it now rather than waiting for their
    // next post/reply.
    $this->updateParticipationScore($reply->UserID, $post->topic?->GroupID);

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