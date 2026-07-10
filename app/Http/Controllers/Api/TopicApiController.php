<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Group;
use App\Models\Post;
use App\Models\Topic;
use Illuminate\Http\Request;

/**
 * Mirrors the exact behaviour of DiscussionHubPageController's
 * groupTopics() / createTopic() / storeTopic() / showTopic() methods
 * (see resources/views/forum/group.blade.php and topics/show.blade.php),
 * just returning JSON instead of a Blade view so the JavaFX desktop
 * client can render an identical screen.
 */
class TopicApiController extends Controller
{
    // GET /api/groups/{group}/topics — same query as groupTopics()
    public function index(Request $request, Group $group)
    {
        $search = $request->input('search');
        $filter = $request->input('filter', 'all');
        $page = max(1, (int) $request->input('page', 1));

        $topics = Topic::where('GroupID', $group->GroupID)
            ->withCount('posts')
            ->with('creator')
            ->when($search, function ($q) use ($search) {
                $q->where('Title', 'like', "%{$search}%");
            })
            ->when($filter !== 'all', function ($q) use ($filter) {
                $q->where('Status', $filter);
            })
            ->orderByDesc('IsPinned')
            ->latest('CreatedAt')
            ->paginate(5, ['*'], 'page', $page);

        return response()->json([
            'group_id' => $group->GroupID,
            'group_name' => $group->GroupName,
            'current_page' => $topics->currentPage(),
            'last_page' => $topics->lastPage(),
            'total' => $topics->total(),
            'topics' => $topics->getCollection()->map(function ($topic) {
                return [
                    'id' => $topic->TopicID,
                    'title' => $topic->Title,
                    'status' => $topic->Status ?? 'open',
                    'is_pinned' => (bool) $topic->IsPinned,
                    'reply_count' => (int) $topic->posts_count,
                    'author' => $topic->creator?->UserName ?? $topic->creator?->name ?? 'a member',
                    'created_at_human' => optional($topic->CreatedAt)->diffForHumans(),
                ];
            })->values(),
        ]);
    }

    // POST /api/topics — same validation + create logic as storeTopic()
    public function store(Request $request)
    {
        $request->validate([
            'Title' => 'required|string|max:255',
            'GroupID' => 'required|exists:Group,GroupID',
            'Content' => 'required|string',
        ]);

        $group = Group::find($request->input('GroupID'));
        $user = $request->user();

        $topic = Topic::create([
            'Title' => $request->input('Title'),
            'GroupID' => $request->input('GroupID'),
            'CreatedBy' => $user->UserID,
            'Status' => 'open',
            'Category' => $group->GroupName,
        ]);

        $post = Post::create([
            'TopicID' => $topic->TopicID,
            'UserID' => $user->UserID,
            'Content' => $request->input('Content'),
        ]);

        return response()->json([
            'id' => $topic->TopicID,
            'title' => $topic->Title,
            'group_id' => $topic->GroupID,
            'main_post_id' => $post->PostID,
        ], 201);
    }

    // GET /api/topics/{topic} — same eager-loading as showTopic()
    public function show(Request $request, Topic $topic)
    {
        $mainPost = Post::where('TopicID', $topic->TopicID)
            ->with(['author', 'replies.author', 'replies.quotedReply.author'])
            ->oldest('CreatedAt')
            ->first();

        $user = $request->user();

        return response()->json([
            'id' => $topic->TopicID,
            'title' => $topic->Title,
            'status' => $topic->Status ?? 'open',
            'group_id' => $topic->GroupID,
            'can_accept' => $user->Role === 'Lecturer',
            'main_post' => $mainPost ? [
                'id' => $mainPost->PostID,
                'content' => $mainPost->Content,
                'author' => $mainPost->author?->UserName ?? $mainPost->author?->name ?? 'a member',
                'created_at_human' => optional($mainPost->CreatedAt)->diffForHumans(),
                'replies' => $mainPost->replies->map(function ($reply) use ($user) {
                    return [
                        'id' => $reply->ReplyID,
                        'content' => $reply->ReplyContent,
                        'author' => $reply->author?->UserName ?? $reply->author?->name ?? 'a member',
                        'author_role' => $reply->author?->Role,
                        'is_own' => $reply->UserID === $user->UserID,
                        'is_accepted' => (bool) $reply->IsAccepted,
                        'created_at_human' => optional($reply->CreatedAt)->diffForHumans(),
                        'quoted' => $reply->quotedReply ? [
                            'author' => $reply->quotedReply->author?->UserName ?? $reply->quotedReply->author?->name ?? 'a member',
                            'snippet' => \Illuminate\Support\Str::limit($reply->quotedReply->ReplyContent, 90),
                        ] : null,
                    ];
                })->values(),
            ] : null,
        ]);
    }
}
