<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\ConversationMember;
use App\Models\GroupStudent;
use App\Models\Message;
use App\Models\ReadState;
use App\Services\AttachmentUploader;
use App\Services\GroupChatService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class GroupChatController extends Controller
{
    public function index($groupId, $conversationId = null)
    {
        $userId = auth()->id();

        // A student who has left (or never joined) this group must not be
        // able to view its chat just by knowing/guessing the URL - without
        // this, the auto-provisioning just below would even silently make
        // them a ConversationMember of a group they're not part of.
        abort_unless(
            GroupStudent::where('GroupID', $groupId)->where('UserID', $userId)->exists(),
            403,
            'You are not a member of this group.'
        );

        // Ensure a main group-wide conversation exists
        $mainConversation = Conversation::firstOrCreate(
            ['GroupID' => $groupId, 'Type' => 'group'],
            ['CreatedBy' => $userId]
        );

        ConversationMember::firstOrCreate([
            'ConversationID' => $mainConversation->ConversationID,
            'UserID' => $userId,
        ], ['JoinedAt' => now()]);

        // Any restricted threads this user belongs to, in this group
        $restrictedThreads = Conversation::where('GroupID', $groupId)
            ->where('Type', 'restricted')
            ->whereHas('members', fn($q) => $q->where('UserID', $userId))
            ->get();

        // Which conversation are we viewing right now? A restricted thread
        // can only be opened by someone who is actually a member of it —
        // otherwise an excluded user could view it just by guessing/pasting
        // its conversationId into the URL.
        if ($conversationId) {
            $activeConversation = Conversation::where('ConversationID', $conversationId)
                ->where('GroupID', $groupId)
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

        // Spam-flagged messages are held out of every OTHER member's view
        // until an admin clears them (AdminFlaggedContentController::
        // dismissMessage) - flagging exists to stop them flooding the
        // group. The sender is the one exception: they still see their own
        // pending message (chat-bubble.blade.php marks it "under review")
        // so it doesn't look like it silently vanished.
        $messages = Message::where('ConversationID', $activeConversation->ConversationID)
            ->where(function ($q) use ($userId) {
                $q->where('IsSpam', false)->orWhere('UserID', $userId);
            })
            ->orderBy('CreatedAt')
            ->with(['user', 'parentMessage.user'])
            ->get();

        $groupMembers = GroupStudent::where('GroupID', $groupId)
            ->where('Status', 'active')
            ->with('user')
            ->get();

        // All of this user's groups, so they can switch chats instead of
        // being stuck on whichever group the link happened to point at.
        $userGroups = auth()->user()->groups()->get();

        // Local "unread" tracking (see ReadState), mirroring the desktop
        // client's - lastReadMessageId is frozen BEFORE markRead() below, so
        // the NEW MESSAGES divider (student.messages.blade.php) reflects
        // "unread since your last visit", not "unread since this request".
        // threadUnreadCounts covers every OTHER thread in this group (the
        // active one is never badged - you're already looking at it).
        $allConversationIds = collect([$mainConversation->ConversationID])
            ->merge($restrictedThreads->pluck('ConversationID'));
        $lastReadByConversation = ReadState::where('UserID', $userId)
            ->where('EntityType', 'Conversation')
            ->whereIn('EntityID', $allConversationIds)
            ->pluck('LastReadItemId', 'EntityID');

        $threadUnreadCounts = [];
        foreach ($allConversationIds as $convId) {
            $lastRead = $lastReadByConversation[$convId] ?? 0;
            $threadUnreadCounts[$convId] = Message::where('ConversationID', $convId)
                ->where('IsSpam', false)
                ->where('MessageID', '>', $lastRead)
                ->count();
        }

        $lastReadMessageId = $lastReadByConversation[$activeConversation->ConversationID] ?? 0;
        $maxMessageId = $messages->max('MessageID') ?? 0;
        if ($maxMessageId > 0) {
            ReadState::markRead($userId, 'Conversation', $activeConversation->ConversationID, $maxMessageId);
        }

        // Mirrors forum/group.blade.php's per-topic unread badges, but for
        // the "Your Groups" switcher above - otherwise switching groups from
        // inside the chat page gives no clue which other group has new
        // messages waiting, only threads within the group already open.
        $otherGroupIds = $userGroups->pluck('GroupID')->reject(fn ($id) => (string) $id === (string) $groupId);
        $otherMainConversations = Conversation::whereIn('GroupID', $otherGroupIds)
            ->where('Type', 'group')
            ->pluck('ConversationID', 'GroupID');

        $otherLastRead = ReadState::where('UserID', $userId)
            ->where('EntityType', 'Conversation')
            ->whereIn('EntityID', $otherMainConversations->values())
            ->pluck('LastReadItemId', 'EntityID');

        $groupUnreadCounts = [];
        foreach ($otherMainConversations as $gid => $convId) {
            $lastRead = $otherLastRead[$convId] ?? 0;
            $groupUnreadCounts[$gid] = Message::where('ConversationID', $convId)
                ->where('IsSpam', false)
                ->where('MessageID', '>', $lastRead)
                ->count();
        }

        return view('student.messages', compact(
            'groupId', 'mainConversation', 'restrictedThreads',
            'activeConversation', 'messages', 'groupMembers', 'userGroups', 'groupUnreadCounts',
            'threadUnreadCounts', 'lastReadMessageId'
        ));
    }

    public function store(Request $request, $groupId)
    {
        $request->validate([
            'body' => 'nullable|string|max:2000',
            'exclude' => 'array',
            'exclude.*' => 'exists:User,UserID',
            'conversation_id' => 'nullable|exists:Conversation,ConversationID',
            'attachment' => ['nullable', 'file', 'mimes:pdf,doc,docx,ppt,pptx,png,jpg,jpeg,zip', 'max:20480'],
            'parent_message_id' => 'nullable|exists:Message,MessageID',
        ]);

        if (blank($request->input('body')) && !$request->hasFile('attachment')) {
            $message = 'Please enter a message or attach a file.';

            if ($request->wantsJson() || $request->header('Accept') === 'application/json') {
                return response()->json([
                    'success' => false,
                    'message' => $message,
                    'errors' => ['body' => [$message]],
                ], 422);
            }

            return back()->withErrors(['body' => $message]);
        }

        $attachmentPath = null;
        $attachmentType = null;

        if ($request->hasFile('attachment')) {
            $stored = AttachmentUploader::store($request->file('attachment'));
            $attachmentPath = $stored['path'];
            $attachmentType = $stored['type'];
        }

        $chatMessage = app(GroupChatService::class)->send(
            $groupId,
            auth()->id(),
            (string) $request->input('body', ''),
            $request->input('exclude', []),
            $request->input('conversation_id'),
            $attachmentPath,
            $attachmentType,
            $request->input('parent_message_id')
        );
        $chatMessage->load('parentMessage.user');

        $wantsJson = $request->wantsJson() || $request->header('Accept') === 'application/json';

        // Spam is saved (see GroupChatService::send) and never broadcast to
        // other members, but the sender still sees their own bubble -
        // chat-bubble.blade.php renders a "flagged for review" notice on it
        // (via $msg->IsSpam) instead of it looking like it silently
        // vanished. It stays invisible to everyone else until an admin
        // clears it (index() only shows another member their own spam).
        if ($wantsJson) {
            return response()->json([
                'success' => true,
                'pending' => (bool) $chatMessage->IsSpam,
                'html' => view('student.partials.chat-bubble', [
                    'msg' => $chatMessage,
                    'canExclude' => $chatMessage->conversation->Type !== 'restricted',
                ])->render(),
            ]);
        }

        return back();
    }

    /**
     * Only the sender can edit their own message, and only its text - an
     * attachment already sent can't be swapped out here, same limitation
     * the forum chat's own deleteMessage() places on editing (it doesn't
     * even offer edit, only delete).
     */
    public function update(Request $request, Message $message)
    {
        abort_unless($message->UserID === auth()->id(), 403, 'You can only edit your own messages.');

        $request->validate([
            'body' => 'required|string|max:2000',
        ]);

        $message->update(['Body' => $request->input('body')]);
        $message->load('user');

        if ($request->wantsJson() || $request->header('Accept') === 'application/json') {
            return response()->json([
                'success' => true,
                'html' => view('student.partials.chat-bubble', [
                    'msg' => $message,
                    'canExclude' => $message->conversation->Type !== 'restricted',
                ])->render(),
            ]);
        }

        return back();
    }

    public function destroy(Request $request, Message $message)
    {
        abort_unless($message->UserID === auth()->id(), 403, 'You can only delete your own messages.');

        if ($message->Attachment) {
            Storage::disk('public')->delete($message->Attachment);
        }

        $message->delete();

        if ($request->wantsJson() || $request->header('Accept') === 'application/json') {
            return response()->json(['success' => true]);
        }

        return back();
    }

    /**
     * Report a group chat message for moderator review - the manual
     * counterpart to IsSpam (the ML gateway's automatic detection). Mirrors
     * DiscussionHubPageController::flagPost()/flagReply().
     */
    public function flag(Request $request, Message $message)
    {
        abort_unless($message->UserID !== auth()->id(), 403, 'You cannot report your own message.');

        $request->validate(['Reason' => 'nullable|string|max:250']);

        $message->flagBy(auth()->id(), $request->input('Reason'));

        if ($request->wantsJson() || $request->header('Accept') === 'application/json') {
            return response()->json(['success' => true]);
        }

        return back()->with('status', 'Message reported. A moderator will review it.');
    }
}
