<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $topic->Title ?? 'Discussion Export' }}</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 12px; color: #1f2937; }
        .header { border-bottom: 2px solid #0052CC; padding-bottom: 8px; margin-bottom: 4px; }
        .header h2 { margin: 0 0 4px 0; color: #0052CC; }
        .meta { color: #4b5563; font-size: 10px; margin-bottom: 2px; }
        .post { border: 1px solid #e5e7eb; border-radius: 4px; padding: 10px; margin-top: 14px; }
        .msg-header { margin-bottom: 6px; }
        .avatar { display: inline-block; width: 20px; height: 20px; border-radius: 10px;
                  background-color: #0052CC; color: #ffffff; text-align: center;
                  font-size: 10px; font-weight: bold; line-height: 20px; }
        .author-name { font-weight: bold; color: #111827; font-size: 12px; }
        .badge-op { color: #0052CC; font-size: 9px; font-weight: bold; margin-left: 6px; }
        .timestamp { color: #9ca3af; font-size: 9px; margin-left: 6px; }
        .content { margin-top: 6px; white-space: pre-wrap; line-height: 1.5; }
        .attachment { margin-top: 6px; color: #6b7280; font-size: 10px; }
        .reply { margin-top: 10px; margin-left: 20px; padding: 8px 10px; background: #f9fafb; border-radius: 4px; }
        .reply .author-name { font-size: 11px; }
        .reply .avatar { width: 16px; height: 16px; border-radius: 8px; line-height: 16px;
                          font-size: 9px; background-color: #6b7280; }
    </style>
</head>
<body>
    <div class="header">
        <h2>{{ $topic->Title ?? 'Discussion Export' }}</h2>
        <div class="meta">Group: {{ $topic->group?->GroupName ?? 'Discussion Hub' }}</div>
        <div class="meta">Generated: {{ now()->setTimezone('Africa/Kampala')->format('Y-m-d H:i') }}</div>
    </div>

    @foreach($posts as $post)
        @php
            $postAuthorName = $post->author?->UserName ?? $post->author?->name ?? 'Student';
        @endphp
        <div class="post">
            <div class="msg-header">
                <span class="avatar">{{ Str::initials($postAuthorName) }}</span>
                <span class="author-name">{{ $postAuthorName }}</span>
                <span class="badge-op">ORIGINAL POST</span>
                @if($post->CreatedAt)
                    <span class="timestamp">{{ $post->CreatedAt->setTimezone('Africa/Kampala')->format('Y-m-d H:i') }}</span>
                @endif
            </div>
            <div class="content">{{ $post->Content }}</div>
            @if($post->Attachment)
                <div class="attachment">Attachment: {{ basename($post->Attachment) }}</div>
            @endif
            @foreach($post->replies as $reply)
                @php
                    $replyAuthorName = $reply->author?->UserName ?? $reply->author?->name ?? 'Student';
                @endphp
                <div class="reply">
                    <div class="msg-header">
                        <span class="avatar">{{ Str::initials($replyAuthorName) }}</span>
                        <span class="author-name">{{ $replyAuthorName }}</span>
                        @if($reply->CreatedAt)
                            <span class="timestamp">{{ $reply->CreatedAt->setTimezone('Africa/Kampala')->format('Y-m-d H:i') }}</span>
                        @endif
                    </div>
                    <div class="content">{{ $reply->ReplyContent }}</div>
                </div>
            @endforeach
        </div>
    @endforeach
</body>
</html>
