<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Message;
use App\Models\Post;

class AdminFlaggedContentController extends Controller
{
    public function index()
    {
        $flaggedPosts = Post::where('IsFlagged', true)
            ->with(['author', 'topic.group'])
            ->latest('CreatedAt')
            ->paginate(20);

        $flaggedMessages = Message::where('is_spam', true)
            ->with('user')
            ->latest('CreatedAt')
            ->limit(20)
            ->get();

        return view('admin.flagged-content.index', compact('flaggedPosts', 'flaggedMessages'));
    }

    public function dismiss(Post $post)
    {
        $post->update(['IsFlagged' => false, 'FlaggedReason' => null]);

        return back()->with('success', 'Post unflagged.');
    }

    public function destroy(Post $post)
    {
        $post->delete();

        return back()->with('success', 'Post deleted.');
    }

    public function dismissMessage(Message $message)
    {
        $message->update(['is_spam' => false]);

        return back()->with('success', 'Message unflagged.');
    }
}
