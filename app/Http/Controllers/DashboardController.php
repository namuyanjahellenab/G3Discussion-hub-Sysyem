<?php

namespace App\Http\Controllers;

use App\Models\Quiz;
use App\Models\QuizResult;
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

        // TODO: replace these placeholder values with real queries once
        // course/discussion features are fully built out
        $activeCoursesCount = Quiz::where('LecturerID', $lecturerId)->distinct('GroupID')->count('GroupID');
        $totalStudents = 0;
        $activeDiscussions = 0;
        $newDiscussionsToday = 0;
        $unansweredQuestions = 0;
        $reportedPosts = 0;
        $reportedPostsChange = 0;
        $recentDiscussions = collect();

        return view('lecturer.dash', compact(
            'activeCoursesCount', 'totalStudents', 'activeDiscussions',
            'newDiscussionsToday', 'unansweredQuestions', 'reportedPosts',
            'reportedPostsChange', 'recentDiscussions'
        ));
    }

    protected function lecturerMarks()
    {
        $lecturerId = Auth::id();

        $quizzes = Quiz::where('LecturerID', $lecturerId)
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