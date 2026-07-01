<?php

namespace App\Http\Controllers;

use App\Models\GroupStudent;
use App\Models\Post;
use App\Models\Quiz;
use App\Models\QuizResult;
use App\Models\Recommendation;
use App\Models\Topic;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Dompdf\Dompdf;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\RedirectResponse;

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

        return view('forum.index', compact('joinedGroups', 'topics'));
    }

    public function messages(Request $request)
    {
        $user = Auth::user();
        if (!$user instanceof \App\Models\User) {
            $user = \App\Models\User::find(Auth::id());
        }
        $joinedGroups = $user->groups()->withCount(['students as member_count'])->get();
        $groupIds = $joinedGroups->pluck('GroupID');
        $memberIds = GroupStudent::whereIn('GroupID', $groupIds)->pluck('UserID')->unique();

        $query = Post::with(['author', 'topic', 'parent.author', 'replies.author'])
            ->whereHas('topic', function ($topicQuery) use ($memberIds) {
                $topicQuery->whereIn('CreatedBy', $memberIds);
            })
            ->orderBy('CreatedAt');

        if ($request->filled('group_id')) {
            $groupMemberIds = GroupStudent::where('GroupID', $request->group_id)->pluck('UserID');
            $query->whereHas('topic', function ($topicQuery) use ($groupMemberIds) {
                $topicQuery->whereIn('CreatedBy', $groupMemberIds);
            });
        }

        if ($request->filled('topic_id')) {
            $query->where('TopicID', $request->topic_id);
        }

        $posts = $query->get();
        $threadedPosts = $posts->whereNull('ParentPostID')->values()->map(function ($post) use ($posts) {
            $post->setRelation('replies', $posts->where('ParentPostID', $post->PostID)->values());

            return $post;
        });
        $topics = Topic::whereIn('CreatedBy', $memberIds)->latest('CreatedAt')->get();
        $replyToPost = $request->filled('reply_to') ? Post::find($request->reply_to) : null;

        return view('messages.index', compact('joinedGroups', 'threadedPosts', 'topics', 'replyToPost'));
    }

    public function storeMessage(Request $request): RedirectResponse
    {
        $request->validate([
            'topic_id' => ['required', 'exists:Topic,TopicID'],
            'content' => ['nullable', 'string'],
            'attachment' => ['nullable', 'file', 'mimes:pdf,doc,docx,ppt,pptx,png,jpg,jpeg,zip', 'max:20480'],
            'parent_post_id' => ['nullable', 'exists:Post,PostID'],
        ]);

        if (blank($request->content) && !$request->hasFile('attachment')) {
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

        Post::create([
            'TopicID' => $request->topic_id,
            'UserID' => Auth::id(),
            'Content' => $request->input('content', ''),
            'ParentPostID' => $request->input('parent_post_id'),
            'Attachment' => $attachmentPath,
            'AttachmentType' => $attachmentType,
        ]);

        return redirect()->route('messages.index', ['topic_id' => $request->topic_id])
            ->with('status', 'Message sent successfully.');
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
        $marks = [
            'coursework' => 78,
            'cats' => 84,
            'exams' => 81,
            'gpa' => 4.2,
        ];

        return view('marks.index', compact('marks'));
    }

    public function quizzes()
    {
        $quizzes = Quiz::latest('CreatedAt')->get();
        $completed = QuizResult::where('UserID', Auth::id())->with('quiz')->latest('SubmissionTime')->get();
        $upcoming = Quiz::where('CreatedAt', '>=', now()->subDays(7))->get();
        $scores = QuizResult::where('UserID', Auth::id())->get();

        return view('quizzes.index', compact('quizzes', 'completed', 'upcoming', 'scores'));
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

        return view('recommend.index', compact('joinedGroups', 'recommendedTopics', 'recommendedStudents'));
    }

    public function settings()
    {
        return view('settings.index', [
            'user' => Auth::user(),
            'preferences' => session('notification_preferences', ['email' => true, 'push' => true]),
            'darkMode' => session('dark_mode', false),
        ]);
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
