<?php

namespace App\Services;

use App\Models\Message;
use App\Models\Participation;
use App\Models\ParticipationCriteria;
use App\Models\Post;
use App\Models\Reply;
use App\Models\User;
use Illuminate\Support\Collection;

class ParticipationService
{
    /**
     * Recompute a user's participation score for a group from source data,
     * rather than incrementally patching a running total. This is what keeps
     * a flagged/archived post from keeping the points it earned before
     * moderation caught it — the count simply excludes IsFlagged rows every
     * time it's recalculated, instead of requiring an explicit "subtract N
     * points" step wherever content gets flagged or deleted.
     *
     * PostCount covers every Post row (topic-starting posts and replies-as-
     * posts) plus every group-chat Message sent in this group's own
     * conversations - despite living in a separate table/model today, a
     * chat message is still "posting" for participation-scoring purposes.
     * ReplyCount covers Reply rows, i.e. actual Q&A answers in a topic
     * thread.
     */
    public function recalculate(int $userId, int $groupId): Participation
    {
        $criteria = ParticipationCriteria::forGroup($groupId);

        // Counts whatever Post/Reply/Message rows for this user in this
        // group still exist in the database, full stop - no rejoin-based
        // cutoff. A row that's actually been deleted is simply absent from
        // these counts already; nothing further to exclude.
        $topicPostCount = Post::where('UserID', $userId)
            ->where('IsFlagged', false)
            ->whereHas('topic', fn ($q) => $q->where('GroupID', $groupId))
            ->count();

        $chatMessageCount = Message::where('user_id', $userId)
            ->where('is_spam', false)
            ->where('IsFlagged', false)
            ->whereHas('conversation', fn ($q) => $q->where('group_id', $groupId))
            ->count();

        $postCount = $topicPostCount + $chatMessageCount;

        $replyCount = Reply::where('UserID', $userId)
            ->where('IsFlagged', false)
            ->whereHas('post.topic', fn ($q) => $q->where('GroupID', $groupId))
            ->count();

        $acceptedCount = Reply::where('UserID', $userId)
            ->where('IsFlagged', false)
            ->where('IsAccepted', true)
            ->whereHas('post.topic', fn ($q) => $q->where('GroupID', $groupId))
            ->count();

        $score = ($postCount * $criteria->PointsPerPost)
            + ($replyCount * $criteria->PointsPerReply)
            + ($acceptedCount * $criteria->PointsPerAcceptedAnswer);

        return Participation::updateOrCreate(
            ['UserID' => $userId, 'GroupID' => $groupId],
            ['PostCount' => $postCount, 'ReplyCount' => $replyCount, 'AcceptedCount' => $acceptedCount, 'ParticipationScore' => $score]
        );
    }

    /**
     * The single highest raw ParticipationScore recorded anywhere on the
     * platform - any user, in any group. This is the "10" every group's
     * curve is measured against, deliberately global rather than per-group:
     * the intent is competitive pressure between groups (be the platform's
     * most active participant to top your own group's marks), not a curve
     * that's trivially easy to max out by being the only active person in
     * a quiet group.
     */
    public function globalMaxScore(): float
    {
        return (float) (Participation::max('ParticipationScore') ?? 0);
    }

    /**
     * A user's participation in every group they belong to, curved against
     * the platform-wide top scorer. Square-root rather than linear scaling
     * softens the effect of one outlier's raw score setting the ceiling -
     * a group of solidly-active students shouldn't get crushed toward 0
     * just because one person elsewhere posts 10x more than anyone else.
     */
    public function curvedScoresForUser(int $userId): Collection
    {
        $groups = User::findOrFail($userId)->groups()->get();
        $globalMax = $this->globalMaxScore();

        $participations = Participation::where('UserID', $userId)
            ->whereIn('GroupID', $groups->pluck('GroupID'))
            ->get()
            ->keyBy('GroupID');

        return $groups->map(function ($group) use ($participations, $globalMax) {
            $p = $participations->get($group->GroupID);
            $raw = (float) ($p->ParticipationScore ?? 0);
            $curved = $globalMax > 0
                ? min(10.0, round(10 * sqrt($raw / $globalMax), 1))
                : 0.0;

            return [
                'group_id' => $group->GroupID,
                'group_name' => $group->GroupName,
                'participation' => $curved,
                'raw_score' => $raw,
                'post_count' => $p->PostCount ?? 0,
                'reply_count' => $p->ReplyCount ?? 0,
                'accepted_count' => $p->AcceptedCount ?? 0,
            ];
        });
    }
}
