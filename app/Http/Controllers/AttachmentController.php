<?php

namespace App\Http\Controllers;

use App\Models\ConversationMember;
use App\Models\Message;
use App\Models\Post;
use App\Services\AttachmentUploader;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class AttachmentController extends Controller
{
    public function download(Post $post)
    {
        if (!$post->Attachment) {
            abort(404);
        }

        return response()->download(
            Storage::disk('public')->path($post->Attachment),
            AttachmentUploader::displayName($post->Attachment)
        );
    }

    public function downloadMessage(Message $message)
    {
        if (!$message->Attachment) {
            abort(404);
        }

        // Only members of the conversation this message belongs to can
        // download its attachment - same boundary GroupChatController::index()
        // already enforces for viewing a restricted thread at all.
        abort_unless(
            ConversationMember::where('ConversationID', $message->ConversationID)
                ->where('UserID', auth()->id())
                ->exists(),
            403
        );

        return response()->download(
            Storage::disk('public')->path($message->Attachment),
            AttachmentUploader::displayName($message->Attachment)
        );
    }
}
