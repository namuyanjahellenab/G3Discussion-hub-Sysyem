<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\GroupStudent;
use App\Models\Notification;
use App\Models\Post;
use App\Models\Reply;
use App\Models\Topic;
use App\Services\GroupChatService;
use App\Services\MlGatewayClient;
use App\Services\ParticipationService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

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

        // AuthorName travels alongside each post/reply now - the offline
        // cache used to store only UserID, so a cached topic viewed offline
        // showed "Member #14" instead of a real name (the desktop has no
        // other local cache of other users' display names to resolve that
        // id against).
        $posts = Post::whereIn('TopicID', $topicIds)
            ->where('CreatedAt', '>', $since)
            ->with('author:UserID,UserName')
            ->orderBy('CreatedAt')
            ->get(['PostID', 'TopicID', 'UserID', 'Content', 'CreatedAt'])
            ->map(fn ($p) => [
                'PostID' => $p->PostID, 'TopicID' => $p->TopicID, 'UserID' => $p->UserID,
                'Content' => $p->Content, 'CreatedAt' => $p->CreatedAt,
                'AuthorName' => $p->author->UserName ?? null,
            ]);

        $postIds = Post::whereIn('TopicID', $topicIds)->pluck('PostID');

        // Attachment fields mirror TopicApiController::show()'s reply
        // mapping exactly (same Storage::url() accessor, same basename-as-
        // display-name convention) so the desktop's offline attachment
        // cache has the same URL it would've downloaded from while online.
        $replies = Reply::whereIn('PostID', $postIds)
            ->where('CreatedAt', '>', $since)
            ->with('author:UserID,UserName')
            ->orderBy('CreatedAt')
            ->get(['ReplyID', 'PostID', 'UserID', 'ReplyContent', 'CreatedAt', 'Attachment', 'AttachmentType'])
            ->map(fn ($r) => [
                'ReplyID' => $r->ReplyID, 'PostID' => $r->PostID, 'UserID' => $r->UserID,
                'ReplyContent' => $r->ReplyContent, 'CreatedAt' => $r->CreatedAt,
                'AuthorName' => $r->author->UserName ?? null,
                'AttachmentUrl' => $r->Attachment ? Storage::url($r->Attachment) : null,
                'AttachmentType' => $r->AttachmentType,
                'AttachmentName' => $r->Attachment ? basename($r->Attachment) : null,
            ]);

        $notifications = Notification::where('UserID', $user->UserID)
            ->where('CreatedAt', '>', $since)
            ->orderBy('CreatedAt')
            ->get(['NotificationID', 'UserID', 'Message', 'Status', 'CreatedAt', 'Type']);

        return response()->json([
            'topics' => $topics,
            'posts' => $posts,
            'replies' => $replies,
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
            // A topic composed while offline: mirrors
            // GroupTopicsApiController::store() exactly (topic + its opening
            // post are created together, same moderation check, same
            // audience/exclude handling) rather than the bare Topic-only row
            // this used to create, which silently dropped the topic's own
            // content and any restricted-audience selection.
            $content = $payload['Content'] ?? '';
            $moderation = app(MlGatewayClient::class)->moderateContent($content);

            if (!$moderation['isEducational']) {
                return response()->json([
                    'message' => 'This group is for course discussion — please keep posts relevant to your studies.',
                ], 422);
            }

            $topic = Topic::create([
                'Title' => $payload['Title'] ?? null,
                'Category' => $payload['Category'] ?? null,
                'CreatedBy' => $payload['CreatedBy'] ?? $request->user()->UserID,
                'GroupID' => $payload['GroupID'] ?? null,
                'Status' => 'open',
            ]);

            Post::create([
                'TopicID' => $topic->TopicID,
                'UserID' => $payload['CreatedBy'] ?? $request->user()->UserID,
                'Content' => $content,
                'IsFlagged' => $moderation['isSpam'],
                'FlaggedReason' => $moderation['isSpam'] ? 'Auto-flagged by spam detection' : null,
            ]);

            if (($payload['Audience'] ?? null) === 'custom') {
                foreach (collect($payload['Exclude'] ?? [])->unique() as $excludedUserId) {
                    \App\Models\TopicExclusion::create(['TopicID' => $topic->TopicID, 'UserID' => $excludedUserId]);
                }
            }

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

        if ($entityType === 'Reply') {
            // A reply composed while offline: mirrors
            // TopicApiController::storeReply() (no moderation gate there
            // either - it was removed for live replies too). Attachments
            // aren't supported offline (the desktop only queues text), so
            // Attachment/AttachmentType are always null here.
            $post = Post::find($payload['PostID'] ?? null);
            if (!$post) {
                return response()->json(['message' => 'That topic no longer exists.'], 422);
            }

            $userId = $payload['UserID'] ?? $request->user()->UserID;

            $reply = Reply::create([
                'PostID' => $post->PostID,
                'UserID' => $userId,
                'ReplyContent' => $payload['ReplyContent'] ?? '',
                'ParentReplyID' => $payload['ParentReplyID'] ?? null,
            ]);

            if ($post->UserID !== $userId) {
                $replierName = $request->user()->UserName ?? 'Someone';
                $snippet = \Illuminate\Support\Str::limit($payload['ReplyContent'] ?? '', 60);
                Notification::create([
                    'UserID' => $post->UserID,
                    'Message' => "{$replierName} replied to your question: \"{$snippet}\"",
                    'Status' => false,
                    'Type' => 'Reply',
                ]);
            }

            app(ParticipationService::class)->recalculate($userId, $post->topic?->GroupID);

            if ($request->user()->Role === 'Lecturer') {
                $post->topic()->update(['Status' => 'answered']);
            }

            return response()->json(['ReplyID' => $reply->ReplyID], 201);
        }

        if ($entityType === 'Message') {
            // A chat message composed while offline: goes through the exact
            // same exclusion-resolution/moderation/broadcast path a live
            // POST /chat-messages would (GroupChatApiController::store())
            // rather than a fourth, separately-maintained copy of that logic.
            $message = app(GroupChatService::class)->send(
                (int) ($payload['GroupID'] ?? 0),
                $request->user()->UserID,
                $payload['Body'] ?? '',
                $payload['Exclude'] ?? [],
                isset($payload['ConversationID']) ? (int) $payload['ConversationID'] : null
            );

            return response()->json(['MessageID' => $message->MessageID], 201);
        }

        return response()->json(['message' => 'Unknown entityType'], 422);
    }
}
