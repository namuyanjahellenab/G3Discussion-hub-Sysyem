<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $topic->Title ?? 'Discussion Export' }}</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 12px; color: #1f2937; }
        .header { margin-bottom: 20px; }
        .meta { color: #4b5563; margin-bottom: 8px; }
        .post { border-top: 1px solid #e5e7eb; padding: 10px 0; }
        .reply { margin-left: 20px; color: #374151; }
    </style>
</head>
<body>
    <div class="header">
        <h2>{{ $topic->Title ?? 'Discussion Export' }}</h2>
        <div class="meta">Group: {{ $topic->creator?->name ?? 'Discussion Hub' }}</div>
        <div class="meta">Date: {{ now()->format('Y-m-d H:i') }}</div>
    </div>

    @foreach($posts as $post)
        <div class="post">
            <strong>{{ $post->author?->UserName ?? $post->author?->name ?? 'Student' }}</strong>
            <div>{{ $post->Content }}</div>
            @if($post->Attachment)
                <div>Attachment: {{ basename($post->Attachment) }}</div>
            @endif
            @foreach($post->replies as $reply)
                <div class="reply">
                    <strong>{{ $reply->author?->UserName ?? $reply->author?->name ?? 'Student' }}</strong>
                    <div>{{ $reply->ReplyContent }}</div>
                </div>
            @endforeach
        </div>
    @endforeach
</body>
</html>
