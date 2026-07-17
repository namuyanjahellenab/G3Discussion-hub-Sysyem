<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\ConversationMember;
use App\Models\GroupStudent;
use App\Models\Message;
use App\Services\GroupChatService;
use Illuminate\Http\Request;

class GroupChatController extends Controller
{
    public function index($groupId, $conversationId = null)
    {
        $userId = auth()->id();

        // Ensure a main group-wide conversation exists
        $mainConversation = Conversation::firstOrCreate(
            ['group_id' => $groupId, 'Type' => 'group'],
            ['CreatedBy' => $userId]
        );

        ConversationMember::firstOrCreate([
            'ConversationID' => $mainConversation->ConversationID,
            'UserID' => $userId,
        ], ['JoinedAt' => now()]);

        // Any restricted threads this user belongs to, in this group
        $restrictedThreads = Conversation::where('group_id', $groupId)
            ->where('Type', 'restricted')
            ->whereHas('members', fn($q) => $q->where('UserID', $userId))
            ->get();

        // Which conversation are we viewing right now? A restricted thread
        // can only be opened by someone who is actually a member of it —
        // otherwise an excluded user could view it just by guessing/pasting
        // its conversationId into the URL.
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
            ->with('user') // requires user() belongsTo on Message, add if missing
            ->get();

        $groupMembers = GroupStudent::where('GroupID', $groupId)
            ->where('Status', 'active')
            ->with('user')
            ->get();

        // All of this user's groups, so they can switch chats instead of
        // being stuck on whichever group the link happened to point at.
        $userGroups = auth()->user()->groups()->get();

        return view('student.messages', compact(
            'groupId', 'mainConversation', 'restrictedThreads',
            'activeConversation', 'messages', 'groupMembers', 'userGroups'
        ));
    }

    public function store(Request $request, $groupId)
    {
        $request->validate([
            'body' => 'required|string|max:2000',
            'exclude' => 'array',
            'exclude.*' => 'exists:user,UserID',
             'conversation_id' => 'nullable|exists:conversation,ConversationID',
        ]);

        $chatMessage = app(GroupChatService::class)->send(
            $groupId,
            auth()->id(),
            $request->input('body'),
            $request->input('exclude', []),
            $request->input('conversation_id')
        );

        $wantsJson = $request->wantsJson() || $request->header('Accept') === 'application/json';

        if ($wantsJson) {
            return response()->json([
                'success' => true,
                'html' => view('student.partials.chat-bubble', [
                    'msg' => $chatMessage,
                    'canExclude' => $chatMessage->conversation->Type !== 'restricted',
                ])->render(),
            ]);
        }

        return back();
    }
}
