<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Message extends Model
{
 const CREATED_AT = 'CreatedAt';
    const UPDATED_AT = 'UpdatedAt';

   protected $table = 'Message';
protected $primaryKey = 'MessageID';

protected $fillable = ['TopicID', 'user_id', 'ConversationID', 'ParentMessageID', 'body', 'is_spam', 'IsFlagged', 'FlaggedReason', 'Attachment', 'AttachmentType'];

    protected $casts = ['is_spam' => 'boolean', 'IsFlagged' => 'boolean'];

    protected static function booted()
    {
        static::created(fn (Message $message) => User::recordActivity($message->user_id));
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'UserID');
    }
public function topic()
{
    return $this->belongsTo(Topic::class);
}

    public function conversation()
{
    return $this->belongsTo(Conversation::class, 'ConversationID', 'ConversationID');
}

public function parentMessage()
{
    return $this->belongsTo(Message::class, 'ParentMessageID');
}

public function replies()
{
    return $this->hasMany(Message::class, 'ParentMessageID');
}

public function flags()
{
    return $this->hasMany(MessageFlag::class, 'MessageID', 'MessageID');
}

/**
 * Record a flag from this user and escalate IsFlagged once the configured
 * threshold of distinct flaggers is reached - mirrors Post::flagBy()/
 * Reply::flagBy() so a manually-reported message gets the same moderation-
 * queue treatment as a spam-detected one.
 */
public function flagBy(int $userId, ?string $reason = null): MessageFlag
{
    $flag = $this->flags()->firstOrCreate(
        ['FlaggedByUserID' => $userId],
        ['Reason' => $reason]
    );

    $threshold = (int) config('moderation.flag_escalation_threshold', 2);
    if ($this->flags()->count() >= $threshold && !$this->IsFlagged) {
        $this->update(['IsFlagged' => true, 'FlaggedReason' => $reason]);
    }

    return $flag;
}
}
