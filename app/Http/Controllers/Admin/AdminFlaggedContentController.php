<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Message;
use App\Models\Post;
use App\Models\PostFlag;
use App\Models\Reply;
use App\Models\ReplyFlag;
use App\Models\Warning;
use App\Services\ParticipationService;
use Illuminate\Http\Request;

class AdminFlaggedContentController extends Controller
{
    public function index(Request $request)
    {
        // Filter chips are built from whatever Reason values flaggers have
        // actually typed (Reason is freeform text, not a fixed enum) — "All"
        // plus each distinct reason on record, rather than inventing
        // categories the schema doesn't enforce.
        $reasonFilter = $request->get('reason');
        $availableReasons = PostFlag::whereNotNull('Reason')->distinct()->pluck('Reason')
            ->merge(ReplyFlag::whereNotNull('Reason')->distinct()->pluck('Reason'))
            ->filter()
            ->unique()
            ->values();

        $flaggedPosts = Post::where('IsFlagged', true)
            ->with(['author', 'topic.group', 'flags'])
            ->when($reasonFilter, fn ($q) => $q->whereHas('flags', fn ($fq) => $fq->where('Reason', $reasonFilter)))
            ->latest('CreatedAt')
            ->paginate(20, ['*'], 'posts_page');

        $flaggedReplies = Reply::where('IsFlagged', true)
            ->with(['author', 'post.topic.group', 'flags'])
            ->when($reasonFilter, fn ($q) => $q->whereHas('flags', fn ($fq) => $fq->where('Reason', $reasonFilter)))
            ->latest('CreatedAt')
            ->paginate(20, ['*'], 'replies_page');

        $flaggedMessages = Message::where('is_spam', true)
            ->with('user')
            ->latest('CreatedAt')
            ->limit(20)
            ->get();

        $warningCounts = Warning::where('ExpiryDate', '>', now())
            ->selectRaw('UserID, COUNT(*) as active_count')
            ->groupBy('UserID')
            ->pluck('active_count', 'UserID');

        return view('admin.flagged-content.index', compact(
            'flaggedPosts', 'flaggedReplies', 'flaggedMessages', 'availableReasons', 'reasonFilter', 'warningCounts'
        ));
    }

    /**
     * Dismissing means "reviewed, not an issue" — clear the reports so the
     * post doesn't immediately re-escalate next time anyone recalculates
     * IsFlagged from post_flags (the actual source of truth).
     */
    public function dismiss(Post $post)
    {
        $post->flags()->delete();
        $post->update(['IsFlagged' => false, 'FlaggedReason' => null]);

        return back()->with('success', 'Post unflagged.');
    }

    public function destroy(Post $post, ParticipationService $participation)
    {
        $userId = $post->UserID;
        $groupId = $post->topic?->GroupID;

        $post->delete();

        if ($groupId) {
            $participation->recalculate($userId, $groupId);
        }

        return back()->with('success', 'Post deleted.');
    }

    public function dismissReply(Reply $reply)
    {
        $reply->flags()->delete();
        $reply->update(['IsFlagged' => false]);

        return back()->with('success', 'Reply unflagged.');
    }

    public function destroyReply(Reply $reply, ParticipationService $participation)
    {
        $userId = $reply->UserID;
        $groupId = $reply->post?->topic?->GroupID;

        $reply->delete();

        if ($groupId) {
            $participation->recalculate($userId, $groupId);
        }

        return back()->with('success', 'Reply deleted.');
    }

    public function dismissMessage(Message $message)
    {
        $message->update(['is_spam' => false]);

        return back()->with('success', 'Message unflagged.');
    }
}
