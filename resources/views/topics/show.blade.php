<!-- mkdir -p resources/views/topics
cat > resources/views/topics/show.blade.php << 'EOF' -->
@extends('layouts.app')

@section('content')
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<style>
    .thread-page-wrap { font-family: 'Inter', sans-serif; padding: 2.5rem; max-width: 800px; margin: 0 auto; }
    .back-link { color: #0d52cc; text-decoration: none; font-size: 0.85rem; font-weight: 600; margin-bottom: 1rem; display: inline-block; }
    .thread-title { color: #101828; font-size: 1.5rem; font-weight: 700; margin: 0 0 6px 0; }
    .thread-meta { color: #667085; font-size: 0.85rem; margin-bottom: 1.5rem; }
    .post-card { background: #fff; border: 1px solid #e4e7ec; border-radius: 14px; padding: 18px 20px; margin-bottom: 16px; display: flex; gap: 14px; }
    .post-avatar { width: 36px; height: 36px; border-radius: 50%; background: #0d52cc; color: #fff; font-weight: 700; font-size: 0.8rem; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
    .post-avatar.lecturer { background: #12b76a; }
    .post-author { font-weight: 700; color: #101828; font-size: 0.9rem; }
    .post-time { color: #98a2b3; font-size: 0.78rem; margin-bottom: 6px; }
    .post-content { color: #344054; font-size: 0.92rem; line-height: 1.5; }
    .accepted-card { border: 2px solid #12b76a; background: #f6fefa; }
    .accepted-tag { color: #12b76a; font-weight: 700; font-size: 0.78rem; text-transform: uppercase; display: flex; align-items: center; gap: 6px; margin-bottom: 10px; }
    .accept-btn { background: none; border: 1px solid #d0d5dd; color: #667085; border-radius: 8px; padding: 5px 10px; font-size: 0.75rem; margin-top: 8px; cursor: pointer; }
    .reply-form textarea { width: 100%; border: 1px solid #e4e7ec; border-radius: 12px; padding: 14px; font-size: 0.9rem; resize: vertical; margin-bottom: 10px; }
    .reply-submit { background: #0d52cc; color: #fff; border: none; border-radius: 10px; padding: 10px 20px; font-weight: 600; font-size: 0.88rem; cursor: pointer; }
    .sidebar-card { background: #fff; border: 1px solid #e4e7ec; border-radius: 14px; padding: 18px; margin-bottom: 16px; }
    .sidebar-title { font-weight: 700; color: #101828; font-size: 0.9rem; margin-bottom: 12px; }
    .sidebar-row { display: flex; justify-content: space-between; font-size: 0.85rem; color: #344054; margin-bottom: 10px; }
    .status-pill { font-size: 0.7rem; font-weight: 700; padding: 3px 10px; border-radius: 20px; text-transform: uppercase; }
    .status-answered { background: #ecfdf3; color: #12b76a; }
    .status-open { background: #f2f4f7; color: #667085; }
    .status-discussion { background: #fef0ff; color: #9e77ed; }
    .participant-avatars { display: flex; }
    .participant-avatars span { width: 26px; height: 26px; border-radius: 50%; background: #0d52cc; color: #fff; font-size: 0.65rem; font-weight: 700; display: flex; align-items: center; justify-content: center; margin-left: -8px; border: 2px solid #fff; }
    .rec-item { padding: 10px 0; border-bottom: 1px solid #f2f4f7; font-size: 0.85rem; }
    .rec-item:last-child { border-bottom: none; }
    .rec-item a { color: #101828; font-weight: 600; text-decoration: none; }
    .rec-item .rec-meta { color: #98a2b3; font-size: 0.75rem; margin-top: 2px; }
</style>

<div class="thread-page-wrap">
    <div>
        <a href="{{ route('groups.topics', $topic->group) }}" class="back-link">&larr; Back to Forum</a>
        <h1 class="thread-title">{{ $topic->Title }}</h1>

        @if($mainPost)
            <div class="thread-meta">
                Posted by {{ $mainPost->author?->UserName ?? $mainPost->author?->name ?? 'a member' }}
                &bull; {{ $mainPost->CreatedAt->diffForHumans() }}
            </div>

            <div class="post-card">
                <div class="post-avatar">{{ Str::substr($mainPost->author?->UserName ?? $mainPost->author?->name ?? '?', 0, 1) }}</div>
                <div style="flex:1;">
                    <div class="post-author">{{ $mainPost->author?->UserName ?? $mainPost->author?->name ?? 'a member' }}</div>
                    <div class="post-content">{{ $mainPost->Content }}</div>
                </div>
            </div>

            <h6 style="font-weight:700; color:#101828; margin: 24px 0 12px 0;">Replies</h6>

            @forelse($mainPost->replies as $reply)
                <div class="post-card {{ $reply->IsAccepted ? 'accepted-card' : '' }}">
                    <div class="post-avatar {{ $reply->author?->Role === 'Lecturer' ? 'lecturer' : '' }}">
                        {{ Str::substr($reply->author?->UserName ?? $reply->author?->name ?? '?', 0, 1) }}
                    </div>
                    <div style="flex:1;">
                        @if($reply->IsAccepted)
                            <div class="accepted-tag"><i class="fa-solid fa-circle-check"></i> Accepted Answer</div>
                        @endif
                        <div class="post-author">{{ $reply->author?->UserName ?? $reply->author?->name ?? 'a member' }}</div>
                        <div class="post-time">{{ $reply->CreatedAt->diffForHumans() }}</div>
                        <div class="post-content">{{ $reply->ReplyContent }}</div>

                        @if(!$reply->IsAccepted && auth()->user()->Role === 'Lecturer')
                            <form method="POST" action="{{ route('replies.accept', $reply) }}">
                                @csrf
                                <button type="submit" class="accept-btn">Mark as Accepted Answer</button>
                            </form>
                        @endif
                    </div>
                </div>
            @empty
                <p style="color:#667085; font-size:0.88rem;">No replies yet. Be the first to respond.</p>
            @endforelse

            <form method="POST" action="{{ route('posts.reply', $mainPost->PostID) }}" class="reply-form" style="margin-top: 20px;">
                @csrf
                <textarea name="ReplyContent" rows="3" placeholder="Write your reply here..." required></textarea>
                <button type="submit" class="reply-submit">Post Reply <i class="fa-solid fa-paper-plane"></i></button>
            </form>
        @else
            <p style="color:#667085;">No discussion started in this topic yet.</p>
        @endif
    </div>
</div>
@endsection
EOF