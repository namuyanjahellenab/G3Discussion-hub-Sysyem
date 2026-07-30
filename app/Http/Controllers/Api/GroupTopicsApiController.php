<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Group;
use App\Models\Post;
use App\Models\Topic;
use App\Models\TopicExclusion;
use App\Services\MlGatewayClient;
use Illuminate\Http\Request;

// JSON equivalent of DiscussionHubPageController::groupTopics() /
// forum/group.blade.php - search, filter chips (all/open/flagged/restricted)
// with counts, and pagination, exactly like the web page.
class GroupTopicsApiController extends Controller
{
    public function index(Request $request, Group $group)
    {
        $userId = $request->user()->UserID;
        $search = $request->input('search');
        $filter = $request->input('filter', 'all');
        $page = max(1, (int) $request->input('page', 1));
        $perPage = 5;

        $baseTopics = Topic::where('GroupID', $group->GroupID)->visibleTo($userId);

        $filterCounts = [
            'all' => (clone $baseTopics)->count(),
            'open' => (clone $baseTopics)->where('Status', 'open')->count(),
            'flagged' => (clone $baseTopics)->whereHas('replies', fn ($q) => $q->where('Reply.IsFlagged', true))->count(),
            'restricted' => (clone $baseTopics)->whereHas('exclusions')->count(),
        ];

        $query = Topic::where('GroupID', $group->GroupID)
            ->visibleTo($userId)
            ->withCount('posts')
            ->with(['creator', 'exclusions', 'replies'])
            ->withCount(['replies as flagged_replies_count' => fn ($q) => $q->where('Reply.IsFlagged', true)])
            ->when($search, fn ($q) => $q->where('Title', 'like', "%{$search}%"))
            ->when($filter === 'flagged', fn ($q) => $q->whereHas('replies', fn ($rq) => $rq->where('Reply.IsFlagged', true)))
            ->when($filter === 'restricted', fn ($q) => $q->whereHas('exclusions'))
            ->when(!in_array($filter, ['all', 'flagged', 'restricted'], true), fn ($q) => $q->where('Status', $filter))
            ->orderByDesc('IsPinned')
            ->latest('CreatedAt');

        $total = $query->count();
        $topics = $query->forPage($page, $perPage)->get();

        $groupMembers = $group->activeMembers()->where('UserID', '!=', $userId)->values();

        return response()->json(\App\Support\ApiResponseCasing::withPascalAliases([
            'group' => ['id' => $group->GroupID, 'name' => $group->GroupName],
            'group_members' => $groupMembers->map(fn ($gs) => [
                'id' => $gs->UserID,
                'name' => $gs->user->UserName ?? 'Unknown',
            ])->values(),
            'filter_counts' => $filterCounts,
            'total' => $total,
            'page' => $page,
            'last_page' => max(1, (int) ceil($total / $perPage)),
            'topics' => $topics->map(function ($t) {
                $lastActivity = $t->replies->max('CreatedAt') ?? $t->CreatedAt;
                return [
                    'id' => $t->TopicID,
                    'title' => $t->Title,
                    'status' => $t->Status,
                    'is_pinned' => (bool) $t->IsPinned,
                    'is_restricted' => $t->exclusions->isNotEmpty(),
                    'flagged_replies_count' => $t->flagged_replies_count,
                    'creator_name' => $t->creator->UserName ?? 'a member',
                    // Actual reply count (Reply rows nested under the topic's
                    // post/s), not $t->posts_count - a topic almost always
                    // has exactly one Post, so posts_count was always ~1
                    // regardless of how many real replies existed.
                    'posts_count' => $t->replies->count(),
                    'last_activity' => $lastActivity->diffForHumans(),
                ];
            })->values(),
        ]));
    }

    // Mirrors DiscussionHubPageController::storeTopic() - same Topic + Post
    // creation, same custom-audience exclusion support. Starting a new topic
    // is never spam/relevance-checked - only replying within one and group
    // chat messages are. Attachments aren't exposed here since the desktop
    // dialog has no file picker for topic creation.
    public function store(Request $request, Group $group)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'audience' => 'nullable|in:everyone,custom',
            'exclude' => 'array',
            'exclude.*' => 'exists:User,UserID',
        ]);

        $topic = Topic::create([
            'Title' => $request->input('title'),
            'GroupID' => $group->GroupID,
            'CreatedBy' => $request->user()->UserID,
            'Status' => 'open',
            'Category' => $group->GroupName,
        ]);

        Post::create([
            'TopicID' => $topic->TopicID,
            'UserID' => $request->user()->UserID,
            'Content' => $request->input('content'),
        ]);

        if ($request->input('audience') === 'custom') {
            foreach (collect($request->input('exclude', []))->unique() as $excludedUserId) {
                TopicExclusion::create(['TopicID' => $topic->TopicID, 'UserID' => $excludedUserId]);
            }
        }

        return response()->json(['id' => $topic->TopicID, 'title' => $topic->Title], 201);
    }
}
