<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\GroupStudent;
use App\Models\Notification;
use App\Models\Post;
use App\Models\Topic;
use App\Services\MlGatewayClient;
use Carbon\Carbon;
use Illuminate\Http\Request;

class SyncController extends Controller
{
    public function pull(Request $request)
    {
        $user = $request->user();

        $sinceParam = $request->query('since');
        $since = $sinceParam ? Carbon::parse($sinceParam) : Carbon::createFromTimestamp(0);

        $groupIds = GroupStudent::where('UserID', $user->UserID)->pluck('GroupID');

        $topics = Topic::whereIn('GroupID', $groupIds)
            ->where('CreatedAt', '>', $since)
            ->orderBy('CreatedAt')
            ->get(['TopicID', 'Title', 'Category', 'CreatedBy', 'CreatedAt', 'GroupID']);

        $topicIds = Topic::whereIn('GroupID', $groupIds)->pluck('TopicID');

        $posts = Post::whereIn('TopicID', $topicIds)
            ->where('CreatedAt', '>', $since)
            ->orderBy('CreatedAt')
            ->get(['PostID', 'TopicID', 'UserID', 'Content', 'CreatedAt']);

        $notifications = Notification::where('UserID', $user->UserID)
            ->where('CreatedAt', '>', $since)
            ->orderBy('CreatedAt')
            ->get(['NotificationID', 'UserID', 'Message', 'Status', 'CreatedAt', 'Type']);

        return response()->json([
            'topics' => $topics,
            'posts' => $posts,
            'notifications' => $notifications,
        ]);
    }

    public function push(Request $request)
    {
        $entityType = $request->input('entityType');
        $operation = $request->input('operation');
        $payload = $request->input('payload', []);

        if ($operation !== 'Create') {
            return response()->json(['message' => 'Unsupported operation'], 422);
        }

        if ($entityType === 'Topic') {
            $topic = Topic::create([
                'Title' => $payload['Title'] ?? null,
                'Category' => $payload['Category'] ?? null,
                'CreatedBy' => $payload['CreatedBy'] ?? $request->user()->UserID,
                'GroupID' => $payload['GroupID'] ?? null,
            ]);

            return response()->json(['TopicID' => $topic->TopicID], 201);
        }

        if ($entityType === 'Post') {
            $content = $payload['Content'] ?? '';

            $moderation = app(MlGatewayClient::class)->moderateContent($content);

            if (!$moderation['isEducational']) {
                return response()->json(['message' => 'Content blocked: not relevant to course discussion'], 422);
            }

            $isSpam = $moderation['isSpam'];

            $post = Post::create([
                'TopicID' => $payload['TopicID'] ?? null,
                'UserID' => $payload['UserID'] ?? $request->user()->UserID,
                'Content' => $payload['Content'] ?? null,
                'IsFlagged' => $isSpam,
                'FlaggedReason' => $isSpam ? 'Auto-flagged by spam detection' : null,
            ]);

            return response()->json(['PostID' => $post->PostID], 201);
        }

        return response()->json(['message' => 'Unknown entityType'], 422);
    }
}
