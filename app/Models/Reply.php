<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Reply extends Model
{
    protected $table = 'Reply';
    protected $primaryKey = 'ReplyID';
    const CREATED_AT = 'CreatedAt';
    const UPDATED_AT = 'UpdatedAt';
    protected $fillable = ['PostID', 'UserID', 'ReplyContent', 'IsAccepted', 'IsFlagged', 'ParentReplyID', 'Attachment', 'AttachmentType'];
    protected $casts = ['IsAccepted' => 'boolean', 'IsFlagged' => 'boolean'];

    public function author()
    {
        return $this->belongsTo(User::class, 'UserID', 'UserID');
    }

    public function post()
    {
        return $this->belongsTo(Post::class, 'PostID', 'PostID');
    }

    public function parentReply()
    {
        return $this->belongsTo(Reply::class, 'ParentReplyID', 'ReplyID');
    }

    public function flags()
    {
        return $this->hasMany(ReplyFlag::class, 'ReplyID', 'ReplyID');
    }

    /**
     * Record a flag from this user and escalate IsFlagged once the
     * configured threshold of distinct flaggers is reached. Mirrors
     * Post::flagBy() so replies get the same moderation-queue treatment.
     */
    public function flagBy(int $userId, ?string $reason = null): ReplyFlag
    {
        $flag = $this->flags()->firstOrCreate(
            ['FlaggedByUserID' => $userId],
            ['Reason' => $reason]
        );

        $threshold = (int) config('moderation.flag_escalation_threshold', 2);
        if ($this->flags()->count() >= $threshold && !$this->IsFlagged) {
            $this->update(['IsFlagged' => true]);

            $groupId = $this->post?->topic?->GroupID;
            if ($groupId) {
                app(\App\Services\ParticipationService::class)->recalculate($this->UserID, $groupId);
            }
        }

        return $flag;
    }
}
