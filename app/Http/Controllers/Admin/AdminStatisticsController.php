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
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AdminStatisticsController extends Controller
{
    public function index(Request $request)
    {
        $groupId = $request->get('group_id');
        $groups = Group::orderBy('GroupName')->get();

        // The whole dashboard is a snapshot, not a live feed — every widget
        // here was a fresh set of queries on every single page view/filter
        // change. Cached per group + per day (so a "today vs yesterday"
        // comparison never survives a real day boundary even before the TTL
        // hits) for a short 2 minutes, which is plenty for an admin refreshing
        // a stats page without re-running ~15 queries each time.
        $cacheKey = 'admin_statistics:' . ($groupId ?: 'all') . ':' . Carbon::today()->toDateString();

        $stats = Cache::remember($cacheKey, 120, function () use ($groupId, $groups) {
            return $this->computeStatistics($groupId, $groups);
        });

        return view('admin.statistics.index', array_merge($stats, compact('groups', 'groupId')));
    }

    private function computeStatistics($groupId, $groups): array
    {
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
        $quizzesQuery = Quiz::where('Status', 'scheduled');
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
        // Participation is now per-group (see ParticipationCriteria/
        // ParticipationService), so a selected group filters to that group's
        // rows directly; with no group selected, sum each user's scores
        // across all their groups instead of just picking one arbitrary row.
        $topMembersQuery = DB::table('participation')->join('user', 'user.UserID', '=', 'participation.UserID');

        if ($groupId) {
            $topMembers = $topMembersQuery
                ->where('participation.GroupID', $groupId)
                ->select(
                    'user.UserID',
                    'user.UserName',
                    'participation.ParticipationScore',
                    'participation.PostCount',
                    'participation.ReplyCount'
                )
                ->orderByDesc('participation.ParticipationScore')
                ->limit(10)
                ->get();
        } else {
            $topMembers = $topMembersQuery
                ->select(
                    'user.UserID',
                    'user.UserName',
                    DB::raw('SUM(participation.ParticipationScore) as ParticipationScore'),
                    DB::raw('SUM(participation.PostCount) as PostCount'),
                    DB::raw('SUM(participation.ReplyCount) as ReplyCount')
                )
                ->groupBy('user.UserID', 'user.UserName')
                ->orderByDesc('ParticipationScore')
                ->limit(10)
                ->get();
        }

        // Batch the blacklist count for all top members in one query instead
        // of one query per row.
        $blacklistCounts = Blacklist::whereIn('UserID', $topMembers->pluck('UserID'))
            ->selectRaw('UserID, COUNT(*) as total')
            ->groupBy('UserID')
            ->pluck('total', 'UserID');

        $topMembers = $topMembers->map(function ($row) use ($blacklistCounts) {
            $row->BlacklistCount = $blacklistCounts[$row->UserID] ?? 0;
            return $row;
        });
    $blacklistedCount = Blacklist::active()
    ->when($groupId, function ($q) use ($groupId) {
        $q->whereIn('UserID', DB::table('groupstudent')->where('GroupID', $groupId)->pluck('UserID'));
    })
    ->distinct('UserID')
    ->count('UserID');

        // ---- Group Participation Summary ----
        // Was one map() over $groups issuing ~6 queries per group (totalPosts,
        // activeUsers x2, enrolledCount, quizIds, actualSubmissions,
        // flaggedContent) — batchGroupStats() does the same work in a fixed
        // number of queries regardless of how many groups exist.
        $groupSummary = collect($this->batchGroupStats($groups))->values();

        return compact(
            'activeUsersToday', 'activeUsersChange',
            'postsToday', 'postsChange', 'openQuestions', 'quizzesRun',
            'postsPerDay', 'topMembers', 'groupSummary', 'blacklistedCount'
        );
    }

    public function export(Request $request)
    {
        $groupId = $request->get('group_id');
        $groups = $groupId ? Group::where('GroupID', $groupId)->get() : Group::all();

        $stats = $this->batchGroupStats($groups);
        $rows = $groups->map(fn ($group) => [
            $group->GroupName,
            $stats[$group->GroupID]['TotalPosts'],
            $stats[$group->GroupID]['ActiveUsers'],
            $stats[$group->GroupID]['FlaggedContent'],
        ]);

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

    /**
     * TotalPosts/ActiveUsers/QuizCompletion/FlaggedContent for every group in
     * $groups, computed with a fixed number of batched queries instead of
     * ~6 queries per group.
     */
    private function batchGroupStats($groups): array
    {
        $groupIds = $groups->pluck('GroupID');

        $totalPostsByGroup = DB::table('Post')
            ->join('Topic', 'Post.TopicID', '=', 'Topic.TopicID')
            ->whereIn('Topic.GroupID', $groupIds)
            ->selectRaw('Topic.GroupID as GroupID, COUNT(*) as total')
            ->groupBy('Topic.GroupID')
            ->pluck('total', 'GroupID');

        $flaggedByGroup = DB::table('Post')
            ->join('Topic', 'Post.TopicID', '=', 'Topic.TopicID')
            ->whereIn('Topic.GroupID', $groupIds)
            ->where('Post.IsFlagged', true)
            ->selectRaw('Topic.GroupID as GroupID, COUNT(*) as total')
            ->groupBy('Topic.GroupID')
            ->pluck('total', 'GroupID');

        $enrolledByGroup = DB::table('groupstudent')
            ->whereIn('GroupID', $groupIds)
            ->selectRaw('GroupID, COUNT(*) as total')
            ->groupBy('GroupID')
            ->pluck('total', 'GroupID');

        $scheduledQuizzes = Quiz::whereIn('GroupID', $groupIds)->where('Status', 'scheduled')->get(['QuizID', 'GroupID']);
        $quizIdsByGroup = $scheduledQuizzes->groupBy('GroupID')->map(fn ($rows) => $rows->pluck('QuizID'));

        $submissionsByQuiz = QuizResult::whereIn('QuizID', $scheduledQuizzes->pluck('QuizID'))
            ->selectRaw('QuizID, COUNT(*) as total')
            ->groupBy('QuizID')
            ->pluck('total', 'QuizID');

        $postUsersByGroup = DB::table('Post')
            ->join('Topic', 'Post.TopicID', '=', 'Topic.TopicID')
            ->whereIn('Topic.GroupID', $groupIds)
            ->select('Topic.GroupID', 'Post.UserID')
            ->distinct()
            ->get()
            ->groupBy('GroupID')
            ->map(fn ($rows) => $rows->pluck('UserID'));

        $replyUsersByGroup = DB::table('Reply')
            ->join('Post', 'Reply.PostID', '=', 'Post.PostID')
            ->join('Topic', 'Post.TopicID', '=', 'Topic.TopicID')
            ->whereIn('Topic.GroupID', $groupIds)
            ->select('Topic.GroupID', 'Reply.UserID')
            ->distinct()
            ->get()
            ->groupBy('GroupID')
            ->map(fn ($rows) => $rows->pluck('UserID'));

        $stats = [];
        foreach ($groups as $group) {
            $gid = $group->GroupID;
            $enrolledCount = $enrolledByGroup[$gid] ?? 0;
            $quizIds = $quizIdsByGroup[$gid] ?? collect();
            $expectedSubmissions = $quizIds->count() * max($enrolledCount, 1);
            $actualSubmissions = $quizIds->sum(fn ($qid) => $submissionsByQuiz[$qid] ?? 0);
            $quizCompletion = $expectedSubmissions > 0
                ? round(($actualSubmissions / $expectedSubmissions) * 100, 1)
                : 0;

            $activeUsers = collect($postUsersByGroup[$gid] ?? [])
                ->merge($replyUsersByGroup[$gid] ?? [])
                ->unique()
                ->count();

            $stats[$gid] = [
                'GroupName' => $group->GroupName,
                'TotalPosts' => $totalPostsByGroup[$gid] ?? 0,
                'ActiveUsers' => $activeUsers,
                'QuizCompletion' => $quizCompletion,
                'FlaggedContent' => $flaggedByGroup[$gid] ?? 0,
            ];
        }

        return $stats;
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