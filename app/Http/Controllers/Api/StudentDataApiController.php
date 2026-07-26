<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Notification;
use App\Models\Quiz;
use App\Models\QuizResult;
use App\Models\ReadState;
use App\Models\Topic;
use App\Services\MarksService;
use App\Services\MlGatewayClient;
use App\Services\ParticipationService;
use App\Services\RecommendationScorer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

// JSON equivalents of the "My Questions", "Marks", "Recommend", and "Quizzes"
// pages the web app already has, for the desktop client's sidebar. Each
// method mirrors the query logic of its web counterpart in
// DiscussionHubPageController/DashboardController without touching those
// classes, so the web pages can't regress from this addition.
class StudentDataApiController extends Controller
{
    // Mirrors DiscussionHubPageController::forum() - the "Topic Discussions"
    // landing page: joined groups (each opens into the group's topic list)
    // plus the 5 most recent topics across all of them.
    public function forum(Request $request)
    {
        $user = $request->user();
        $joinedGroups = $user->groups()->withCount(['students as member_count'])->get();
        $groupIds = $joinedGroups->pluck('GroupID');

        $totalTopicsCount = Topic::whereIn('GroupID', $groupIds)->visibleTo($user->UserID)->count();

        $topics = Topic::whereIn('GroupID', $groupIds)
            ->visibleTo($user->UserID)
            ->with(['creator', 'classification'])
            ->latest('CreatedAt')
            ->limit(5)
            ->get();

        return response()->json([
            'joined_groups' => $joinedGroups->map(fn ($g) => [
                'id' => $g->GroupID,
                'name' => $g->GroupName,
                'description' => $g->Description,
                'member_count' => $g->member_count,
            ])->values(),
            'total_topics_count' => $totalTopicsCount,
            'latest_topics' => $topics->map(fn ($t) => [
                'id' => $t->TopicID,
                'title' => $t->Title,
                'creator_name' => $t->creator->UserName ?? 'a member',
                'category' => $t->classification->PredictedCategory ?? $t->Category,
                'time' => $t->CreatedAt->diffForHumans(),
            ])->values(),
        ]);
    }

    // Mirrors DiscussionHubPageController::pollNotifications() exactly (same
    // shape: unread_count + latest 10) so the desktop sidebar's bell can poll
    // this from any screen instead of re-fetching the whole dashboard payload
    // just for its notification count.
    public function pollNotifications(Request $request)
    {
        $userId = $request->user()->UserID;

        $unreadCount = Notification::where('UserID', $userId)
            ->where('Status', false)
            ->count();

        $latest = Notification::where('UserID', $userId)
            ->orderByDesc('CreatedAt')
            ->take(10)
            ->get()
            ->map(fn ($n) => [
                'id' => $n->NotificationID,
                'message' => $n->Message,
                'type' => $n->Type,
                'status' => $n->Status,
                'time' => $n->CreatedAt->diffForHumans(),
            ]);

        return response()->json([
            'success' => true,
            'unread_count' => $unreadCount,
            'notifications' => $latest,
        ]);
    }

    public function markNotificationRead(Request $request, Notification $notification)
    {
        abort_unless($notification->UserID === $request->user()->UserID, 403);
        $notification->update(['Status' => true]);

        return response()->json(['success' => true]);
    }

    public function markAllNotificationsRead(Request $request)
    {
        Notification::where('UserID', $request->user()->UserID)
            ->where('Status', false)
            ->update(['Status' => true]);

        return response()->json(['success' => true]);
    }

    public function myQuestions(Request $request)
    {
        $userId = $request->user()->UserID;

        $hasUnreadReplyNotification = Notification::where('UserID', $userId)
            ->where('Type', 'Reply')
            ->where('Status', false)
            ->exists();

        $myTopics = Topic::where('CreatedBy', $userId)
            ->with(['group', 'mainPost', 'replies' => fn ($q) => $q->where('IsAccepted', true)->with('author')])
            ->withCount('replies')
            ->latest('CreatedAt')
            ->paginate(20)
            ->through(function ($topic) use ($hasUnreadReplyNotification) {
                $acceptedReply = $topic->replies->first();

                return [
                    'id' => $topic->TopicID,
                    // The actual question text (the opening post's content),
                    // not the topic title - mirrors topics/my-questions.blade.php
                    // on the web side, which shows the same thing.
                    'title' => $topic->mainPost?->Content ?: $topic->Title,
                    'group_name' => $topic->group->GroupName ?? null,
                    'reply_count' => $topic->replies_count,
                    'has_unread_answer' => $hasUnreadReplyNotification && $topic->replies_count > 0,
                    'accepted_reply' => $acceptedReply ? [
                        'content' => $acceptedReply->ReplyContent,
                        'author_name' => $acceptedReply->author->UserName ?? 'Unknown',
                    ] : null,
                    'created_at' => optional($topic->CreatedAt)->setTimezone('Africa/Kampala')->format('Y-m-d H:i'),
                ];
            });

        return response()->json($myTopics);
    }

    // Single-number Dashboard summary tile - the full per-group breakdown
    // lives on the Marks page (see marks() below, via MarksService). This
    // averages each group's curved participation (ParticipationService)
    // into one figure, since a summary tile has room for one number, not a
    // per-group list.
    private function participationSnapshot(int $userId): array
    {
        $curvedScores = app(ParticipationService::class)->curvedScoresForUser($userId);
        $participationScore = $curvedScores->isNotEmpty()
            ? round($curvedScores->avg('participation'), 1)
            : 0.0;

        $quizAverage = DB::table('QuizResult')->where('UserID', $userId)->avg('Score');

        return [
            'participation' => $participationScore,
            'quiz_average' => $quizAverage ? round($quizAverage, 1) : null,
        ];
    }

    // Mirrors DiscussionHubPageController::marks() on the web side - one
    // row per group the student belongs to, via the same shared
    // MarksService, so the two platforms can't drift the way the old
    // duplicated hardcoded formula did.
    public function marks(Request $request)
    {
        $groups = app(MarksService::class)->marksBreakdownForUser($request->user()->UserID);

        return response()->json(['groups' => $groups]);
    }

    // Mirrors DashboardController::index() on the web side - joined groups,
    // notifications + unread count, participation/quiz snapshot, nearest
    // upcoming quiz, and the latest non-excluded announcement.
    public function dashboard(Request $request)
    {
        $user = $request->user();
        $joinedGroups = $user->groups()->withCount(['students as member_count'])->get();
        $groupIds = $joinedGroups->pluck('GroupID');

        $notifications = Notification::where('UserID', $user->UserID)
            ->orderBy('Status')->latest('CreatedAt')->get();

        $upcomingQuiz = Quiz::whereIn('GroupID', $groupIds)
            ->where('Status', 'scheduled')
            ->where('StartTime', '>', now())
            ->orderBy('StartTime')
            ->first();

        $latestAnnouncement = Announcement::whereIn('GroupID', $groupIds)
            ->whereDoesntHave('exclusions', fn ($q) => $q->where('UserID', $user->UserID))
            ->with('group')
            ->latest('CreatedAt')
            ->first();

        // Mirrors GroupChatController::index()'s $groupUnreadCounts - lets
        // the desktop group-switcher badge other groups' unread counts too,
        // not just threads within whichever group is currently open (its
        // local SQLite cache has no way to know that on its own for a group
        // it hasn't opened yet).
        $mainConversationsByGroup = Conversation::whereIn('group_id', $groupIds)
            ->where('Type', 'group')
            ->pluck('ConversationID', 'group_id');

        $lastReadByConversation = ReadState::where('UserID', $user->UserID)
            ->where('EntityType', 'Conversation')
            ->whereIn('EntityID', $mainConversationsByGroup->values())
            ->pluck('LastReadItemId', 'EntityID');

        $groupUnreadCounts = [];
        foreach ($mainConversationsByGroup as $gid => $convId) {
            $lastRead = $lastReadByConversation[$convId] ?? 0;
            $groupUnreadCounts[$gid] = Message::where('ConversationID', $convId)
                ->where('is_spam', false)
                ->where('MessageID', '>', $lastRead)
                ->count();
        }

        return response()->json([
            'joined_groups' => $joinedGroups->map(fn ($g) => [
                'id' => $g->GroupID,
                'name' => $g->GroupName,
                'member_count' => $g->member_count,
                'unread_count' => $groupUnreadCounts[$g->GroupID] ?? 0,
            ])->values(),
            'notifications_count' => $notifications->where('Status', false)->count(),
            'notifications' => $notifications->take(6)->map(fn ($n) => [
                'id' => $n->NotificationID,
                'type' => $n->Type,
                'message' => $n->Message,
                'time' => $n->CreatedAt->diffForHumans(),
                'is_read' => (bool) $n->Status,
            ])->values(),
            'snapshot' => $this->participationSnapshot($user->UserID),
            'upcoming_quiz' => $upcomingQuiz ? [
                'title' => $upcomingQuiz->Title,
                'start_time' => $upcomingQuiz->StartTime->setTimezone('Africa/Kampala')->format('d M Y, h:i A'),
                'duration_minutes' => $upcomingQuiz->Duration,
            ] : null,
            'latest_announcement' => $latestAnnouncement ? [
                'message' => $latestAnnouncement->Message,
                'group_name' => $latestAnnouncement->group->GroupName ?? null,
            ] : null,
        ]);
    }

    public function recommend(Request $request)
    {
        $user = $request->user();
        $groupIds = $user->groups()->pluck('Group.GroupID');

        $interests = Topic::whereIn('GroupID', $groupIds)
            ->whereNotNull('Category')->distinct()->pluck('Category')->filter()->values()->all();

        $recentMessages = app(RecommendationScorer::class)->buildRecentMessages($user->UserID, $groupIds);

        $candidateTopics = Topic::whereIn('GroupID', $groupIds)
            ->where('CreatedBy', '!=', $user->UserID)
            ->visibleTo($user->UserID)
            ->withCount('posts')
            ->with(['creator', 'group'])
            ->get();

        $topicPayload = $candidateTopics->map(fn ($topic) => [
            'TopicID' => $topic->TopicID,
            'Title' => $topic->Title,
            'Category' => $topic->Category,
        ])->all();

        $ranked = $topicPayload !== []
            ? app(MlGatewayClient::class)->recommendTopics($user->UserID, $interests, $recentMessages, $topicPayload)
            : null;
        $topicScores = collect($ranked['TopicScores'] ?? [])->pluck('RelevanceScore', 'TopicID');

        // Same blended text-relevance + engagement scoring as the web
        // (RecommendationScorer) so a topic that's actually "vibing" with
        // discussion outranks one that only shares vocabulary with the
        // user's interests, on both platforms identically.
        $topics = app(RecommendationScorer::class)->score($candidateTopics, $topicScores)->take(10);

        // Was a bare array (just the topics) - the desktop Recommend screen
        // used to be the generic simple-list-view.fxml card list, which
        // never showed the interest tags row the web page has. Wrapping the
        // response instead of adding a second endpoint, since $interests was
        // already computed above for the ML ranking call and simply never
        // returned.
        return response()->json([
            'interests' => $interests,
            'topics' => $topics->map(fn ($topic) => [
                'id' => $topic->TopicID,
                'title' => $topic->Title,
                'category' => $topic->Category,
                'relevance_score' => round($topic->relevance_score * 100, 2),
                'creator_name' => $topic->creator->UserName ?? 'a member',
                'group_name' => $topic->group->GroupName ?? 'General',
                'created_at' => optional($topic->CreatedAt)->diffForHumans(),
            ])->values(),
        ]);
    }

    public function quizzes(Request $request)
    {
        $userId = $request->user()->UserID;
        $now = now();

        $allQuizzes = Quiz::where('Status', 'scheduled')->orderByDesc('StartTime')->get();
        $completed = QuizResult::where('UserID', $userId)->with('quiz')->latest('SubmissionTime')->get();
        $completedQuizIds = $completed->pluck('QuizID')->unique();

        $available = collect();
        $upcoming = collect();
        $missed = collect();

        foreach ($allQuizzes as $quiz) {
            if ($completedQuizIds->contains($quiz->QuizID)) {
                continue;
            }

            $end = $quiz->StartTime->copy()->addMinutes($quiz->Duration);

            if ($quiz->StartTime > $now) {
                $upcoming->push($quiz);
            } elseif ($now <= $end) {
                $available->push($quiz);
            } else {
                $missed->push($quiz);
            }
        }

        $shape = fn ($q) => [
            'id' => $q->QuizID,
            'title' => $q->Title,
            'start_time' => optional($q->StartTime)->setTimezone('Africa/Kampala')->format('Y-m-d H:i'),
            'duration_minutes' => $q->Duration,
            'starts_in' => optional($q->StartTime)->diffForHumans(),
            'closed_ago' => $q->StartTime->copy()->addMinutes($q->Duration)->diffForHumans(),
        ];

        return response()->json([
            'available' => $available->map($shape)->values(),
            'upcoming' => $upcoming->map($shape)->values(),
            'missed' => $missed->map($shape)->values(),
            'completed' => $completed->map(fn ($r) => [
                'quiz_id' => $r->QuizID,
                'title' => $r->quiz->Title ?? 'Unknown quiz',
                'score' => $r->Score,
                'submitted_at' => optional($r->SubmissionTime)->setTimezone('Africa/Kampala')->format('Y-m-d H:i'),
                'submitted_ago' => optional($r->SubmissionTime)->diffForHumans(),
            ])->values(),
            'total_score' => $completed->sum('Score'),
        ]);
    }
}
