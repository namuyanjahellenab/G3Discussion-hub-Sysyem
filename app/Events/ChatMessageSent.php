<?php

namespace App\Events;

use App\Models\Message;
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
            'created_at' => optional($this->message->CreatedAt)->format('Y-m-d H:i'),
            'is_spam' => (bool) $this->message->is_spam,
        ];
    }
}
