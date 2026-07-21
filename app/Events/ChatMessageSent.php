<?php

namespace App\Events;

use App\Models\Message;
use App\Services\AttachmentUploader;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Queue\SerializesModels;

// Fired the moment a chat message is created (whether via the live REST
// endpoint or an offline device's queued sync push) so every other member
// currently viewing this conversation sees it appear immediately. Broadcasts
// synchronously (ShouldBroadcastNow) rather than through the queue - this
// project has no queue worker running, and a chat message with no visible
// live update defeats the point.
class ChatMessageSent implements ShouldBroadcastNow
{
    use SerializesModels;

    public function __construct(public Message $message)
    {
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel('conversation.' . $this->message->ConversationID)];
    }

    public function broadcastAs(): string
    {
        return 'message.sent';
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->message->MessageID,
            'conversation_id' => $this->message->ConversationID,
            'user_id' => $this->message->user_id,
            'author_name' => $this->message->user->UserName ?? 'Unknown',
            'body' => $this->message->body,
            'attachment_url' => $this->message->Attachment
                ? route('student.messages.attachment', $this->message->MessageID)
                : null,
            'attachment_name' => $this->message->Attachment
                ? AttachmentUploader::displayName($this->message->Attachment)
                : null,
            // Human-friendly for immediate display (matches the sender's own
            // optimistically-appended bubble, which already uses
            // diffForHumans() via chat-bubble.blade.php) - a raw
            // "2026-07-19 14:30" here made every OTHER member's live-received
            // bubble look inconsistent with the sender's own.
            'created_at' => optional($this->message->CreatedAt)->diffForHumans(),
            // Raw ISO instant alongside it, for the desktop client to cache
            // and re-derive an accurate relative time from later (a frozen
            // "2 minutes ago" baked into the cache would go stale/wrong the
            // next time it's rendered offline).
            'created_at_iso' => $this->message->CreatedAt,
            'is_spam' => (bool) $this->message->is_spam,
        ];
    }
}
