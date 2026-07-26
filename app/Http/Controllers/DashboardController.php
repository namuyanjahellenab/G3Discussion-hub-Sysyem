<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use App\Models\AnnouncementExclusion;
use App\Models\Blacklist;
use App\Models\Question;
use App\Models\Quiz;
use App\Models\QuizResult;
use App\Models\Group;
use App\Models\GroupStudent;
use App\Models\Notification;
use App\Models\Post;
use App\Models\Reply;
use App\Models\Topic;
use App\Models\Warning;
use App\Services\ParticipationService;
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
        // Role only ever becomes 'Administrator' for a promoted lecturer
        // once they explicitly click "Go to Admin Dashboard"
        // (switchToAdmin()) - promote() itself leaves Role untouched, so a
        // just-promoted lecturer's Role is still 'Lecturer' and they hit the
        // branch above instead, landing on their own dashboard by default.
        return redirect()->route('admin.dashboard');
    }

    if (!$user->groupMemberships()->exists()) {
        return redirect()->route('groups.select');
    }

    $joinedGroups = $user->groups()
        ->withCount(['students as member_count'])
        ->get()
        ->map(function ($group) {
            $group->activity_status = 'Active discussion';
            return $group;
        });

    $groupIds = $joinedGroups->pluck('GroupID');

    $sharedUserIds = GroupStudent::whereIn('GroupID', $groupIds)
        ->pluck('UserID')
        ->unique();

    $notifications = Notification::where('UserID', $user->UserID)
        ->excludingStaleGroupNotifications()
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

    // A quiz already open right now (student hasn't submitted yet) takes
    // priority over a future one in the panel below - same "active now"
    // window QuizEngineController::activeNow() uses for the popup, so the
    // dashboard's own "available" indicator gets a direct take-quiz link
    // instead of only ever pointing at a still-upcoming quiz.
    $ongoingQuiz = Quiz::whereIn('GroupID', $groupIds)
        ->where('StartTime', '<=', now())
        ->whereRaw('DATE_ADD(StartTime, INTERVAL Duration MINUTE) >= ?', [now()])
        ->whereNotExists(function ($q) use ($user) {
            $q->select(DB::raw(1))
                ->from('QuizResult')
                ->whereColumn('QuizResult.QuizID', 'Quiz.QuizID')
                ->where('QuizResult.UserID', $user->UserID);
        })
        ->orderBy('StartTime')
        ->first();

    // Most recent announcement for one of the student's groups, skipping
    // any this student was specifically excluded from.
    $latestAnnouncement = Announcement::whereIn('GroupID', $groupIds)
        ->whereDoesntHave('exclusions', fn ($q) => $q->where('UserID', $user->UserID))
        ->with('group')
        ->latest('CreatedAt')
        ->first();

    // Surfaced as a dashboard banner/panel — a blacklisted or warned student
    // otherwise has no way to see this (the AuthController login gate only
    // catches it on a fresh login, not an already-open session).
    $activeBlacklist = Blacklist::where('UserID', $user->UserID)->active()->latest('CreatedAt')->first();
    $activeWarnings = Warning::where('UserID', $user->UserID)
        ->where('ExpiryDate', '>', now())
        ->orderByDesc('CreatedAt')
        ->get();

    return view('dashboard.index', compact(
        'joinedGroups', 'notifications', 'recentActivity', 'notificationsCount',
        'snapshot', 'upcomingQuiz', 'ongoingQuiz', 'latestAnnouncement', 'activeBlacklist', 'activeWarnings'
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

    $groups = app(\App\Services\MarksService::class)->marksBreakdownForUser($user->UserID);

    return view('marks.index', compact('groups'));
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

    $curvedScores = app(ParticipationService::class)->curvedScoresForUser($userId);
    $participationScore = $curvedScores->isNotEmpty()
        ? round($curvedScores->avg('participation'), 1)
        : 0.0;

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

    /**
     * A lecturer promoted to Admin keeps PreviousRole = 'Lecturer' on file
     * (only the main admin's own "Demote" action clears it - see
     * AdminLecturerStaffController::demote()), which is what lets them
     * toggle back and forth between the two dashboards themselves via these
     * two actions. Each one actually flips User.Role in the database (not
     * just a view swap), so index()'s own Role-based routing, the 'admin'
     * middleware, and AdminStatisticsController's totalAdmins/totalLecturers
     * counts all immediately agree with whichever side they're on - nothing
     * needed to special-case that separately.
     */
    public function switchToAdmin()
    {
        $user = Auth::user();
        abort_unless($user->PreviousRole !== null, 403);

        $user->update(['Role' => 'Administrator']);

        return redirect()->route('admin.dashboard');
    }

    public function switchToLecturer()
    {
        $user = Auth::user();
        abort_unless($user->PreviousRole !== null, 403);

        $user->update(['Role' => 'Lecturer']);

        return redirect()->route('dashboard');
    }

    protected function lecturerDashboard()
    {
        $lecturerId = Auth::id();

        // There's no direct Group<->Lecturer link in the schema. A lecturer
        // joins a group the same way a student does (GroupController::join,
        // via the "Groups" browse/join page), which writes a GroupStudent
        // row for them - so "groups the lecturer has joined" is exactly
        // their own GroupStudent memberships.
        $groupIds = GroupStudent::where('UserID', $lecturerId)->pluck('GroupID')->unique()->values();

        $activeCoursesCount = $groupIds->count();

        // Excludes the lecturer's own membership row (UserID != $lecturerId)
        // - otherwise the lecturer would count themselves as one of their
        // own students. distinct('UserID') across the whole $groupIds set
        // (not per-group) keeps a student in two joined groups from being
        // counted twice here.
        $totalStudents = GroupStudent::whereIn('GroupID', $groupIds)
            ->where('UserID', '!=', $lecturerId)
            ->where('Status', 'active')
            ->distinct('UserID')
            ->count('UserID');

        $activeDiscussions = Topic::whereIn('GroupID', $groupIds)
            ->whereIn('Status', ['open', 'discussion'])
            ->count();

        $unansweredQuestions = Topic::whereIn('GroupID', $groupIds)
            ->where('Status', 'open')
            ->count();

        $recentDiscussions = Topic::whereIn('GroupID', $groupIds)
            ->with(['creator', 'group'])
            ->withCount('posts')
            ->latest('CreatedAt')
            ->take(8)
            ->get();

        $courses = Group::whereIn('GroupID', $groupIds)
            ->withCount(['students as student_count' => fn($q) => $q->where('Status', 'active')->where('UserID', '!=', $lecturerId)])
            ->get();

        return view('lecturer.dash', compact(
            'activeCoursesCount', 'totalStudents', 'activeDiscussions',
            'unansweredQuestions', 'recentDiscussions', 'courses'
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

        // Joined with User/Quiz so the "Recent Submissions" card can show who
        // actually took it and which quiz, instead of a bare "Student #16"
        // with no context - TotalMarks per quiz is added after the fact
        // since it's a sum across Question rather than a plain column.
        $recentResults = QuizResult::whereIn('QuizResult.QuizID', $quizzes->pluck('QuizID'))
            ->join('User', 'QuizResult.UserID', '=', 'User.UserID')
            ->join('Quiz', 'QuizResult.QuizID', '=', 'Quiz.QuizID')
            ->select(
                'QuizResult.ResultID',
                'QuizResult.Score',
                'QuizResult.SubmissionTime',
                'QuizResult.IsAutoSubmit',
                'User.UserName as StudentName',
                'Quiz.Title as QuizTitle',
                'Quiz.QuizID'
            )
            ->orderByDesc('QuizResult.SubmissionTime')
            ->take(10)
            ->get();

        $totalMarksByQuiz = Question::whereIn('QuizID', $recentResults->pluck('QuizID')->unique())
            ->selectRaw('QuizID, SUM(Marks) as total')
            ->groupBy('QuizID')
            ->pluck('total', 'QuizID');

        $recentDiscussions = collect(); // TODO: replace with real discussions query once Post/Topic feature is ready

        return view('lecturer.marks', compact('quizzes', 'upcoming', 'active', 'closed', 'recentResults', 'totalMarksByQuiz', 'recentDiscussions'));
    }
}