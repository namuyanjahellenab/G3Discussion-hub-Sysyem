@extends('layouts.app')

@section('content')
<style>
    .content-workspace { padding: 28px; max-width: 820px; }
    .page-header { margin-bottom: 24px; }
    .page-header p.eyebrow { text-transform: uppercase; color: var(--text-muted); font-size: 0.75rem; font-weight: 600; letter-spacing: 0.5px; margin: 0 0 4px 0; }
    .page-header h1 { font-size: 24px; font-weight: 800; margin: 0; }

    .mod-row { display: flex; align-items: center; gap: 14px; background: var(--surface-card); border: 1px solid var(--surface-border); border-radius: var(--radius-lg); padding: 14px 18px; margin-bottom: 12px; box-shadow: var(--shadow-soft); text-decoration: none; color: inherit; }
    .mod-row.answered-new { border-color: var(--accent-success); background: var(--accent-success-bg); }
    .mod-row .title { font-weight: 700; font-size: 13.5px; color: var(--text-heading); }
    .mod-row .sub { font-size: 12px; color: var(--text-muted); margin-top: 2px; }
    .mod-row .badge-status { font-size: 10.5px; font-weight: 700; padding: 4px 10px; border-radius: 999px; white-space: nowrap; }
    .badge-new { background: var(--accent-success); color: #fff; }
    .badge-read { background: var(--luna-subtle-bg); color: var(--luna-mid); }
    .badge-waiting { background: var(--surface-bg); color: var(--text-muted); }

    .empty-state { padding: 40px; text-align: center; color: var(--text-muted); font-size: 13.5px; }
</style>

<div class="content-workspace">
    <div class="page-header">
        <p class="eyebrow">Student</p>
        <h1>My Questions</h1>
    </div>

    @forelse($myTopics as $topic)
        <a href="{{ route('topics.show', $topic) }}" class="mod-row {{ $topic->hasUnreadAnswer ? 'answered-new' : '' }}">
            <div style="flex: 1;">
                <div class="title">{{ $topic->Title }}</div>
                <div class="sub">
                    {{ $topic->group?->GroupName ?? '—' }} &bull;
                    @if($topic->acceptedReply)
                        Answered by {{ $topic->acceptedReply->author?->UserName ?? $topic->acceptedReply->author?->name ?? 'a member' }}
                    @elseif($topic->replies_count > 0)
                        Waiting on a reply &bull; asked {{ $topic->CreatedAt->diffForHumans() }}
                    @else
                        Waiting on a reply
                    @endif
                </div>
            </div>
            <span class="badge-status {{ $topic->hasUnreadAnswer ? 'badge-new' : ($topic->acceptedReply ? 'badge-read' : 'badge-waiting') }}">
                {{ $topic->hasUnreadAnswer ? '● New' : ($topic->acceptedReply ? 'Answered' : 'Waiting') }}
            </span>
        </a>
    @empty
        <div class="empty-state">You haven't posted any questions yet.</div>
    @endforelse
</div>
@endsection
