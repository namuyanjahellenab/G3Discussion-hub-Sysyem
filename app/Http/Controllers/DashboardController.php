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

class DashboardController extends Controller
{
    public function index()
    {
        $user = Auth::user();

        if ($user->Role === 'Lecturer') {
            return $this->lecturer();
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

        return view('dashboard.index', compact('joined_groups', 'notifications', 'recentActivity', 'notificationsCount'))
            ->with([
                'showSidebar' => false,
                'showNavbar'  => false,
            ]);
    }

    protected function lecturer()
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

        return view('lecturer.dashboard', compact('quizzes', 'upcoming', 'active', 'closed', 'recentResults'));
    }
}