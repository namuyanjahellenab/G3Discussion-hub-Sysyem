<?php

namespace App\Services;

use App\Models\Participation;
use App\Models\ParticipationCriteria;
use App\Models\Post;
use App\Models\Reply;

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
     * PostCount covers every Post row (topic-starting posts and group-chat
     * messages alike — both use the Post model). ReplyCount covers Reply
     * rows, i.e. actual Q&A answers in a topic thread.
     */
    public function recalculate(int $userId, int $groupId): Participation
    {
        $criteria = ParticipationCriteria::forGroup($groupId);

        $postCount = Post::where('UserID', $userId)
            ->where('IsFlagged', false)
            ->whereHas('topic', fn ($q) => $q->where('GroupID', $groupId))
            ->count();

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
            ['PostCount' => $postCount, 'ReplyCount' => $replyCount, 'ParticipationScore' => $score]
        );
    }
}
