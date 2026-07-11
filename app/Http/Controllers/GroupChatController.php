<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\ConversationMember;
use App\Models\ConversationExclusion;
use App\Models\GroupStudent;
use App\Models\Message;
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

        // Which conversation are we viewing right now?
        $activeConversation = $conversationId
            ? Conversation::findOrFail($conversationId)
            : $mainConversation;

        $messages = Message::where('ConversationID', $activeConversation->ConversationID)
            ->orderBy('CreatedAt')
            ->with('user') // requires user() belongsTo on Message, add if missing
            ->get();

        $groupMembers = GroupStudent::where('GroupID', $groupId)
            ->where('Status', 'active')
            ->with('user')
            ->get();

        return view('student.messages', compact(
            'groupId', 'mainConversation', 'restrictedThreads',
            'activeConversation', 'messages', 'groupMembers'
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

        $userId = auth()->id();
        $excludeIds = collect($request->input('exclude', []))->map(fn($id) => (int) $id)->sort()->values();

        if ($excludeIds->isEmpty()) {
            // goes to the main group thread
            $conversation = Conversation::firstOrCreate(
                ['group_id' => $groupId, 'Type' => 'group'],
                ['CreatedBy' => $userId]
            );
        } else {
            $conversation = $this->findOrCreateRestrictedConversation($groupId, $userId, $excludeIds);
        }

        Message::create([
            'ConversationID' => $conversation->ConversationID,
            'TopicID' => null,
            'user_id' => $userId,
            'body' => $request->body,
            'is_spam' => 0,
        ]);

        return back();
    }

    private function findOrCreateRestrictedConversation($groupId, $senderId, $excludeIds)
    {
        // look for an existing restricted conversation in this group with the exact same excluded set
        $candidates = Conversation::where('group_id', $groupId)
            ->where('Type', 'restricted')
            ->get();

        foreach ($candidates as $candidate) {
            $existingExcluded = ConversationExclusion::where('ConversationID', $candidate->ConversationID)
                ->pluck('UserID')->sort()->values();

            if ($existingExcluded->toArray() === $excludeIds->toArray()) {
                return $candidate; // reuse — same people excluded
            }
        }

        // no match — create a new restricted conversation
        $conversation = Conversation::create([
            'Type' => 'restricted',
            'CreatedBy' => $senderId,
            'group_id' => $groupId,
        ]);

        // members = all active group students EXCEPT the excluded ones
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

        // log the exclusions
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