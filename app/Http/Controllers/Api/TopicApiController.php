<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Models\Post;
use App\Models\Reply;
use App\Models\Topic;
use App\Services\AttachmentUploader;
use App\Services\MlGatewayClient;
use App\Services\ParticipationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

// JSON equivalent of DiscussionHubPageController's topic-thread actions
// (showTopic, storeReply, flagReply, deleteReply, acceptAnswer) - same
// moderation gate, same participation/notification side effects, and now
// the same reply attachment support (image/file upload via AttachmentUploader).
class TopicApiController extends Controller
{
    public function show(Request $request, Topic $topic)
    {
        $userId = $request->user()->UserID;
        abort_if($topic->exclusions()->where('UserID', $userId)->exists(), 403);

        $mainPost = Post::where('TopicID', $topic->TopicID)
            ->with(['author', 'replies.author', 'replies.parentReply.author', 'replies.flags'])
            ->oldest('CreatedAt')
            ->first();

        $participants = collect();
        if ($mainPost) {
            $participants->push($mainPost->author);
            foreach ($mainPost->replies as $reply) {
                $participants->push($reply->author);
            }
        }
        $participants = $participants->filter()->unique('UserID');

        $lastActivity = $mainPost?->replies->max('CreatedAt') ?? $mainPost?->CreatedAt;

        $recommended = Topic::where('GroupID', $topic->GroupID)
            ->where('TopicID', '!=', $topic->TopicID)
            ->visibleTo($userId)
            ->withCount('posts')
            ->latest('CreatedAt')
            ->take(3)
            ->get();

        $statusLabels = ['answered' => 'Answered', 'discussion' => 'Discussion', 'open' => 'Open'];

        return response()->json(\App\Support\ApiResponseCasing::withPascalAliases([
            'topic' => [
                'id' => $topic->TopicID,
                'title' => $topic->Title,
                'status' => $statusLabels[$topic->Status] ?? 'Open',
                'group_id' => $topic->GroupID,
                'group_name' => $topic->group->GroupName ?? '—',
                'is_restricted' => $topic->exclusions->isNotEmpty(),
                'last_activity' => $lastActivity?->diffForHumans() ?? '—',
            ],
            'main_post' => $mainPost ? [
                'id' => $mainPost->PostID,
                'author_name' => $mainPost->author->UserName ?? 'a member',
                'content' => $mainPost->Content,
                'created_at' => $mainPost->CreatedAt->diffForHumans(),
            ] : null,
            'replies' => $mainPost ? $mainPost->replies->map(fn ($r) => [
                'id' => $r->ReplyID,
                'user_id' => $r->UserID,
                'author_name' => $r->author->UserName ?? 'a member',
                'is_own' => $r->UserID === $userId,
                'is_lecturer' => $r->author->Role === 'Lecturer',
                'content' => $r->ReplyContent,
                'created_at' => $r->CreatedAt->diffForHumans(),
                // Raw ISO instant alongside the human-friendly string above -
                // the desktop client caches replies it sees live (not just
                // ones pulled via a full sync) so they survive going offline,
                // and a frozen "2 minutes ago" string would go stale/wrong
                // the next time it's rendered from that cache.
                'created_at_iso' => $r->CreatedAt,
                'is_accepted' => (bool) $r->IsAccepted,
                'is_flagged' => (bool) $r->IsFlagged,
                'parent_reply_author' => $r->parentReply->author->UserName ?? null,
                'parent_reply_snippet' => $r->parentReply ? Str::limit($r->parentReply->ReplyContent, 40) : null,
                'can_delete' => $r->UserID === $userId || in_array($request->user()->Role, ['Lecturer', 'Administrator'], true),
                'can_accept' => !$r->IsAccepted && $mainPost && ($userId === $mainPost->UserID || $request->user()->Role === 'Lecturer'),
                'can_flag' => $r->UserID !== $userId,
                // flags is eager-loaded on the query below - checking it in
                // memory instead of an exists() query per reply.
                'has_flagged' => $r->flags->contains('FlaggedByUserID', $userId),
                'attachment_url' => $r->Attachment ? Storage::url($r->Attachment) : null,
                'attachment_type' => $r->AttachmentType,
                'attachment_name' => $r->Attachment ? AttachmentUploader::displayName($r->Attachment) : null,
            ])->values() : [],
            'participants' => $participants->take(8)->map(fn ($p) => [
                'name' => $p->UserName ?? '?',
            ])->values(),
            'recommended' => $recommended->map(fn ($t) => [
                'id' => $t->TopicID,
                'title' => $t->Title,
                'posts_count' => $t->posts_count,
                'created_at' => $t->CreatedAt->diffForHumans(),
            ])->values(),
        ]));
    }

    public function storeReply(Request $request, Post $post)
    {
        $request->validate([
            'ReplyContent' => 'required|string',
            'parent_reply_id' => 'nullable|exists:Reply,ReplyID',
            'attachment' => ['nullable', 'file', 'mimes:pdf,doc,docx,ppt,pptx,png,jpg,jpeg,zip', 'max:20480'],
        ]);

        $userId = $request->user()->UserID;

        $attachmentPath = null;
        $attachmentType = null;
        if ($request->hasFile('attachment')) {
            $stored = AttachmentUploader::store($request->file('attachment'));
            $attachmentPath = $stored['path'];
            $attachmentType = $stored['type'];
        }

        $reply = Reply::create([
            'PostID' => $post->PostID,
            'UserID' => $userId,
            'ReplyContent' => $request->input('ReplyContent'),
            'ParentReplyID' => $request->input('parent_reply_id'),
            'Attachment' => $attachmentPath,
            'AttachmentType' => $attachmentType,
        ]);

        if ($post->UserID !== $userId) {
            $replierName = $request->user()->UserName ?? 'Someone';
            $snippet = Str::limit($request->input('ReplyContent'), 60);
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

        return response()->json(['id' => $reply->ReplyID], 201);
    }

    public function flagReply(Request $request, Reply $reply)
    {
        $request->validate(['Reason' => 'nullable|string|max:250']);
        $reply->flagBy($request->user()->UserID, $request->input('Reason'));

        return response()->json(['success' => true]);
    }

    // Counterpart to flagReply() - "I reported that by mistake". Mirrors
    // DiscussionHubPageController::unflagReply().
    public function unflagReply(Request $request, Reply $reply)
    {
        $reply->unflagBy($request->user()->UserID);

        return response()->json(['success' => true]);
    }

    public function deleteReply(Request $request, Reply $reply)
    {
        $isOwner = $reply->UserID === $request->user()->UserID;
        $isModerator = in_array($request->user()->Role, ['Lecturer', 'Administrator'], true);
        abort_unless($isOwner || $isModerator, 403);

        $userId = $reply->UserID;
        $groupId = $reply->post?->topic?->GroupID;
        $reply->delete();

        if ($groupId) {
            app(ParticipationService::class)->recalculate($userId, $groupId);
        }

        return response()->json(['success' => true]);
    }

    // Mirrors DiscussionHubPageController::exportTopic() - same Python
    // gateway (richer rendering) with a local Dompdf fallback, so the
    // download always succeeds either way, just like the web version.
    public function export(Topic $topic)
    {
        $pdfBytes = app(MlGatewayClient::class)->exportTopicPdf($topic->TopicID);

        if ($pdfBytes === null) {
            $posts = Post::with(['author', 'parent.author', 'replies.author'])
                ->where('TopicID', $topic->TopicID)
                ->orderBy('CreatedAt')
                ->get();

            $html = view('messages.export_pdf', compact('topic', 'posts'))->render();
            $dompdf = new \Dompdf\Dompdf();
            $dompdf->loadHtml($html);
            $dompdf->setPaper('A4', 'portrait');
            $dompdf->render();
            $pdfBytes = $dompdf->output();
        }

        $filename = Str::slug($topic->Title ?: 'discussion') . '-discussion.pdf';

        return response($pdfBytes, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    // Mirrors DiscussionHubPageController::showTopic()'s shareLinks build -
    // Python gateway first (richer text), same PHP fallback shape if
    // unreachable. Matches web's WhatsApp/Twitter/Facebook/email links.
    public function shareLinks(Topic $topic)
    {
        $baseUrl = config('app.url');
        $shareLinks = app(MlGatewayClient::class)->topicShareLinks($topic->TopicID, $baseUrl)
            ?? $this->buildFallbackShareLinks($topic);

        return response()->json(\App\Support\ApiResponseCasing::withPascalAliases($shareLinks));
    }

    private function buildFallbackShareLinks(Topic $topic): array
    {
        $shareUrl = route('topics.show', $topic);
        $shareText = 'Check out "' . $topic->Title . '" on Discussion Hub';

        return [
            'TopicID' => $topic->TopicID,
            'ShareUrl' => $shareUrl,
            'ShareText' => $shareText,
            'Links' => [
                'whatsapp' => 'https://wa.me/?' . http_build_query(['text' => $shareText . ' ' . $shareUrl]),
                'twitter' => 'https://twitter.com/intent/tweet?' . http_build_query(['text' => $shareText, 'url' => $shareUrl]),
                'facebook' => 'https://www.facebook.com/sharer/sharer.php?' . http_build_query(['u' => $shareUrl]),
                'email' => 'mailto:?' . http_build_query(['subject' => $topic->Title, 'body' => $shareText . ' ' . $shareUrl]),
            ],
        ];
    }

    public function acceptAnswer(Request $request, Reply $reply)
    {
        $post = $reply->post;
        $isOriginalPoster = $request->user()->UserID === $post->UserID;
        $isLecturer = $request->user()->Role === 'Lecturer';
        abort_unless($isOriginalPoster || $isLecturer, 403);

        Reply::where('PostID', $post->PostID)->update(['IsAccepted' => false]);
        $reply->update(['IsAccepted' => true]);
        $post->topic()->update(['Status' => 'answered']);

        app(ParticipationService::class)->recalculate($reply->UserID, $post->topic?->GroupID);

        return response()->json(['success' => true]);
    }
}
