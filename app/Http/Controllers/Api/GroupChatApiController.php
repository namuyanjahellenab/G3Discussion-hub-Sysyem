<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\ConversationExclusion;
use App\Models\ConversationMember;
use App\Models\GroupStudent;
use App\Models\Message;
use App\Services\GroupChatService;
use Illuminate\Http\Request;

// JSON equivalent of the web's Group Chat (GroupChatController) - full
// parity, including restricted/selective threads (a conversation that
// excludes specific group members). Message creation itself is delegated to
// GroupChatService so the same exclusion-resolution/moderation/broadcast
// logic is shared with the web controller and the offline sync-push path.
class GroupChatApiController extends Controller
{
    // Returns the main conversation, any restricted threads this user is in,
    // the full member list (for building an exclude picker), and the
    // messages for whichever conversation is being viewed.
    public function index(Request $request, int $groupId)
    {
        $userId = $request->user()->UserID;

        abort_unless(
            GroupStudent::where('GroupID', $groupId)->where('UserID', $userId)->exists(),
            403,
            'Not a member of this group'
        );

        $mainConversation = Conversation::firstOrCreate(
            ['group_id' => $groupId, 'Type' => 'group'],
            ['CreatedBy' => $userId]
        );

        ConversationMember::firstOrCreate([
            'ConversationID' => $mainConversation->ConversationID,
            'UserID' => $userId,
        ], ['JoinedAt' => now()]);

        $restrictedThreads = Conversation::where('group_id', $groupId)
            ->where('Type', 'restricted')
            ->whereHas('members', fn ($q) => $q->where('UserID', $userId))
            ->get();

        $conversationId = $request->query('conversation_id');

        if ($conversationId) {
            $activeConversation = Conversation::where('ConversationID', $conversationId)
                ->where('group_id', $groupId)
                ->firstOrFail();

            abort_unless(
                ConversationMember::where('ConversationID', $activeConversation->ConversationID)
                    ->where('UserID', $userId)
                    ->exists(),
                403,
                'You do not have access to this conversation'
            );
        } else {
            $activeConversation = $mainConversation;
        }

        $messages = Message::where('ConversationID', $activeConversation->ConversationID)
            ->orderBy('CreatedAt')
            ->with('user')
            ->get();

        $groupMembers = GroupStudent::where('GroupID', $groupId)
            ->where('Status', 'active')
            ->where('UserID', '!=', $userId)
            ->with('user')
            ->get();

        return response()->json([
            'main_conversation_id' => $mainConversation->ConversationID,
            'active_conversation_id' => $activeConversation->ConversationID,
            'is_restricted' => $activeConversation->Type === 'restricted',
            'restricted_threads' => $restrictedThreads->map(fn ($c) => [
                'id' => $c->ConversationID,
                'excluded_names' => ConversationExclusion::where('ConversationID', $c->ConversationID)
                    ->with('user')->get()->pluck('user.UserName')->filter()->values(),
            ])->values(),
            'group_members' => $groupMembers->map(fn ($gs) => [
                'id' => $gs->UserID,
                'name' => $gs->user->UserName ?? 'Unknown',
            ])->values(),
            'messages' => $messages->map(fn ($m) => [
                'id' => $m->MessageID,
                'user_id' => $m->user_id,
                'author_name' => $m->user->UserName ?? 'Unknown',
                'body' => $m->body,
                'created_at' => optional($m->CreatedAt)->format('Y-m-d H:i'),
                'is_spam' => (bool) $m->is_spam,
            ]),
        ]);
    }

    public function store(Request $request, int $groupId)
    {
        $request->validate([
            'body' => 'required|string|max:2000',
            'exclude' => 'array',
            'exclude.*' => 'exists:User,UserID',
            'conversation_id' => 'nullable|exists:conversation,ConversationID',
        ]);

        $message = app(GroupChatService::class)->send(
            $groupId,
            $request->user()->UserID,
            $request->input('body'),
            $request->input('exclude', []),
            $request->input('conversation_id')
        );

        return response()->json([
            'id' => $message->MessageID,
            'user_id' => $message->user_id,
            'author_name' => $message->user->UserName ?? $request->user()->UserName,
            'body' => $message->body,
            'created_at' => optional($message->CreatedAt)->format('Y-m-d H:i'),
            'conversation_id' => $message->ConversationID,
        ], 201);
    }
}
