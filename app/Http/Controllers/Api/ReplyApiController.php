<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Models\Reply;
use Illuminate\Http\Request;

/**
 * Mirrors DiscussionHubPageController's storeReply() / acceptAnswer()
 * exactly (see routes/web.php posts.reply / replies.accept), returning
 * JSON instead of a redirect so the JavaFX client can use it.
 */
class ReplyApiController extends Controller
{
    // POST /api/posts/{post}/reply — same logic as storeReply()
    public function store(Request $request, Post $post)
    {
        $request->validate(['ReplyContent' => 'required|string']);
        $user = $request->user();

        $reply = Reply::create([
            'PostID' => $post->PostID,
            'UserID' => $user->UserID,
            'ReplyContent' => $request->input('ReplyContent'),
            'QuotedReplyID' => $request->input('QuotedReplyID') ?: null,
        ]);

        // Auto-mark topic as answered if a lecturer replies — same rule as the web app
        if ($user->Role === 'Lecturer') {
            $post->topic()->update(['Status' => 'answered']);
        }

        return response()->json([
            'id' => $reply->ReplyID,
            'content' => $reply->ReplyContent,
            'author' => $user->UserName ?? $user->name,
            'author_role' => $user->Role,
            'is_own' => true,
            'is_accepted' => false,
            'created_at_human' => optional($reply->CreatedAt)->diffForHumans(),
        ], 201);
    }

    // POST /api/replies/{reply}/accept — same lecturer-only rule as acceptAnswer()
    public function accept(Request $request, Reply $reply)
    {
        if ($request->user()->Role !== 'Lecturer') {
            return response()->json(['message' => 'Only a lecturer can mark an answer as accepted.'], 403);
        }

        $post = $reply->post;
        Reply::where('PostID', $post->PostID)->update(['IsAccepted' => false]);
        $reply->update(['IsAccepted' => true]);
        $post->topic()->update(['Status' => 'answered']);

        return response()->json(['success' => true]);
    }
}
