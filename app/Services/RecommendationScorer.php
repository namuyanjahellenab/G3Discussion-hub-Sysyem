<?php

namespace App\Services;

use App\Models\Post;
use App\Models\Topic;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

// Blends text relevance (from the ML gateway's TF-IDF ranking) with real
// engagement - how many replies a topic has and how many distinct people
// contributed - so recommendations favor topics that are actually "vibing"
// with discussion, not just ones that happen to share vocabulary with the
// user's interests. Used by both the web (DiscussionHubPageController) and
// desktop (StudentDataApiController) recommendation endpoints so the two
// stay identical.
class RecommendationScorer
{
    private const TEXT_WEIGHT = 0.7;
    private const ENGAGEMENT_WEIGHT = 0.3;
    private const REPLY_COUNT_WEIGHT = 0.6;
    private const DISTINCT_REPLIERS_WEIGHT = 0.4;

    /**
     * The "activity" signal used to rank topics against: a user's own
     * recent posts. Falls back to the group's most recently created topic
     * titles when the user hasn't posted anything yet, so a brand new
     * member still gets a real (if coarser) activity signal instead of an
     * empty query that would make every topic score on category alone.
     * Shared here specifically so the web and desktop recommendation
     * endpoints can't quietly diverge the way they did before this existed
     * (desktop was missing this fallback entirely).
     */
    public function buildRecentMessages(int $userId, Collection $groupIds): array
    {
        $recentMessages = Post::where('UserID', $userId)
            ->latest('CreatedAt')
            ->limit(5)
            ->pluck('Content')
            ->filter()
            ->values()
            ->all();

        if (empty($recentMessages)) {
            $recentMessages = Topic::whereIn('GroupID', $groupIds)
                ->latest('CreatedAt')
                ->limit(5)
                ->pluck('Title')
                ->filter()
                ->values()
                ->all();
        }

        return $recentMessages;
    }

    /**
     * @param Collection $candidateTopics Topic models to score.
     * @param Collection $textScores TopicID => raw 0-1 cosine similarity from the ML gateway.
     * @return Collection Topics with ->relevance_score (0-1), ->reply_count,
     *                     ->distinct_repliers set, sorted best-first.
     */
    public function score(Collection $candidateTopics, Collection $textScores): Collection
    {
        $topicIds = $candidateTopics->pluck('TopicID');

        $replyStats = DB::table('reply')
            ->join('post', 'reply.PostID', '=', 'post.PostID')
            ->whereIn('post.TopicID', $topicIds)
            ->select(
                'post.TopicID',
                DB::raw('COUNT(*) as reply_count'),
                DB::raw('COUNT(DISTINCT reply.UserID) as distinct_repliers')
            )
            ->groupBy('post.TopicID')
            ->get()
            ->keyBy('TopicID');

        $maxReplyCount = max(1, (int) ($replyStats->max('reply_count') ?? 0));
        $maxRepliers = max(1, (int) ($replyStats->max('distinct_repliers') ?? 0));

        return $candidateTopics->map(function ($topic) use ($textScores, $replyStats, $maxReplyCount, $maxRepliers) {
            $textScore = (float) $textScores->get($topic->TopicID, 0);
            $stats = $replyStats->get($topic->TopicID);
            $replyCount = $stats->reply_count ?? 0;
            $distinctRepliers = $stats->distinct_repliers ?? 0;

            $engagementScore = (self::REPLY_COUNT_WEIGHT * ($replyCount / $maxReplyCount))
                + (self::DISTINCT_REPLIERS_WEIGHT * ($distinctRepliers / $maxRepliers));

            $topic->relevance_score = (self::TEXT_WEIGHT * $textScore) + (self::ENGAGEMENT_WEIGHT * $engagementScore);
            $topic->reply_count = $replyCount;
            $topic->distinct_repliers = $distinctRepliers;

            return $topic;
        })->sortByDesc('relevance_score')->values();
    }
}
