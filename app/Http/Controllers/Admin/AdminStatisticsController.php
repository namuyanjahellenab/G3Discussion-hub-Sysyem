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
        $stats['activeStudents'] = User::where('Role', 'Student')->where('Status', 'Active')
            ->when($groupId, fn ($q) => $q->whereHas('groupMemberships', fn ($q2) => $q2->where('GroupID', $groupId)))
            ->count();
        $stats['inactiveStudents'] = User::where('Role', 'Student')->where('Status', 'Inactive')
            ->when($groupId, fn ($q) => $q->whereHas('groupMemberships', fn ($q2) => $q2->where('GroupID', $groupId)))
            ->count();

        // Also read live, not cached - both are group-membership counts
        // (groupstudent), which must reflect a join/leave immediately rather
        // than up to 2 minutes late when a specific group is filtered.
        $stats['totalStudents'] = User::where('Role', 'Student')
            ->when($groupId, fn ($q) => $q->whereHas('groupMemberships', fn ($q2) => $q2->where('GroupID', $groupId)))
            ->count();
        $stats['totalLecturers'] = User::where('Role', 'Lecturer')
            ->when($groupId, fn ($q) => $q->whereHas('groupMemberships', fn ($q2) => $q2->where('GroupID', $groupId)))
            ->count();

        // Also live, not cached - a promoted lecturer toggling between their
        // Lecturer and Admin dashboards (DashboardController::switchToAdmin/
        // switchToLecturer) flips User.Role right away, and this count would
        // otherwise still show the pre-toggle number for up to 2 minutes.
        $stats['totalAdmins'] = User::where('Role', 'Administrator')->count();


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
            // Ties (most commonly a whole group still sitting at 0 marks)
            // fall back to alphabetical order instead of whatever order the
            // database happens to return them in.
            ->orderByDesc('TotalScore')
            ->orderBy('User.UserName')
            ->limit(5)
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
        $q->whereIn('UserID', DB::table('GroupStudent')->where('GroupID', $groupId)->pluck('UserID'));
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
        // activeStudents/inactiveStudents/totalStudents/totalLecturers/
        // totalAdmins are deliberately NOT computed here - they're read live
        // in index() instead (same reason as postsToday below), since this
        // whole array gets cached for 2 minutes.
        $totalUsers = User::count();

        return compact(
            'openQuestions', 'quizzesRun',
            'postsPerDay', 'topMembers', 'blacklistedCount',
            'totalUsers'
        );
    }

    public function export(Request $request)
    {
        $groupId = $request->get('group_id');
        $groups = $groupId ? Group::where('GroupID', $groupId)->get() : Group::all();

        // batchGroupStats() is a fresh, uncached set of queries (same as
        // index()'s $stats['groupSummary'], which is deliberately read live
        // rather than out of that method's 2-minute cache) - so this export
        // always reflects the database at the moment it's downloaded, not a
        // stale snapshot. Same columns and AverageScore-descending order as
        // the on-screen Group Participation Summary table, so the file
        // matches exactly what the admin is looking at.
        $rows = collect($this->batchGroupStats($groups))
            ->values()
            ->sortByDesc('AverageScore')
            ->values();

        $filename = 'group_participation_summary_' . now()->setTimezone('Africa/Kampala')->format('Y_m_d_His') . '.csv';
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"$filename\"",
        ];

        $callback = function () use ($rows) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, [
                'Group', 'Total Students', 'Active Students', 'Total Posts',
                'Students Participation', 'Quiz Participation', 'Average Score',
            ]);
            foreach ($rows as $row) {
                fputcsv($handle, [
                    $row['GroupName'],
                    $row['TotalStudents'],
                    $row['ActiveUsers'],
                    $row['TotalPosts'],
                    $row['StudentsParticipation'],
                    $row['QuizCompletion'] . '%',
                    number_format($row['AverageScore'], 2),
                ]);
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

        // Excludes posts from a user who has since left THIS post's group
        // (Studentsleft is keyed per UserID+GroupID, so a post they made in
        // a DIFFERENT group they haven't left still counts there) - their
        // post row itself is untouched in the Post table, just no longer
        // read into this group's real-time count.
        $totalPostsByGroup = DB::table('Post')
            ->join('Topic', 'Post.TopicID', '=', 'Topic.TopicID')
            ->whereIn('Topic.GroupID', $groupIds)
            ->whereNotExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('Studentsleft')
                    ->whereColumn('Studentsleft.UserID', 'Post.UserID')
                    ->whereColumn('Studentsleft.GroupID', 'Topic.GroupID');
            })
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

        // Total Students column, and the actual per-group student ID lists
        // QuizCompletion below intersects against - only ever real Role=
        // Student accounts, even if groupstudent ever gained a non-student
        // row.
        $studentIdsByGroup = DB::table('GroupStudent')
            ->join('User', 'User.UserID', '=', 'GroupStudent.UserID')
            ->where('User.Role', 'Student')
            ->whereIn('GroupStudent.GroupID', $groupIds)
            ->select('GroupStudent.GroupID as GroupID', 'GroupStudent.UserID as UserID')
            ->get()
            ->groupBy('GroupID')
            ->map(fn ($rows) => $rows->pluck('UserID'));

        $scheduledQuizzes = Quiz::whereIn('GroupID', $groupIds)->where('Status', 'scheduled')->get(['QuizID', 'GroupID']);
        $quizIdsByGroup = $scheduledQuizzes->groupBy('GroupID')->map(fn ($rows) => $rows->pluck('QuizID'));

        // Which students attempted each quiz - QuizCompletion below counts
        // DISTINCT students (Role=Student only, so a lecturer test-submitting
        // their own quiz doesn't inflate it) who attempted at least one of
        // the group's quizzes, not total submissions (a student who took 3
        // quizzes used to count as 3 "submissions" against a denominator
        // sized for 1 attempt each, which is how completion could exceed
        // 100%). Not intersected against groupstudent enrollment below -
        // QuizEngineController::join()/submit() never actually check group
        // membership before accepting a submission, so a real student
        // attempt for this group's quiz that the groupstudent table doesn't
        // (yet/anymore) reflect must still count, or completion reads as a
        // false 0%/"not calculated" despite a genuine attempt existing.
        $attemptedUsersByQuiz = QuizResult::join('User', 'User.UserID', '=', 'QuizResult.UserID')
            ->join('Quiz', 'Quiz.QuizID', '=', 'QuizResult.QuizID')
            ->where('User.Role', 'Student')
            ->whereIn('QuizResult.QuizID', $scheduledQuizzes->pluck('QuizID'))
            ->whereNotExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('Studentsleft')
                    ->whereColumn('Studentsleft.UserID', 'QuizResult.UserID')
                    ->whereColumn('Studentsleft.GroupID', 'Quiz.GroupID');
            })
            ->select('QuizResult.QuizID as QuizID', 'QuizResult.UserID as UserID')
            ->distinct()
            ->get()
            ->groupBy('QuizID')
            ->map(fn ($rows) => $rows->pluck('UserID'));

        // Average Score column - each student's own total score across every
        // quiz they've taken for this group (same Score column Top Students
        // above sums), summed then divided by the group's real Role=Student
        // headcount so a student who hasn't attempted anything yet still
        // pulls the average down to 0 rather than being excluded from it.
        $studentScoreTotalsByGroup = QuizResult::join('Quiz', 'Quiz.QuizID', '=', 'QuizResult.QuizID')
            ->join('User', 'User.UserID', '=', 'QuizResult.UserID')
            ->where('User.Role', 'Student')
            ->whereIn('Quiz.GroupID', $groupIds)
            ->whereNotExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('Studentsleft')
                    ->whereColumn('Studentsleft.UserID', 'QuizResult.UserID')
                    ->whereColumn('Studentsleft.GroupID', 'Quiz.GroupID');
            })
            ->selectRaw('Quiz.GroupID as GroupID, SUM(QuizResult.Score) as total')
            ->groupBy('Quiz.GroupID')
            ->pluck('total', 'GroupID');

        // Active Users column - was "ever posted or replied in this group,
        // all-time", a completely different (and effectively static, since it
        // only grows) definition of "active" than the Active Students /
        // Inactive Students cards above, which read the live User.Status kept
        // in sync by students:process-inactivity/User::recordActivity. Using
        // that same Status field here instead keeps this table's numbers from
        // ever contradicting the cards.
        $activeUsersByGroup = DB::table('GroupStudent')
            ->join('User', 'User.UserID', '=', 'GroupStudent.UserID')
            ->where('User.Role', 'Student')
            ->where('User.Status', 'Active')
            ->whereIn('GroupStudent.GroupID', $groupIds)
            ->selectRaw('GroupStudent.GroupID as GroupID, COUNT(*) as total')
            ->groupBy('GroupStudent.GroupID')
            ->pluck('total', 'GroupID');

        $stats = [];
        foreach ($groups as $group) {
            $gid = $group->GroupID;
            $enrolledStudentIds = $studentIdsByGroup[$gid] ?? collect();
            $totalStudents = $enrolledStudentIds->count();

            // Students Participation - raw count of distinct students who
            // attempted at least one of this group's quizzes (real-time,
            // from QuizResult; already deduplicated via ->unique(), so a
            // student who attempted several quizzes is still only counted
            // once). Capped at this group's own student count so an attempt
            // from someone no longer (or never) enrolled here can't push the
            // figure past the group's actual headcount.
            $quizIds = $quizIdsByGroup[$gid] ?? collect();
            $attemptedCount = min(
                $totalStudents,
                $quizIds->flatMap(fn ($qid) => $attemptedUsersByQuiz[$qid] ?? collect())->unique()->count()
            );

            // Quiz Participation - the average, per student, of how much of
            // this group's own quiz set they've each completed:
            //   1. fraction_i = quizzes THIS student answered / total quizzes
            //      sent to this group
            //   2. sum those fractions across every enrolled student
            //   3. (sum / total students) * 100
            // 0 students -> 0% (nothing to average). 0 quizzes, or students
            // with 0 attempts -> every fraction is 0 -> 0%. Every enrolled
            // student having answered every quiz -> every fraction is 1 ->
            // sum == totalStudents -> exactly 100%, true even for a single
            // student who's completed everything.
            $answeredCountByUser = [];
            foreach ($quizIds as $qid) {
                foreach (($attemptedUsersByQuiz[$qid] ?? collect()) as $uid) {
                    $answeredCountByUser[$uid] = ($answeredCountByUser[$uid] ?? 0) + 1;
                }
            }
            $totalQuizzes = $quizIds->count();
            $sumFractions = $totalQuizzes > 0
                ? $enrolledStudentIds->sum(fn ($uid) => ($answeredCountByUser[$uid] ?? 0) / $totalQuizzes)
                : 0;
            $quizParticipation = $totalStudents > 0
                ? round(($sumFractions / $totalStudents) * 100, 1)
                : 0;

            $activeUsers = $activeUsersByGroup[$gid] ?? 0;

            $averageScore = $totalStudents > 0
                ? round(($studentScoreTotalsByGroup[$gid] ?? 0) / $totalStudents, 2)
                : 0;

            $stats[$gid] = [
                'GroupName' => $group->GroupName,
                'TotalPosts' => $totalPostsByGroup[$gid] ?? 0,
                'ActiveUsers' => $activeUsers,
                'StudentsParticipation' => $attemptedCount,
                'QuizCompletion' => $quizParticipation,
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