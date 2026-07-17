<?php

use App\Models\ConversationMember;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// Only actual members of the conversation may subscribe - an excluded
// user's client can't receive the broadcast even if it knew the channel
// name, mirroring the membership check on the REST GET endpoint.
Broadcast::channel('conversation.{conversationId}', function ($user, $conversationId) {
    return ConversationMember::where('ConversationID', $conversationId)
        ->where('UserID', $user->UserID)
        ->exists();
});
