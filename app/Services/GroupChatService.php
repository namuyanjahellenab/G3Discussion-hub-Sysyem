<?php

namespace App\Services;

use App\Events\ChatMessageSent;
use App\Models\Conversation;
use App\Models\ConversationExclusion;
use App\Models\ConversationMember;
use App\Models\GroupStudent;
use App\Models\Message;
use Illuminate\Support\Collection;

// Single source of truth for "create a group chat message", used by the web
// controller, the desktop REST endpoint, and the offline sync-push handler
// (a message queued while offline goes through this exact same path once
// connectivity returns - same exclusion resolution, same moderation, same
// broadcast - rather than a fourth copy of this logic).
class GroupChatService
{
    public function send(
        int $groupId,
        int $userId,
        string $body,
        array $excludeIds = [],
        ?int $conversationId = null,
        ?string $attachmentPath = null,
        ?string $attachmentType = null,
        ?int $parentMessageId = null
    ): Message {
        abort_unless(
            GroupStudent::where('GroupID', $groupId)->where('UserID', $userId)->exists(),
            403,
            'Not a member of this group'
        );

        if ($conversationId) {
            $conversation = Conversation::where('ConversationID', $conversationId)
                ->where('group_id', $groupId)
                ->firstOrFail();

            abort_unless(
                ConversationMember::where('ConversationID', $conversation->ConversationID)
                    ->where('UserID', $userId)
                    ->exists(),
                403
            );
        } else {
            $sortedExcludeIds = collect($excludeIds)->map(fn ($id) => (int) $id)->sort()->values();

            $conversation = $sortedExcludeIds->isEmpty()
                ? Conversation::firstOrCreate(['group_id' => $groupId, 'Type' => 'group'], ['CreatedBy' => $userId])
                : $this->findOrCreateRestrictedConversation($groupId, $userId, $sortedExcludeIds);
        }

        $isSpam = app(MlGatewayClient::class)->isSpam($body);

        // The tagged message must belong to this same conversation - a stale
        // reference (e.g. the message got deleted, or belongs to a different
        // thread) is dropped rather than blocking the send outright.
        if ($parentMessageId !== null && !Message::where('MessageID', $parentMessageId)
                ->where('ConversationID', $conversation->ConversationID)
                ->exists()) {
            $parentMessageId = null;
        }

        $message = Message::create([
            'ConversationID' => $conversation->ConversationID,
            'TopicID' => null,
            'user_id' => $userId,
            'ParentMessageID' => $parentMessageId,
            'body' => $body,
            'is_spam' => $isSpam,
            'Attachment' => $attachmentPath,
            'AttachmentType' => $attachmentType,
        ]);

        $message->load('user');

        // The message is already durably saved above - a live-push failure
        // (e.g. the Reverb server isn't running, as in local dev without it
        // started) must not fail the whole send. Without this try/catch,
        // ShouldBroadcastNow's synchronous broadcast() throws straight
        // through to the controller, so the client sees an error and the
        // sender's own message never appears until they refresh the page,
        // even though it was already in the database the whole time.
        // Spam is stored (so the admin moderation queue has something to
        // review) but never broadcast live - other members currently
        // viewing this conversation must not see it appear at all, or the
        // whole point of holding it back until an admin clears it is
        // defeated the instant it's sent.
        if (!$isSpam) {
            try {
                broadcast(new ChatMessageSent($message));
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return $message;
    }

    // A restricted thread is private to whoever set up the exclusion - only
    // its creator ever becomes a member, so nobody else (whether excluded or
    // simply not the one who restricted anyone) can see it exists, read it,
    // or learn who was excluded from it. Matching an existing thread is
    // scoped to this same sender for that reason - otherwise a second
    // student picking the identical exclude list would silently be folded
    // into the first student's private thread instead of getting their own.
    private function findOrCreateRestrictedConversation(int $groupId, int $senderId, Collection $excludeIds): Conversation
    {
        $candidates = Conversation::where('group_id', $groupId)
            ->where('Type', 'restricted')
            ->where('CreatedBy', $senderId)
            ->get();

        foreach ($candidates as $candidate) {
            $existingExcluded = ConversationExclusion::where('ConversationID', $candidate->ConversationID)
                ->pluck('UserID')->sort()->values();

            if ($existingExcluded->toArray() === $excludeIds->toArray()) {
                return $candidate;
            }
        }

        $conversation = Conversation::create([
            'Type' => 'restricted',
            'CreatedBy' => $senderId,
            'group_id' => $groupId,
        ]);

        ConversationMember::create([
            'ConversationID' => $conversation->ConversationID,
            'UserID' => $senderId,
            'JoinedAt' => now(),
        ]);

        foreach ($excludeIds as $excludedUserId) {
            ConversationExclusion::create([
                'ConversationID' => $conversation->ConversationID,
                'UserID' => $excludedUserId,
                'ExcludedBy' => $senderId,
            ]);
        }

        return $conversation;
    }
}
