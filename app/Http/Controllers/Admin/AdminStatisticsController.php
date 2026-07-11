<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Models\Reply;
use App\Models\Quiz;
use App\Models\QuizResult;
use App\Models\Group;
use App\Models\Blacklist;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AdminStatisticsController extends Controller
{
    public function index(Request $request)
    {
        $groupId = $request->get('group_id');
        $groups = Group::orderBy('GroupName')->get();

        $today = Carbon::today();
        $yesterday = Carbon::yesterday();

        // ---- Active Users (posted or replied today) ----
        $activeUsersToday = $this->activeUserIds($today, $groupId)->count();
        $activeUsersYesterday = $this->activeUserIds($yesterday, $groupId, true)->count();
        $activeUsersChange = $this->percentChange($activeUsersYesterday, $activeUsersToday);

        // ---- Posts Today ----
        $postsTodayQuery = Post::whereDate('CreatedAt', $today);
        $postsYesterdayQuery = Post::whereDate('CreatedAt', $yesterday);
        if ($groupId) {
            $postsTodayQuery->whereHas('topic', fn($q) => $q->where('GroupID', $groupId));
            $postsYesterdayQuery->whereHas('topic', fn($q) => $q->where('GroupID', $groupId));
        }
        $postsToday = $postsTodayQuery->count();
        $postsYesterday = $postsYesterdayQuery->count();
        $postsChange = $this->percentChange($postsYesterday, $postsToday);

        // ---- Open Questions (top-level posts with no accepted reply) ----
        $openQuestionsQuery = Post::whereNull('ParentPostID')
            ->whereDoesntHave('replies', fn($q) => $q->where('IsAccepted', true));
        if ($groupId) {
            $openQuestionsQuery->whereHas('topic', fn($q) => $q->where('GroupID', $groupId));
        }
        $openQuestions = $openQuestionsQuery->count();

        // ---- Quizzes Run ----
        $quizzesQuery = Quiz::query();
        if ($groupId) {
            $quizzesQuery->where('GroupID', $groupId);
        }
        $quizzesRun = $quizzesQuery->count();

        // ---- Posts Per Day (last 7 days) ----
        $postsPerDay = [];
        for ($i = 6; $i >= 0; $i--) {
            $day = Carbon::today()->subDays($i);
            $q = Post::whereDate('CreatedAt', $day);
            if ($groupId) {
                $q->whereHas('topic', fn($qq) => $qq->where('GroupID', $groupId));
            }
            $postsPerDay[] = [
                'label' => $day->format('D'),
                'count' => $q->count(),
            ];
        }

        // ---- Top Members leaderboard ----
        $topMembers = DB::table('participation')
    ->join('user', 'user.UserID', '=', 'participation.UserID')
    ->select(
        'user.UserID',
        'user.UserName',
        'participation.ParticipationScore',
        'participation.PostCount',
        'participation.ReplyCount'
    )
    ->orderByDesc('participation.ParticipationScore')
    ->limit(10)
    ->get()
    ->map(function ($row) {
        $row->BlacklistCount = Blacklist::where('UserID', $row->UserID)->count();
        return $row;
    });
    $blacklistedCount = Blacklist::active()
    ->when($groupId, function ($q) use ($groupId) {
        $q->whereIn('UserID', DB::table('groupstudent')->where('GroupID', $groupId)->pluck('UserID'));
    })
    ->distinct('UserID')
    ->count('UserID');

        // ---- Group Participation Summary ----
        $groupSummary = $groups->map(function ($group) {
            $totalPosts = Post::whereHas('topic', fn($q) => $q->where('GroupID', $group->GroupID))->count();

            $activeUsers = $this->activeUserIds(null, $group->GroupID, false, true)->count();

            $enrolledCount = DB::table('groupstudent')->where('GroupID', $group->GroupID)->count();
            $quizIds = Quiz::where('GroupID', $group->GroupID)->pluck('QuizID');
            $expectedSubmissions = $quizIds->count() * max($enrolledCount, 1);
            $actualSubmissions = QuizResult::whereIn('QuizID', $quizIds)->count();
            $quizCompletion = $expectedSubmissions > 0
                ? round(($actualSubmissions / $expectedSubmissions) * 100, 1)
                : 0;

            $flaggedContent = Post::whereHas('topic', fn($q) => $q->where('GroupID', $group->GroupID))
                ->where('IsFlagged', true)
                ->count();

            return [
                'GroupName' => $group->GroupName,
                'TotalPosts' => $totalPosts,
                'ActiveUsers' => $activeUsers,
                'QuizCompletion' => $quizCompletion,
                'FlaggedContent' => $flaggedContent,
            ];
        });

        return view('admin.statistics.index', compact(
    'groups', 'groupId', 'activeUsersToday', 'activeUsersChange',
    'postsToday', 'postsChange', 'openQuestions', 'quizzesRun',
    'postsPerDay', 'topMembers', 'groupSummary', 'blacklistedCount'
));
    }

    public function export(Request $request)
    {
        $groupId = $request->get('group_id');
        $groups = $groupId ? Group::where('GroupID', $groupId)->get() : Group::all();

        $rows = $groups->map(function ($group) {
            $totalPosts = Post::whereHas('topic', fn($q) => $q->where('GroupID', $group->GroupID))->count();
            $activeUsers = $this->activeUserIds(null, $group->GroupID, false, true)->count();
            $flaggedContent = Post::whereHas('topic', fn($q) => $q->where('GroupID', $group->GroupID))
                ->where('IsFlagged', true)->count();

            return [$group->GroupName, $totalPosts, $activeUsers, $flaggedContent];
        });

        $filename = 'group_participation_summary_' . now()->format('Y_m_d') . '.csv';
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"$filename\"",
        ];

        $callback = function () use ($rows) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Group', 'Total Posts', 'Active Users', 'Flagged Content']);
            foreach ($rows as $row) {
                fputcsv($handle, $row);
            }
            fclose($handle);
        };

        return response()->stream($callback, 200, $headers);
    }

    private function activeUserIds($date = null, $groupId = null, $singleDay = true, $allTime = false)
    {
        $postQuery = Post::query();
        $replyQuery = Reply::query();

        if (!$allTime && $date) {
            $postQuery->whereDate('CreatedAt', $date);
            $replyQuery->whereDate('CreatedAt', $date);
        }

        if ($groupId) {
            $postQuery->whereHas('topic', fn($q) => $q->where('GroupID', $groupId));
            $replyQuery->whereHas('post.topic', fn($q) => $q->where('GroupID', $groupId));
        }

        $postUserIds = $postQuery->pluck('UserID');
        $replyUserIds = $replyQuery->pluck('UserID');

        return $postUserIds->merge($replyUserIds)->unique();
    }

    private function percentChange($old, $new)
    {
        if ($old == 0) {
            return $new > 0 ? 100 : 0;
        }
        return round((($new - $old) / $old) * 100, 1);
    }
}