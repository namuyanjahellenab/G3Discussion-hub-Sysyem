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

        // Spam-flagged messages are held out of every OTHER member's view
        // until an admin clears them (AdminFlaggedContentController::
        // dismissMessage) - flagging exists to stop them flooding the
        // group. The sender is the one exception: they still see their own
        // pending message (chat-bubble.blade.php marks it "under review")
        // so it doesn't look like it silently vanished.
        $messages = Message::where('ConversationID', $activeConversation->ConversationID)
            ->where(function ($q) use ($userId) {
                $q->where('is_spam', false)->orWhere('user_id', $userId);
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
                ->where('is_spam', false)
                ->where('MessageID', '>', $lastRead)
                ->count();
        }

        $lastReadMessageId = $lastReadByConversation[$activeConversation->ConversationID] ?? 0;
        $maxMessageId = $messages->max('MessageID') ?? 0;
        if ($maxMessageId > 0) {
            ReadState::markRead($userId, 'Conversation', $activeConversation->ConversationID, $maxMessageId);
        }

        return view('student.messages', compact(
            'groupId', 'mainConversation', 'restrictedThreads',
            'activeConversation', 'messages', 'groupMembers', 'userGroups',
            'threadUnreadCounts', 'lastReadMessageId'
        ));
    }

    public function store(Request $request, $groupId)
    {
        $request->validate([
            'body' => 'nullable|string|max:2000',
            'exclude' => 'array',
            'exclude.*' => 'exists:User,UserID',
            'conversation_id' => 'nullable|exists:conversation,ConversationID',
            'attachment' => ['nullable', 'file', 'mimes:pdf,doc,docx,ppt,pptx,png,jpg,jpeg,zip', 'max:20480'],
            'parent_message_id' => 'nullable|exists:message,MessageID',
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
        // (via $msg->is_spam) instead of it looking like it silently
        // vanished. It stays invisible to everyone else until an admin
        // clears it (index() only shows another member their own spam).
        if ($wantsJson) {
            return response()->json([
                'success' => true,
                'pending' => (bool) $chatMessage->is_spam,
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
        abort_unless($message->user_id === auth()->id(), 403, 'You can only edit your own messages.');

        $request->validate([
            'body' => 'required|string|max:2000',
        ]);

        $message->update(['body' => $request->input('body')]);
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
        abort_unless($message->user_id === auth()->id(), 403, 'You can only delete your own messages.');

        if ($message->Attachment) {
            Storage::disk('public')->delete($message->Attachment);
        }

        $message->delete();

        if ($request->wantsJson() || $request->header('Accept') === 'application/json') {
            return response()->json(['success' => true]);
        }

        return back();
    }
}
