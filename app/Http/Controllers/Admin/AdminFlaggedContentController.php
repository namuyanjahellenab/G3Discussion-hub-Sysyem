<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Message;
use App\Models\Post;
use App\Models\Reply;
use App\Models\Warning;
use App\Services\ParticipationService;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class AdminFlaggedContentController extends Controller
{
    public function index(Request $request)
    {
        // Posts and Replies are two different Eloquent models, but the admin
        // just wants one "Flagged Posts and Replies" queue ranked by when
        // each was flagged - normalize both into a common shape, merge, sort,
        // then paginate the merged collection by hand (a plain Eloquent
        // paginate() can only ever page a single query).
        // IsFlagged only flips true once the escalation threshold (2+ distinct
        // flaggers, or ML spam detection) is met - orWhereHas('flags') also
        // surfaces a single student's report immediately instead of making
        // the admin wait for a second person to flag the same thing.
        $posts = Post::where('IsFlagged', true)
            ->orWhereHas('flags')
            ->with(['author', 'topic.group', 'flags'])
            ->get()
            ->map(fn (Post $post) => [
                'type' => 'Post',
                'id' => $post->PostID,
                'content' => $post->Content,
                'author_name' => $post->author?->UserName ?? 'Unknown User',
                'author_id' => $post->author?->UserID,
                'context' => $post->topic?->Title ?? 'Deleted topic',
                'group_name' => $post->topic?->group?->GroupName,
                'flag_count' => $post->flags->count(),
                'reason' => $post->FlaggedReason,
                'date' => $post->CreatedAt,
                'dismiss_route' => route('admin.flagged-content.dismiss', $post->PostID),
                'destroy_route' => route('admin.flagged-content.destroy', $post->PostID),
            ]);

        $replies = Reply::where('IsFlagged', true)
            ->orWhereHas('flags')
            ->with(['author', 'post.topic.group', 'flags'])
            ->get()
            ->map(fn (Reply $reply) => [
                'type' => 'Reply',
                'id' => $reply->ReplyID,
                'content' => $reply->ReplyContent,
                'author_name' => $reply->author?->UserName ?? 'Unknown User',
                'author_id' => $reply->author?->UserID,
                'context' => $reply->post?->topic?->Title ?? 'Deleted topic',
                'group_name' => $reply->post?->topic?->group?->GroupName,
                'flag_count' => $reply->flags->count(),
                'reason' => $reply->flags->pluck('Reason')->filter()->first(),
                'date' => $reply->CreatedAt,
                'dismiss_route' => route('admin.flagged-content.replies.dismiss', $reply->ReplyID),
                'destroy_route' => route('admin.flagged-content.replies.destroy', $reply->ReplyID),
            ]);

        // A student reporting a chat message (Message::flagBy) is the same
        // kind of human report as flagging a post/reply, so it belongs in
        // the same queue with the same actions (Dismiss/Warn/Delete) - not
        // only in the separate spam table below. Excludes IsSpam=true here
        // even if it's ALSO been reported, since that one is still being
        // held back from the group and stays solely in the spam table until
        // resolved there, instead of appearing (and being actionable) twice.
        $reportedMessages = Message::where('IsFlagged', true)
            ->where('IsSpam', false)
            ->with(['user', 'conversation.group', 'flags'])
            ->get()
            ->map(fn (Message $message) => [
                'type' => 'Message',
                'id' => $message->MessageID,
                'content' => $message->Body,
                'author_name' => $message->user?->UserName ?? 'Unknown User',
                'author_id' => $message->UserID,
                'context' => 'Group Chat',
                'group_name' => $message->conversation?->group?->GroupName,
                'flag_count' => $message->flags->count(),
                'reason' => $message->FlaggedReason,
                'date' => $message->CreatedAt,
                'dismiss_route' => route('admin.flagged-content.messages.dismiss', $message->MessageID),
                'destroy_route' => route('admin.flagged-content.messages.destroy', $message->MessageID),
            ]);

        $merged = $posts->concat($replies)->concat($reportedMessages)->sortByDesc('date')->values();

        $page = (int) $request->get('flagged_page', 1);
        $perPage = 20;
        $flaggedItems = new LengthAwarePaginator(
            $merged->forPage($page, $perPage),
            $merged->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'pageName' => 'flagged_page']
        );

        // Only IsSpam here - a manually-reported (IsFlagged, not spam)
        // message is already in $reportedMessages/$flaggedItems above, so it
        // isn't duplicated (and double-actionable) in both tables.
        $flaggedMessages = Message::where('IsSpam', true)
            ->with('user')
            ->latest('CreatedAt')
            ->limit(20)
            ->get();

        $warningCounts = Warning::where('ExpiryDate', '>', now())
            ->selectRaw('UserID, COUNT(*) as active_count')
            ->groupBy('UserID')
            ->pluck('active_count', 'UserID');

        return view('admin.flagged-content.index', compact(
            'flaggedItems', 'flaggedMessages', 'warningCounts'
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
        // Only a message held back as IsSpam was ever kept OUT of the
        // group in the first place (GroupChatController::index() hides
        // IsSpam=true messages from everyone but the sender) - a manually
        // reported message was never hidden, so there's nothing to "send"
        // for that case, only the report itself to clear.
        $wasHeldBack = (bool) $message->IsSpam;

        $message->flags()->delete();
        $message->update(['IsSpam' => false, 'IsFlagged' => false, 'FlaggedReason' => null]);

        if ($wasHeldBack) {
            $message->load('user');
            try {
                broadcast(new \App\Events\ChatMessageSent($message));
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return back()->with('success', 'Message unflagged.');
    }

    public function destroyMessage(Message $message)
    {
        $message->delete();

        return back()->with('success', 'Message deleted.');
    }
}
