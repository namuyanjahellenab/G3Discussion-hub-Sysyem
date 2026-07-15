@extends('layouts.app')

@section('content')
@php
    $displayName = auth()->user()->UserName ?? auth()->user()->name ?? 'Student User';
    $nameParts = explode(' ', $displayName);
    $initials = collect($nameParts)->filter()->map(fn($p) => mb_substr($p, 0, 1))->take(2)->implode('');

    $statusMap = [
        'answered' => ['label' => 'Answered', 'badge' => 'bg-success-subtle'],
        'discussion' => ['label' => 'Discussion', 'badge' => 'bg-warning'],
        'open' => ['label' => 'Open', 'badge' => 'bg-secondary-subtle'],
    ];
    $statusMeta = $statusMap[$topic->Status] ?? $statusMap['open'];
@endphp

<style>
    .content-workspace { padding: 28px; max-width: 1200px; }
    .breadcrumb-row { margin-bottom: 16px; }
    .back-link { display: inline-flex; align-items: center; gap: 7px; color: var(--text-muted); text-decoration: none; font-size: 0.82rem; font-weight: 600; padding: 7px 14px; border-radius: 999px; border: 1px solid var(--surface-border); background: var(--surface-card); box-shadow: var(--shadow-soft); transition: color 0.15s ease, border-color 0.15s ease, background-color 0.15s ease; }
    .back-link i { font-size: 0.72rem; transition: transform 0.15s ease; }
    /* Scoped to real pointer devices only - a :hover rule that changes an
       element's appearance makes touch browsers (notably iOS Safari) consume
       the first tap just to simulate hover, requiring a second tap to
       actually navigate. (hover: hover) excludes touchscreens from this rule
       entirely, so a single tap always navigates immediately. */
    @media (hover: hover) {
        .back-link:hover { color: var(--luna-mid); border-color: var(--luna-mid); background: var(--luna-lightest); }
        .back-link:hover i { transform: translateX(-2px); }
    }

    .forum-layout { display: flex; gap: 20px; align-items: flex-start; }
    .forum-main { flex: 1; min-width: 0; max-width: 760px; }

    .thread-title { color: var(--text-heading); font-size: 1.5rem; font-weight: 800; margin: 0; }
    .thread-meta { color: var(--text-muted); font-size: 0.85rem; margin: 10px 0 20px 0; }

    .post-card { background: var(--surface-card); border: 1px solid var(--surface-border); border-radius: var(--radius-lg); padding: 18px 20px; margin-bottom: 16px; display: flex; gap: 14px; box-shadow: var(--shadow-soft); }
    .post-avatar { width: 36px; height: 36px; border-radius: 50%; background: var(--luna-mid); color: #fff; font-weight: 700; font-size: 0.8rem; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
    .post-avatar.lecturer { background: var(--accent-success); }
    .post-author { font-weight: 700; color: var(--text-heading); font-size: 0.9rem; }
    .post-time { color: var(--text-muted); font-size: 0.78rem; margin-bottom: 6px; }
    .post-content { color: var(--text-body); font-size: 0.92rem; line-height: 1.5; white-space: pre-wrap; }

    .accepted-card { border: 2px solid var(--accent-success); background: var(--accent-success-bg); }
    .accepted-tag { color: var(--accent-success); font-weight: 700; font-size: 0.78rem; text-transform: uppercase; display: flex; align-items: center; gap: 6px; margin-bottom: 10px; }
    .accept-btn { background: none; border: 1px solid var(--surface-border); color: var(--text-muted); border-radius: var(--radius-md); padding: 5px 10px; font-size: 0.75rem; margin-top: 8px; cursor: pointer; }
    .accept-btn:hover { border-color: var(--luna-mid); color: var(--luna-mid); }

    .replies-heading { font-weight: 700; color: var(--text-heading); margin: 24px 0 12px 0; font-size: 15px; }
    .empty-state { color: var(--text-muted); font-size: 0.88rem; }

    .reply-form textarea { width: 100%; border: 1px solid var(--surface-border); border-radius: var(--radius-md); padding: 14px; font-size: 0.9rem; resize: vertical; margin-bottom: 10px; font-family: var(--font-body); }

    .btn { display: inline-flex; align-items: center; gap: 8px; padding: 0.55rem 1rem; border-radius: var(--radius-md); font-size: 13px; font-weight: 600; text-decoration: none; border: 1px solid transparent; box-shadow: var(--shadow-soft); cursor: pointer; }
    .btn-primary { background: var(--luna-mid); color: #fff; }
    .btn-primary:hover { background: var(--luna-dark); color: #fff; }

    .right-info-panel { display: flex; flex-direction: column; gap: 20px; max-width: 300px; }
    .panel { background: var(--surface-card); border: 1px solid var(--surface-border); border-radius: var(--radius-lg); overflow: hidden; box-shadow: var(--shadow-soft); }
    .panel-header { padding: 16px 20px; border-bottom: 1px solid var(--surface-border); }
    .panel-header h2 { font-size: 15px; font-weight: 700; margin: 0; color: var(--text-heading); }

    .profile-mini { display: flex; align-items: center; gap: 10px; padding: 16px 20px; }
    .profile-mini .avatar { width: 40px; height: 40px; border-radius: 50%; background: var(--luna-mid); color: #fff; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 13px; }
    .profile-mini .name { font-weight: 700; color: var(--text-heading); font-size: 13.5px; }
    .profile-mini .role { color: var(--text-muted); font-size: 11.5px; }

    .topic-info-body { padding: 4px 20px 16px; }
    .topic-info-body .row { display: flex; justify-content: space-between; font-size: 13px; color: var(--text-body); padding: 6px 0; }

    .participant-avatars { display: flex; padding: 4px 20px 16px; }
    .participant-avatars span { width: 28px; height: 28px; border-radius: 50%; background: var(--luna-mid); color: #fff; font-size: 0.68rem; font-weight: 700; display: flex; align-items: center; justify-content: center; margin-left: -8px; border: 2px solid var(--surface-card); }
    .participant-avatars span:first-child { margin-left: 0; }

    .rec-item { padding: 10px 20px; border-bottom: 1px solid var(--surface-border); font-size: 0.85rem; }
    .rec-item:last-child { border-bottom: none; }
    .rec-item a { color: var(--text-heading); font-weight: 600; text-decoration: none; }
    .rec-item a:hover { color: var(--luna-mid); }
    .rec-item .rec-meta { color: var(--text-muted); font-size: 0.75rem; margin-top: 2px; }

    .announcement-banner { background: var(--luna-mid); color: #fff; border-radius: var(--radius-lg); padding: 18px 20px; }
    .announcement-banner .tag { display: flex; align-items: center; gap: 8px; text-transform: uppercase; font-size: 0.72rem; font-weight: 700; letter-spacing: 0.5px; opacity: 0.9; margin-bottom: 8px; }
    .announcement-banner .body { font-size: 0.85rem; font-weight: 500; line-height: 1.4; }

    @media (max-width: 1024px) {
        .forum-layout { flex-direction: column; }
        .forum-main { max-width: 100%; }
        .right-info-panel { max-width: 100%; }
    }
</style>

<div class="content-workspace">
    <div class="breadcrumb-row">
        <a href="{{ route('groups.topics', $topic->group) }}" class="back-link">
            <i class="fa-solid fa-arrow-left"></i> Back
        </a>
    </div>

    <div class="forum-layout">
        <div class="forum-main">
            <div style="display: flex; align-items: center; justify-content: space-between; gap: 10px; flex-wrap: wrap;">
                <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
                    <h1 class="thread-title">{{ $topic->Title }}</h1>
                    <span class="badge {{ $statusMeta['badge'] }}">{{ $statusMeta['label'] }}</span>
                </div>
                <div style="display: flex; align-items: center; gap: 8px;">
                    <a href="{{ route('topics.export', $topic) }}" class="btn btn-outline-secondary">
                        <i class="fa-solid fa-file-pdf"></i> Export to PDF
                    </a>
                    @if($shareLinks)
                        <div class="dropdown">
                            <button class="btn btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="fa-solid fa-share-nodes"></i> Share
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li><a class="dropdown-item" href="{{ $shareLinks['Links']['whatsapp'] }}" target="_blank" rel="noopener"><i class="fa-brands fa-whatsapp"></i> WhatsApp</a></li>
                                <li><a class="dropdown-item" href="{{ $shareLinks['Links']['twitter'] }}" target="_blank" rel="noopener"><i class="fa-brands fa-x-twitter"></i> X / Twitter</a></li>
                                <li><a class="dropdown-item" href="{{ $shareLinks['Links']['facebook'] }}" target="_blank" rel="noopener"><i class="fa-brands fa-facebook"></i> Facebook</a></li>
                                <li><a class="dropdown-item" href="{{ $shareLinks['Links']['email'] }}"><i class="fa-solid fa-envelope"></i> Email</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><button type="button" class="dropdown-item" onclick="copyTopicShareLink()"><i class="fa-solid fa-link"></i> Copy Link</button></li>
                            </ul>
                        </div>
                    @endif
                </div>
            </div>

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

                <h6 class="replies-heading">Replies</h6>

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
                    <p class="empty-state">No replies yet. Be the first to respond.</p>
                @endforelse

                <form method="POST" action="{{ route('posts.reply', $mainPost->PostID) }}" class="reply-form" style="margin-top: 20px;">
                    @csrf
                    <textarea name="ReplyContent" rows="3" placeholder="Write your reply here..." required></textarea>
                    <button type="submit" class="btn btn-primary">Post Reply <i class="fa-solid fa-paper-plane"></i></button>
                </form>
            @else
                <p class="empty-state" style="margin-top: 16px;">No discussion started in this topic yet.</p>
            @endif
        </div>

        <div class="right-info-panel">
            <section class="panel">
                <div class="profile-mini">
                    <div class="avatar">{{ strtoupper($initials ?: 'S') }}</div>
                    <div>
                        <div class="name">{{ $displayName }}</div>
                        <div class="role">{{ auth()->user()->Role ?? 'Student' }} Account</div>
                    </div>
                </div>
            </section>

            <section class="panel">
                <div class="panel-header"><h2>Topic Info</h2></div>
                <div class="topic-info-body">
                    <div class="row"><span>Group</span><strong>{{ $topic->group?->GroupName ?? '—' }}</strong></div>
                    <div class="row"><span>Status</span><strong>{{ $statusMeta['label'] }}</strong></div>
                    <div class="row"><span>Last activity</span><strong>{{ $lastActivity?->diffForHumans() ?? '—' }}</strong></div>
                </div>
            </section>

            <section class="panel">
                <div class="panel-header"><h2>Participants ({{ $participants->count() }})</h2></div>
                <div class="participant-avatars">
                    @foreach($participants->take(8) as $participant)
                        <span title="{{ $participant->UserName ?? $participant->name }}">{{ strtoupper(Str::substr($participant->UserName ?? $participant->name ?? '?', 0, 1)) }}</span>
                    @endforeach
                    @if($participants->isEmpty())
                        <span class="empty-state" style="padding: 0;">No participants yet.</span>
                    @endif
                </div>
            </section>

            <section class="panel">
                <div class="panel-header"><h2>Related Topics</h2></div>
                <div>
                    @forelse($recommended as $rec)
                        <div class="rec-item">
                            <a href="{{ route('topics.show', $rec) }}">{{ $rec->Title }}</a>
                            <div class="rec-meta">{{ $rec->posts_count }} {{ Str::plural('reply', $rec->posts_count) }} &bull; {{ $rec->CreatedAt->diffForHumans() }}</div>
                        </div>
                    @empty
                        <div class="empty-state" style="padding: 16px 20px;">No other topics in this group yet.</div>
                    @endforelse
                </div>
            </section>

            <div class="announcement-banner">
                <div class="tag"><i class="fa-solid fa-bullhorn"></i> Tip</div>
                <div class="body">Lecturers can mark the most helpful reply as the accepted answer to close out a discussion.</div>
            </div>
        </div>
    </div>
</div>

@if($shareLinks)
<script>
function copyTopicShareLink() {
    navigator.clipboard.writeText(@json($shareLinks['ShareUrl'])).then(() => {
        alert('Link copied to clipboard!');
    });
}
</script>
@endif
@endsection
