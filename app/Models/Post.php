<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Post extends Model
{
    protected $table = 'Post';
    protected $primaryKey = 'PostID';
    protected $fillable = [
    'TopicID',
    'UserID',
    'Content',
    'ParentPostID',
    'Attachment',
    'AttachmentType',
    'IsFlagged',
    'FlaggedReason',
];
    const CREATED_AT = 'CreatedAt';
    const UPDATED_AT = 'UpdatedAt';

    protected $casts = ['IsFlagged' => 'boolean'];

    protected static function booted()
    {
        static::created(fn (Post $post) => User::recordActivity($post->UserID));
    }

    public function author()
    {
        return $this->belongsTo(User::class, 'UserID', 'UserID');
    }

    public function topic()
    {
        return $this->belongsTo(Topic::class, 'TopicID', 'TopicID');
    }

    public function replies()
    {
        return $this->hasMany(Reply::class, 'PostID', 'PostID');
    }

    public function parent()
    {
        return $this->belongsTo(Post::class, 'ParentPostID', 'PostID');
    }

    public function flags()
    {
        return $this->hasMany(PostFlag::class, 'PostID', 'PostID');
    }

    /**
     * Record a flag from this user and escalate IsFlagged once the
     * configured threshold of distinct flaggers is reached. Post.IsFlagged
     * is a derived cache of the post_flags count, not the source of truth.
     */
    public function flagBy(int $userId, ?string $reason = null): PostFlag
    {
        $flag = $this->flags()->firstOrCreate(
            ['FlaggedByUserID' => $userId],
            ['Reason' => $reason]
        );

        $threshold = (int) config('moderation.flag_escalation_threshold', 2);
        if ($this->flags()->count() >= $threshold && !$this->IsFlagged) {
            $this->update(['IsFlagged' => true, 'FlaggedReason' => $reason]);

            if ($this->topic?->GroupID) {
                app(\App\Services\ParticipationService::class)->recalculate($this->UserID, $this->topic->GroupID);
            }
        }

        return $flag;
    }
}
