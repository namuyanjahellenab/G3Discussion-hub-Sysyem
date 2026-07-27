<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\ConversationExclusion;
use App\Models\ConversationMember;
use App\Models\GroupStudent;
use App\Models\Message;
use App\Models\ReadState;
use App\Services\AttachmentUploader;
use App\Services\GroupChatService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

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
            ['GroupID' => $groupId, 'Type' => 'group'],
            ['CreatedBy' => $userId]
        );

        ConversationMember::firstOrCreate([
            'ConversationID' => $mainConversation->ConversationID,
            'UserID' => $userId,
        ], ['JoinedAt' => now()]);

        $restrictedThreads = Conversation::where('GroupID', $groupId)
            ->where('Type', 'restricted')
            ->whereHas('members', fn ($q) => $q->where('UserID', $userId))
            ->get();

        $conversationId = $request->query('conversation_id');

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
        // dismissMessage) - mirrors GroupChatController::index() (web),
        // which this endpoint was missing entirely, leaking a sender's
        // pending-review message to the whole group on desktop.
        $messages = Message::where('ConversationID', $activeConversation->ConversationID)
            ->where(function ($q) use ($userId) {
                $q->where('IsSpam', false)->orWhere('UserID', $userId);
            })
            ->orderBy('CreatedAt')
            ->with(['user', 'parentMessage.user'])
            ->get();

        $groupMembers = GroupStudent::where('GroupID', $groupId)
            ->where('Status', 'active')
            ->where('UserID', '!=', $userId)
            ->with('user')
            ->get();

        // Mirrors GroupChatController::index() (web) - without this, viewing
        // a conversation from the desktop client never updated the shared
        // ReadState the "unread" badges (both platforms' group-switcher, and
        // web's own thread-list) are computed from, so desktop reads were
        // invisible to the rest of the app.
        $maxMessageId = $messages->max('MessageID') ?? 0;
        if ($maxMessageId > 0) {
            ReadState::markRead($userId, 'Conversation', $activeConversation->ConversationID, $maxMessageId);
        }

        return response()->json(\App\Support\ApiResponseCasing::withPascalAliases([
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
                'user_id' => $m->UserID,
                'author_name' => $m->user->UserName ?? 'Unknown',
                'body' => $m->Body,
                // Human-friendly for display, raw ISO instant alongside it
                // for the desktop client's offline cache to re-derive an
                // accurate relative time from later instead of a frozen one.
                'created_at' => optional($m->CreatedAt)->diffForHumans(),
                'created_at_iso' => $m->CreatedAt,
                'is_spam' => (bool) $m->IsSpam,
                // Was missing entirely - a message sent with an attachment
                // (web supports this; see GroupChatController::store()) came
                // through to the desktop with no way to know it had one,
                // rendering as an empty bubble whenever body was blank.
                // Same field names/shape as TopicApiController's reply
                // attachments, so the desktop can reuse the same rendering.
                'attachment_url' => $m->Attachment ? Storage::url($m->Attachment) : null,
                'attachment_type' => $m->AttachmentType,
                'attachment_name' => $m->Attachment ? AttachmentUploader::displayName($m->Attachment) : null,
                'parent_message_id' => $m->ParentMessageID,
                'parent_message_author' => $m->parentMessage?->user?->UserName,
                'parent_message_snippet' => $m->parentMessage
                    ? \Illuminate\Support\Str::limit($m->parentMessage->Body, 40)
                    : null,
            ]),
        ]));
    }

    public function store(Request $request, int $groupId)
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
            return response()->json([
                'message' => 'Please enter a message or attach a file.',
                'errors' => ['body' => ['Please enter a message or attach a file.']],
            ], 422);
        }

        $attachmentPath = null;
        $attachmentType = null;

        if ($request->hasFile('attachment')) {
            $stored = AttachmentUploader::store($request->file('attachment'));
            $attachmentPath = $stored['path'];
            $attachmentType = $stored['type'];
        }

        $message = app(GroupChatService::class)->send(
            $groupId,
            $request->user()->UserID,
            (string) $request->input('body', ''),
            $request->input('exclude', []),
            $request->input('conversation_id'),
            $attachmentPath,
            $attachmentType,
            $request->input('parent_message_id')
        );
        $message->load('parentMessage.user');

        return response()->json(\App\Support\ApiResponseCasing::withPascalAliases([
            'id' => $message->MessageID,
            'user_id' => $message->UserID,
            'author_name' => $message->user->UserName ?? $request->user()->UserName,
            'body' => $message->Body,
            'created_at' => optional($message->CreatedAt)->diffForHumans(),
            'created_at_iso' => $message->CreatedAt,
            'conversation_id' => $message->ConversationID,
            'is_spam' => (bool) $message->IsSpam,
            'attachment_url' => $message->Attachment ? Storage::url($message->Attachment) : null,
            'attachment_type' => $message->AttachmentType,
            'attachment_name' => $message->Attachment ? AttachmentUploader::displayName($message->Attachment) : null,
            'parent_message_id' => $message->ParentMessageID,
            'parent_message_author' => $message->parentMessage?->user?->UserName,
            'parent_message_snippet' => $message->parentMessage
                ? \Illuminate\Support\Str::limit($message->parentMessage->Body, 40)
                : null,
        ]), 201);
    }

    // JSON counterpart to GroupChatController::update() - only the sender
    // can edit their own message, and only its text (an already-sent
    // attachment can't be swapped out here either).
    public function update(Request $request, Message $message)
    {
        abort_unless($message->UserID === $request->user()->UserID, 403, 'You can only edit your own messages.');

        $request->validate([
            'body' => 'required|string|max:2000',
        ]);

        $message->update(['Body' => $request->input('body')]);

        return response()->json(\App\Support\ApiResponseCasing::withPascalAliases(['success' => true, 'body' => $message->Body]));
    }

    // JSON counterpart to GroupChatController::destroy().
    public function destroy(Request $request, Message $message)
    {
        abort_unless($message->UserID === $request->user()->UserID, 403, 'You can only delete your own messages.');

        if ($message->Attachment) {
            Storage::disk('public')->delete($message->Attachment);
        }

        $message->delete();

        return response()->json(['success' => true]);
    }
}
