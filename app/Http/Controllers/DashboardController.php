<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use App\Models\AnnouncementExclusion;
use App\Models\Quiz;
use App\Models\QuizResult;
use App\Models\Group;
use App\Models\GroupStudent;
use App\Models\Notification;
use App\Models\Post;
use App\Models\Reply;
use App\Models\Topic;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{public function index()
{
    $user = Auth::user();

    if ($user->Role === 'Lecturer') {
        return $this->lecturerDashboard();
    }

    if ($user->Role === 'Administrator') {
        return redirect()->route('admin.dashboard');
    }

    if (!$user->groupMemberships()->exists()) {
        return redirect()->route('groups.select');
    }

    $joined_groups = $user->groups()
        ->withCount(['students as member_count'])
        ->get()
        ->map(function ($group) {
            $group->activity_status = 'Active discussion';
            return $group;
        });

    $groupIds = $joined_groups->pluck('GroupID');

    $sharedUserIds = GroupStudent::whereIn('GroupID', $groupIds)
        ->pluck('UserID')
        ->unique();

    $notifications = Notification::where('UserID', $user->UserID)
        ->orderBy('Status')
        ->latest('CreatedAt')
        ->get();

    $notificationsCount = $notifications->where('Status', false)->count();

    $topics = Topic::with('creator')
        ->whereIn('CreatedBy', $sharedUserIds)
        ->latest('CreatedAt')
        ->get()
        ->map(function ($topic) {
            return [
                'user_name' => $topic->creator?->UserName ?? $topic->creator?->name,
                'action' => "Created topic \"{$topic->Title}\"",
                'time' => $topic->CreatedAt->diffForHumans(),
                'created_at' => $topic->CreatedAt,
            ];
        });

    $posts = Post::with('author', 'topic')
        ->whereIn('UserID', $sharedUserIds)
        ->latest('CreatedAt')
        ->get()
        ->map(function ($post) {
            return [
                'user_name' => $post->author?->UserName ?? $post->author?->name,
                'action' => "Posted in topic \"{$post->topic?->Title}\"",
                'time' => $post->CreatedAt->diffForHumans(),
                'created_at' => $post->CreatedAt,
            ];
        });

    $replies = Reply::with('author', 'post')
        ->whereIn('UserID', $sharedUserIds)
        ->latest('CreatedAt')
        ->get()
        ->map(function ($reply) {
            return [
                'user_name' => $reply->author?->UserName ?? $reply->author?->name,
                'action' => "Replied to post in topic \"{$reply->post?->topic?->Title}\"",
                'time' => $reply->CreatedAt->diffForHumans(),
                'created_at' => $reply->CreatedAt,
            ];
        });

    $recentActivity = $topics->concat($posts)->concat($replies)
        ->sortByDesc('created_at')
        ->take(6);

    // NEW: participation + quiz-average snapshot (same formula as marks())
    $snapshot = $this->participationSnapshot($user->UserID);

    // NEW: nearest upcoming quiz across the student's groups
    // ASSUMPTION: quiz.GroupID links a quiz to a group — confirm this matches
    // the logic already used in DiscussionHubPageController::quizzes(); adjust
    // the whereIn column if quizzes are actually scoped differently there.
    $upcomingQuiz = Quiz::whereIn('GroupID', $groupIds)
        ->where('Status', 'scheduled')
        ->where('StartTime', '>', now())
        ->orderBy('StartTime')
        ->first();

    return view('dashboard.index', compact(
        'joined_groups', 'notifications', 'recentActivity', 'notificationsCount',
        'snapshot', 'upcomingQuiz'
    ))
        ->with('showSidebar', true)
        ->with('showNavbar', true);
}
public function marks()
{
    $user = Auth::user();

    if ($user->Role === 'Lecturer') {
        return $this->lecturerMarks();
    }

    $snapshot = $this->participationSnapshot($user->UserID);

    $quizResults = DB::table('quizresult')
        ->where('UserID', $user->UserID)
        ->orderByDesc('SubmissionTime')
        ->get();

    $marks = [
        'participation' => $snapshot['participation'],
        'participation_details' => $snapshot['participation_details'],
        'quiz_average' => $snapshot['quiz_average'],
        'quizzes_taken' => $quizResults->count(),
        'recent_quizzes' => $quizResults->take(5),
    ];

    return view('marks.index', compact('marks'));
}


/**
 * Shared participation + quiz-average calculation, used by both
 * the dashboard snapshot widget and the full Marks page.
 */
protected function participationSnapshot($userId)
{
    $validPosts = DB::table('post')
        ->where('UserID', $userId)
        ->where('IsFlagged', 0)
        ->count();

    $replies = DB::table('reply')
        ->where('UserID', $userId)
        ->count();

    $acceptedAnswers = DB::table('reply')
        ->where('UserID', $userId)
        ->where('IsAccepted', 1)
        ->count();

    $participationScore = round(
        min(10, ($validPosts * 0.5) + ($replies * 0.3) + ($acceptedAnswers * 2)),
        1
    );

    $quizAverage = DB::table('quizresult')
        ->where('UserID', $userId)
        ->avg('Score');

    return [
        'participation' => $participationScore,
        'participation_details' => [
            'posts' => $validPosts,
            'replies' => $replies,
            'accepted_answers' => $acceptedAnswers,
        ],
        'quiz_average' => $quizAverage ? round($quizAverage, 1) : null,
    ];
}

    protected function lecturerDashboard()
    {
        $lecturerId = Auth::id();

        // A lecturer's "courses" are the groups they've scheduled a quiz for —
        // there's no direct Group<->Lecturer link in the schema, so this is
        // the only available signal. If a lecturer hasn't scheduled a quiz
        // yet, their groups/students/discussions will show as 0 here even if
        // they're active in the group's forum.
        $groupIds = Quiz::where('LecturerID', $lecturerId)->where('Status', 'scheduled')->distinct('GroupID')->pluck('GroupID');

        $activeCoursesCount = $groupIds->count();

        $totalStudents = GroupStudent::whereIn('GroupID', $groupIds)
            ->where('Status', 'active')
            ->distinct('UserID')
            ->count('UserID');

        $topicIds = Topic::whereIn('GroupID', $groupIds)->pluck('TopicID');

        $activeDiscussions = Topic::whereIn('GroupID', $groupIds)
            ->whereIn('Status', ['open', 'discussion'])
            ->count();

        $unansweredQuestions = Topic::whereIn('GroupID', $groupIds)
            ->where('Status', 'open')
            ->count();

        $reportedPosts = Post::whereIn('TopicID', $topicIds)
            ->where('IsFlagged', true)
            ->count();

        $recentDiscussions = Topic::whereIn('GroupID', $groupIds)
            ->with(['creator', 'group'])
            ->withCount('posts')
            ->latest('CreatedAt')
            ->take(8)
            ->get();

        $courses = Group::whereIn('GroupID', $groupIds)
            ->withCount(['students as student_count' => fn($q) => $q->where('Status', 'active')])
            ->get();

        return view('lecturer.dash', compact(
            'activeCoursesCount', 'totalStudents', 'activeDiscussions',
            'unansweredQuestions', 'reportedPosts', 'recentDiscussions', 'courses'
        ));
    }

    public function createAnnouncement()
    {
        abort_unless(in_array(Auth::user()->Role, ['Lecturer', 'Administrator'], true), 403);

        // A lecturer can announce to any group — there's no direct
        // Group<->Lecturer link in this schema (lecturerDashboard() notes the
        // same limitation), so unlike the old quiz-scheduled-only list, this
        // is every group rather than an enforced "groups they teach" set.
        // Admins additionally get a "Campus-wide" option (GroupID = null).
        // students is populated per-group from the same cached
        // Group::activeMembers() accessor createTopic() uses, instead of a
        // fresh eager-loaded query every time this form is opened.
        $groups = Group::orderBy('GroupName')
            ->withCount(['students as member_count' => fn ($q) => $q->where('Status', 'active')])
            ->get()
            ->each(fn ($group) => $group->setRelation('students', $group->activeMembers()));
        $isAdmin = Auth::user()->Role === 'Administrator';

        return view('announcements.create', compact('groups', 'isAdmin'));
    }

    public function storeAnnouncement(Request $request)
    {
        $user = Auth::user();
        abort_unless(in_array($user->Role, ['Lecturer', 'Administrator'], true), 403);

        $isAdmin = $user->Role === 'Administrator';

        $request->validate([
            'GroupID' => ($isAdmin ? 'nullable' : 'required') . '|exists:Group,GroupID',
            'message' => 'required|string|max:1000',
            'exclude' => 'array',
            'exclude.*' => 'exists:User,UserID',
        ]);

        $groupId = $request->filled('GroupID') ? (int) $request->GroupID : null;
        abort_if($groupId === null && !$isAdmin, 403, 'Only an admin can send a campus-wide announcement.');

        $excludeIds = collect($request->input('exclude', []))->map(fn ($id) => (int) $id)->unique();

        $studentIds = $groupId
            ? GroupStudent::where('GroupID', $groupId)->where('Status', 'active')->pluck('UserID')
            : GroupStudent::where('Status', 'active')->distinct()->pluck('UserID'); // campus-wide

        $studentIds = $studentIds->diff($excludeIds);

        $announcement = Announcement::create([
            'AuthorID' => $user->UserID,
            'GroupID' => $groupId,
            'Message' => $request->message,
        ]);

        foreach ($excludeIds as $excludedUserId) {
            AnnouncementExclusion::create([
                'AnnouncementID' => $announcement->AnnouncementID,
                'UserID' => $excludedUserId,
            ]);
        }

        $authorName = $user->UserName ?? $user->name ?? 'An announcement';

        foreach ($studentIds as $studentId) {
            Notification::create([
                'UserID' => $studentId,
                'Message' => "{$authorName}: {$request->message}",
                'Status' => false,
                'Type' => 'Announcement',
            ]);
        }

        return redirect()->route('announcements.index')
            ->with('status', 'Announcement sent to ' . $studentIds->count() . ' student(s).');
    }

    public function announcementsIndex()
    {
        $user = Auth::user();
        abort_unless(in_array($user->Role, ['Lecturer', 'Administrator'], true), 403);

        $announcements = Announcement::with(['author', 'group'])
            ->withCount('exclusions')
            ->when($user->Role === 'Lecturer', fn ($q) => $q->where('AuthorID', $user->UserID))
            ->latest('CreatedAt')
            ->paginate(15);

        return view('announcements.index', compact('announcements'));
    }

    protected function lecturerMarks()
    {
        $lecturerId = Auth::id();

        $quizzes = Quiz::where('LecturerID', $lecturerId)
            ->where('Status', 'scheduled')
            ->orderByDesc('StartTime')
            ->get();

        $now = now();

        $upcoming = $quizzes->filter(fn($q) => $q->StartTime > $now)->count();
        $active   = $quizzes->filter(fn($q) =>
            $q->StartTime <= $now && $now <= $q->StartTime->copy()->addMinutes($q->Duration)
        )->count();
        $closed   = $quizzes->filter(fn($q) =>
            $q->StartTime->copy()->addMinutes($q->Duration) < $now
        )->count();

        $recentResults = QuizResult::whereIn('QuizID', $quizzes->pluck('QuizID'))
            ->orderByDesc('SubmissionTime')
            ->take(10)
            ->get();

        $recentDiscussions = collect(); // TODO: replace with real discussions query once Post/Topic feature is ready

        return view('lecturer.marks', compact('quizzes', 'upcoming', 'active', 'closed', 'recentResults', 'recentDiscussions'));
    }
}