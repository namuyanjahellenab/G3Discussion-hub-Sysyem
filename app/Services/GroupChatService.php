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
        ?string $attachmentType = null
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

        $message = Message::create([
            'ConversationID' => $conversation->ConversationID,
            'TopicID' => null,
            'user_id' => $userId,
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

    private function findOrCreateRestrictedConversation(int $groupId, int $senderId, Collection $excludeIds): Conversation
    {
        $candidates = Conversation::where('group_id', $groupId)
            ->where('Type', 'restricted')
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

        $activeMembers = GroupStudent::where('GroupID', $groupId)
            ->where('Status', 'active')
            ->pluck('UserID');

        foreach ($activeMembers as $memberId) {
            if (!$excludeIds->contains($memberId)) {
                ConversationMember::create([
                    'ConversationID' => $conversation->ConversationID,
                    'UserID' => $memberId,
                    'JoinedAt' => now(),
                ]);
            }
        }

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
