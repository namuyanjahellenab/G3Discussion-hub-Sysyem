<?php

namespace App\Http\Controllers;

use App\Models\GroupStudent;
use App\Models\Post;
use App\Models\Quiz;
use App\Models\QuizResult;
use App\Models\Recommendation;
use App\Models\Topic;
use App\Models\User;
use App\Models\Group;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Dompdf\Dompdf;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;

class DiscussionHubPageController extends Controller
{
    public function forum()
    {
        $user = Auth::user();
        if (!$user instanceof \App\Models\User) {
            $user = \App\Models\User::find(Auth::id());
        }
        $joinedGroups = $user->groups()->withCount(['students as member_count'])->get();

        $groupIds = $joinedGroups->pluck('GroupID');
        $memberIds = GroupStudent::whereIn('GroupID', $groupIds)->pluck('UserID')->unique();

        $topics = Topic::whereIn('CreatedBy', $memberIds)
            ->with('creator')
            ->latest('CreatedAt')
            ->get();

        return view('forum.index', compact('joinedGroups', 'topics'))->with('showSidebar', false);
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
            ->whereHas('topic', function ($topicQuery) use ($memberIds) {
                $topicQuery->whereIn('CreatedBy', $memberIds);
            })
            ->orderBy('CreatedAt');

        if ($topicId) {
            $query->where('TopicID', $topicId);
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
        $topics = Topic::whereIn('CreatedBy', $memberIds)
            ->when($groupId, function ($topicQuery) use ($groupId) {
                $topicQuery->where('GroupID', $groupId);
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
        
        if ($request->wantsJson() || $request->header('Accept') === 'application/json') {
            $request->validate([
                'topic_id' => ['required', 'exists:Topic,TopicID'],
                'group_id' => ['nullable', 'exists:Group,GroupID'],
                'content' => ['nullable', 'string'],
                'attachment' => ['nullable', 'file', 'mimes:pdf,doc,docx,ppt,pptx,png,jpg,jpeg,zip', 'max:20480'],
                'parent_post_id' => ['nullable', 'exists:Post,PostID'],
            ]);
        } else {
            $request->validate([
                //'topic_id' => ['required', 'exists:Topic,TopicID'],
                'group_id' => ['nullable', 'exists:Group,GroupID'],
                'content' => ['nullable', 'string'],
                'attachment' => ['nullable', 'file', 'mimes:pdf,doc,docx,ppt,pptx,png,jpg,jpeg,zip', 'max:20480'],
                'parent_post_id' => ['nullable', 'exists:Post,PostID'],
            ]);
        }


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

            // Enforce that the chosen topic belongs to the selected group (requires GroupID on Topic).
           $topic = Topic::query()->where('TopicID', $request->topic_id)->first();
           if (!$topic || (string)($topic->GroupID ?? '') !== (string)$groupId) {
               abort(403, 'Selected topic does not belong to the selected group.');
            }
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

        // For AJAX: return rendered bubble HTML (same structure as existing @foreach loop)
        if ($request->wantsJson() || $request->header('Accept') === 'application/json') {
            $post->load('author');

            // Determine mine wrapper (frontend logic uses AuthorID/isMine)
            $isMine = (string) $post->AuthorID === (string) Auth::id();
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
                    . "<a href=\"" . asset('storage/' . $post->Attachment) . "\" target=\"_blank\" style=\"color: var(--primary-color); text-decoration: none; font-weight: 500;\">View Attached Document</a>"
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
            $html .= "<span class=\"reply-action-btn\" onclick=\"setReplyContext('" . ($isMine ? 'You' : $senderName) . "', '{$escapedSnippetJs}')\"><i class=\"fa-solid fa-reply\"></i> Reply</span>";

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

        $groupId = $request->input('group_id');

        if (!empty($groupId)) {
            $userId = Auth::id();
            $isMember = GroupStudent::where('GroupID', $groupId)
                ->where('UserID', $userId)
                ->exists();

            if (!$isMember) {
                abort(403, 'You are not a member of the selected group.');
            }

            $topic = Topic::query()->where('TopicID', $request->topic_id)->first();
            if (!$topic || (string)($topic->GroupID ?? '') !== (string)$groupId) {
                abort(403, 'Selected topic does not belong to the selected group.');
            }
        }

        // newer_than is PostID
        $posts = Post::with(['author'])
            ->where('TopicID', $request->topic_id)
            ->when(!empty($groupId), function ($q) use ($groupId) {
                $q->whereHas('topic', function ($t) use ($groupId) {
                    $t->where('GroupID', $groupId);
                });
            })
            ->where('PostID', '>', $request->input('newer_than'))
            ->orderBy('CreatedAt')
            ->get();

        $html = '';
        $latestId = $request->input('newer_than');

        foreach ($posts as $post) {
            $post->load('author');
            $isMine = (string)$post->AuthorID === (string)Auth::id();
            $senderName = $post->author?->UserName ?? $post->author?->name ?? 'Student';

            $loopParts = explode(' ', $senderName);
            $loopInitials = collect($loopParts)->filter()->map(fn($p) => mb_substr($p,0,1))->take(2)->implode('');

            $content = e($post->Content ?? '');

            $parentReplyText = $post->parent?->Content ?? null;
            $parentReplyTextHtml = !empty($parentReplyText)
                ? "<div style=\"background: rgba(0,0,0,0.05); border-left: 3px solid var(--primary-color); padding: 6px 10px; font-size: 0.8rem; border-radius: 4px; margin-bottom: 8px; color: var(--text-muted);\"><i class=\"fa-solid fa-quote-left\" style=\"font-size:0.65rem; margin-right:4px; opacity:0.5;\"></i> " . e($parentReplyText) . "</div>"
                : '';

            $attachmentHtml = !empty($post->Attachment)
                ? "<div style=\"margin-top: 8px; padding: 6px 10px; background: rgba(0,0,0,0.04); border-radius: 6px; display: flex; align-items: center; gap: 8px; font-size: 0.8rem;\"><i class=\"fa-solid fa-paperclip\" style=\"color: var(--text-muted);\"></i><a href=\"" . asset('storage/' . $post->Attachment) . "\" target=\"_blank\" style=\"color: var(--primary-color); text-decoration: none; font-weight: 500;\">View Attached Document</a></div>"
                : '';

            $wrapperClass = $isMine ? 'mine-wrapper' : 'theirs-wrapper';
            $snippet = mb_substr(trim((string)($post->Content ?? '')), 0, 50);
            $escapedSnippetJs = addslashes($snippet);

            $html .= "<div class=\"msg-bubble-wrapper {$wrapperClass}\" data-post-id=\"{$post->PostID}\" data-sender=\"" . e($senderName) . "\" data-role=\"Verified Contributor\" data-email=\"" . e($post->author?->email ?? 'unspecified@domain.edu') . "\">";
            if (!$isMine) {
                $html .= "<div class=\"avatar-circle-ui avatar-green view-sender-profile\" style=\"cursor: pointer;\">" . e($loopInitials ?: 'ST') . "</div>";
            }

            $html .= "<span class=\"reply-action-btn\" onclick=\"setReplyContext('" . ($isMine ? 'You' : $senderName) . "', '{$escapedSnippetJs}')\"><i class=\"fa-solid fa-reply\"></i> Reply</span>";

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
public function marks()
{
    if (auth()->user()->Role === 'Lecturer') {
        return redirect()->route('dashboard');
    }

    $marks = [
        'coursework' => 78,
        'cats' => 84,
        'exams' => 81,
        'gpa' => 4.2,
    ];

    return view('marks.index', compact('marks'))->with('showSidebar', false);
}

    public function quizzes()
    {
        $quizzes = Quiz::latest('CreatedAt')->get();
        $completed = QuizResult::where('UserID', Auth::id())->with('quiz')->latest('SubmissionTime')->get();
        $upcoming = Quiz::where('CreatedAt', '>=', now()->subDays(7))->get();
        $scores = QuizResult::where('UserID', Auth::id())->get();

        return view('quizzes.index', compact('quizzes', 'completed', 'upcoming', 'scores'))->with('showSidebar', false);
    }

    public function recommend()
    {
        $user = Auth::user();
        if (!$user instanceof \App\Models\User) {
            $user = \App\Models\User::find(Auth::id());
        }
        $joinedGroups = $user->groups()->withCount(['students as member_count'])->get();
        $groupIds = $joinedGroups->pluck('GroupID');
        $memberIds = GroupStudent::whereIn('GroupID', $groupIds)->pluck('UserID')->unique();

        $recommendedTopics = Topic::whereIn('CreatedBy', $memberIds)
            ->with('creator')
            ->latest('CreatedAt')
            ->take(4)
            ->get();

        $recommendedStudents = User::where('UserID', '!=', $user->UserID)
            ->whereIn('UserID', $memberIds)
            ->take(4)
            ->get();
return view('recommend.index', compact('joinedGroups', 'recommendedTopics', 'recommendedStudents'))->with('showSidebar', false);
    }

    public function settings()
    {
        return view('settings.index', [
    'user' => Auth::user(),
    'preferences' => session('notification_preferences', ['email' => true, 'push' => true]),
    'darkMode' => session('dark_mode', false),
])->with('showSidebar', false);
        
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

        $user->save();

        session()->put('notification_preferences', [
            'email' => $request->boolean('email_notifications'),
            'push' => $request->boolean('push_notifications'),
        ]);
        session()->put('dark_mode', $request->boolean('dark_mode'));

        return back()->with('status', 'Settings updated.');
    }
}
