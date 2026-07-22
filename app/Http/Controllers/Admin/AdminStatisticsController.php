<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Models\Quiz;
use App\Models\QuizResult;
use App\Models\Group;
use App\Models\Blacklist;
use App\Models\User;
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

        $stats = Cache::remember($cacheKey, 120, function () use ($groupId) {
            return $this->computeStatistics($groupId);
        });

        // Read live on every request rather than out of the cached snapshot
        // above, so a post made just now shows up immediately instead of
        // waiting out the cache TTL. The "Posts Per Day" graph's today-bar
        // (the last entry, since the array runs oldest -> today) is backed
        // by the same query but was still coming from the cached snapshot -
        // overwrite it with this live count too so the two never disagree.
        $stats['postsToday'] = $this->postsTodayCount($groupId);

        if (!empty($stats['postsPerDay'])) {
            $todayIndex = array_key_last($stats['postsPerDay']);
            $stats['postsPerDay'][$todayIndex]['count'] = $stats['postsToday'];
        }

        // Same live-read treatment as postsToday above: Status flips the
        // instant a student posts/replies/messages (User::recordActivity) or
        // the sweep command reclassifies them, but that change was sitting
        // behind the 2-minute cache above - a student going Active right
        // after sending a message wouldn't show up here until the cache
        // expired, making the counters look stuck/wrong.
        $stats['activeStudents'] = User::where('Role', 'Student')->where('Status', 'Active')->count();
        $stats['inactiveStudents'] = User::where('Role', 'Student')->where('Status', 'Inactive')->count();

        // Group Participation Summary is read live rather than out of the
        // cached snapshot above, same reasoning as postsToday/activeStudents -
        // TotalStudents/AverageScore change the moment a student joins a
        // group or a lecturer grades a quiz, and this table is ranked by
        // AverageScore, so a stale ranking would visibly reorder itself once
        // the cache finally expired instead of reflecting what's true now.
        $stats['groupSummary'] = collect($this->batchGroupStats($groups))
            ->values()
            ->sortByDesc('AverageScore')
            ->values();

        return view('admin.statistics.index', array_merge($stats, compact('groups', 'groupId')));
    }

    private function computeStatistics($groupId): array
    {
        // ---- Posts Today ----
        // postsToday is deliberately NOT computed here - it's read live in
        // index() instead (this whole array gets cached for 2 minutes, which
        // would otherwise show a stale count).

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

        // ---- Top Students leaderboard ----
        // Ranked by each student's own Total Score - the same QuizResult.Score
        // sum shown on their own Quizzes screen ($scores->sum('Score') in
        // DiscussionHubPageController::quizzes()) - not forum participation,
        // and restricted to Role=Student so a lecturer/admin can never appear
        // on a "Top Students" list. A selected group scopes to quizzes
        // belonging to that group; with no group selected, sums each
        // student's score across every quiz they've taken.
        $topMembersQuery = QuizResult::join('User', 'User.UserID', '=', 'QuizResult.UserID')
            ->join('Quiz', 'Quiz.QuizID', '=', 'QuizResult.QuizID')
            ->where('User.Role', 'Student');

        if ($groupId) {
            $topMembersQuery->where('Quiz.GroupID', $groupId);
        }

        $topMembers = $topMembersQuery
            ->select(
                'User.UserID',
                'User.UserName',
                DB::raw('SUM(QuizResult.Score) as TotalScore')
            )
            ->groupBy('User.UserID', 'User.UserName')
            ->orderByDesc('TotalScore')
            ->limit(10)
            ->get();

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
        // groupSummary is deliberately NOT computed here - it's read live in
        // index() instead (this whole array gets cached for 2 minutes, which
        // would otherwise show a stale ranking).

        // ---- Account Status Overview ----
        // Reads User.Status, which the students:process-inactivity scheduled
        // command keeps in sync (Active/Inactive/Blacklisted) — distinct from
        // activeUsersToday above, which measures same-day posting activity.
        // activeStudents/inactiveStudents are deliberately NOT computed here -
        // they're read live in index() instead (same reason as postsToday
        // below), since this whole array gets cached for 2 minutes.
        $totalUsers = User::count();
        $totalStudents = User::where('Role', 'Student')->count();
        $totalLecturers = User::where('Role', 'Lecturer')->count();
        $totalAdmins = User::where('Role', 'Administrator')->count();

        return compact(
            'openQuestions', 'quizzesRun',
            'postsPerDay', 'topMembers', 'blacklistedCount',
            'totalUsers', 'totalStudents', 'totalLecturers', 'totalAdmins'
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

        // Total Students column - deliberately re-counted with a Role filter
        // rather than reusing $enrolledByGroup above, so it only ever
        // reflects real Role=Student accounts even if groupstudent ever
        // gained a non-student row.
        $studentsByGroup = DB::table('groupstudent')
            ->join('User', 'User.UserID', '=', 'groupstudent.UserID')
            ->where('User.Role', 'Student')
            ->whereIn('groupstudent.GroupID', $groupIds)
            ->selectRaw('groupstudent.GroupID as GroupID, COUNT(*) as total')
            ->groupBy('groupstudent.GroupID')
            ->pluck('total', 'GroupID');

        $scheduledQuizzes = Quiz::whereIn('GroupID', $groupIds)->where('Status', 'scheduled')->get(['QuizID', 'GroupID']);
        $quizIdsByGroup = $scheduledQuizzes->groupBy('GroupID')->map(fn ($rows) => $rows->pluck('QuizID'));

        $submissionsByQuiz = QuizResult::whereIn('QuizID', $scheduledQuizzes->pluck('QuizID'))
            ->selectRaw('QuizID, COUNT(*) as total')
            ->groupBy('QuizID')
            ->pluck('total', 'QuizID');

        // Average Score column - each student's own total score across every
        // quiz they've taken for this group (same Score column Top Students
        // above sums), summed then divided by the group's real Role=Student
        // headcount so a student who hasn't attempted anything yet still
        // pulls the average down to 0 rather than being excluded from it.
        $studentScoreTotalsByGroup = QuizResult::join('Quiz', 'Quiz.QuizID', '=', 'QuizResult.QuizID')
            ->join('User', 'User.UserID', '=', 'QuizResult.UserID')
            ->where('User.Role', 'Student')
            ->whereIn('Quiz.GroupID', $groupIds)
            ->selectRaw('Quiz.GroupID as GroupID, SUM(QuizResult.Score) as total')
            ->groupBy('Quiz.GroupID')
            ->pluck('total', 'GroupID');

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

            $totalStudents = $studentsByGroup[$gid] ?? 0;
            $averageScore = $totalStudents > 0
                ? round(($studentScoreTotalsByGroup[$gid] ?? 0) / $totalStudents, 2)
                : 0;

            $stats[$gid] = [
                'GroupName' => $group->GroupName,
                'TotalPosts' => $totalPostsByGroup[$gid] ?? 0,
                'ActiveUsers' => $activeUsers,
                'QuizCompletion' => $quizCompletion,
                'FlaggedContent' => $flaggedByGroup[$gid] ?? 0,
                'TotalStudents' => $totalStudents,
                'AverageScore' => $averageScore,
            ];
        }

        return $stats;
    }

    private function postsTodayCount($groupId): int
    {
        $query = Post::whereDate('CreatedAt', Carbon::today());

        if ($groupId) {
            $query->whereHas('topic', fn ($q) => $q->where('GroupID', $groupId));
        }

        return $query->count();
    }

}