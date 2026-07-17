@extends('layouts.app')

@section('content')
@php
    $displayName = auth()->user()->UserName ?? auth()->user()->name ?? 'Student User';
    $initials = Str::initials($displayName);
@endphp

<style>
    .content-workspace { padding: 28px; max-width: 1200px; }
    .page-header { margin-bottom: 24px; display: flex; align-items: flex-end; justify-content: space-between; gap: 16px; flex-wrap: wrap; }
    .page-header p.eyebrow { text-transform: uppercase; color: var(--text-muted); font-size: 0.75rem; font-weight: 600; letter-spacing: 0.5px; margin: 0 0 4px 0; }
    .page-header h1 { font-size: 24px; font-weight: 800; margin: 0; }

    .page-actions { display: flex; gap: 10px; }
    .btn { display: inline-flex; align-items: center; justify-content: center; gap: 8px; padding: 0.5rem 0.95rem; border-radius: var(--radius-md); font-size: 13px; font-weight: 600; text-decoration: none; border: 1px solid transparent; cursor: pointer; }
    .btn-primary { background: var(--luna-mid); color: #fff; }
    .btn-primary:hover { background: var(--luna-dark); color: #fff; }
    .btn-outline { background: var(--surface-card); color: var(--text-body); border-color: var(--surface-border); }
    .btn-outline:hover { border-color: var(--accent-danger); color: var(--accent-danger); }

    .recommend-layout { display: flex; gap: 20px; align-items: flex-start; }
    .recommend-main { flex: 1; min-width: 0; }

    .interest-tags { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 20px; }
    .interest-tag { background: var(--luna-lightest); color: var(--luna-dark); font-size: 12px; font-weight: 600; padding: 6px 14px; border-radius: 999px; }

    .ml-status-note { display: flex; align-items: center; gap: 8px; background: var(--accent-amber-bg); color: var(--accent-amber); border-radius: var(--radius-md); padding: 10px 16px; font-size: 12.5px; font-weight: 500; margin-bottom: 20px; }

    .panel { background: var(--surface-card); border: 1px solid var(--surface-border); border-radius: var(--radius-lg); overflow: hidden; box-shadow: var(--shadow-soft); }
    .panel-header { padding: 16px 20px; border-bottom: 1px solid var(--surface-border); }
    .panel-header h2 { font-size: 15px; font-weight: 700; margin: 0; color: var(--text-heading); }

    .topic-row { display: flex; justify-content: space-between; align-items: center; gap: 16px; padding: 16px 20px; border-bottom: 1px solid var(--surface-border); }
    .topic-row:last-child { border-bottom: none; }
    .topic-row .main { min-width: 0; }
    .topic-row a.title { color: var(--text-heading); font-weight: 700; text-decoration: none; font-size: 13.5px; }
    .topic-row a.title:hover { color: var(--luna-mid); }
    .topic-row .meta { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; color: var(--text-muted); font-size: 12px; margin-top: 6px; }
    .topic-row .meta .sep { color: var(--surface-border); }
    .topic-row .side { display: flex; flex-direction: column; align-items: flex-end; gap: 6px; flex-shrink: 0; }

    .match-badge { font-size: 11.5px; font-weight: 700; padding: 4px 11px; border-radius: 999px; white-space: nowrap; }
    .match-badge.high { background: var(--accent-success-bg); color: var(--accent-success); }
    .match-badge.medium { background: var(--accent-amber-bg); color: var(--accent-amber); }
    .match-badge.low { background: var(--luna-subtle-bg); color: var(--luna-mid); }

    .empty-state { padding: 40px 20px; text-align: center; color: var(--text-muted); font-size: 13.5px; }
    .empty-state i { font-size: 1.6rem; color: var(--luna-light); display: block; margin-bottom: 10px; }

    .right-info-panel { display: flex; flex-direction: column; gap: 20px; max-width: 300px; }
    .profile-mini { display: flex; align-items: center; gap: 10px; padding: 16px 20px; }
    .profile-mini .avatar { width: 40px; height: 40px; border-radius: 50%; background: var(--luna-mid); color: #fff; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 13px; }
    .profile-mini .name { font-weight: 700; color: var(--text-heading); font-size: 13.5px; }
    .profile-mini .role { color: var(--text-muted); font-size: 11.5px; }

    .announcement-banner { background: var(--luna-mid); color: #fff; border-radius: var(--radius-lg); padding: 18px 20px; }
    .announcement-banner .tag { display: flex; align-items: center; gap: 8px; text-transform: uppercase; font-size: 0.72rem; font-weight: 700; letter-spacing: 0.5px; opacity: 0.9; margin-bottom: 8px; }
    .announcement-banner .body { font-size: 0.85rem; font-weight: 500; line-height: 1.4; }

    @media (max-width: 1024px) {
        .recommend-layout { flex-direction: column; }
        .right-info-panel { max-width: 100%; }
    }
</style>

<div class="content-workspace">
    <div class="page-header">
        <div>
            <p class="eyebrow">Suggestions</p>
            <h1>Recommended Topics</h1>
        </div>
        <div class="page-actions">
            <a href="{{ route('recommend.index', ['refresh' => 1]) }}" class="btn btn-outline">
                <i class="fa-solid fa-rotate"></i> Refresh
            </a>
            <form action="{{ route('recommend.dismiss') }}" method="POST" style="margin:0;">
                @csrf
                <button type="submit" class="btn btn-outline">
                    <i class="fa-solid fa-xmark"></i> Dismiss All
                </button>
            </form>
        </div>
    </div>

    <div class="recommend-layout">
        <div class="recommend-main">
            @if($interests->isNotEmpty())
                <div class="interest-tags">
                    @foreach($interests as $interest)
                        <span class="interest-tag">{{ $interest }}</span>
                    @endforeach
                </div>
            @endif

            @if($recommendedTopics->isNotEmpty() && !$mlAvailable)
                <div class="ml-status-note">
                    <i class="fa-solid fa-triangle-exclamation"></i>
                    Recommendation engine unavailable right now — showing the latest topics from your groups instead.
                </div>
            @endif

            <div class="panel">
                <div class="panel-header"><h2>Topics For You</h2></div>
                <div>
                    @forelse($recommendedTopics as $topic)
                        @php
                            $score = $topic->RelevanceScore ?? 0;
                            $tier = $score >= 66 ? 'high' : ($score >= 33 ? 'medium' : 'low');
                        @endphp
                        <div class="topic-row">
                            <div class="main">
                                <a href="{{ route('topics.show', $topic) }}" class="title">{{ $topic->Title }}</a>
                                <div class="meta">
                                    @if($topic->Category)
                                        <span class="badge bg-secondary-subtle">{{ $topic->Category }}</span>
                                    @endif
                                    <span>{{ $topic->creator?->UserName ?? $topic->creator?->name ?? 'a member' }}</span>
                                    <span class="sep">&bull;</span>
                                    <span>{{ $topic->group?->GroupName ?? 'General' }}</span>
                                    <span class="sep">&bull;</span>
                                    <span>{{ $topic->CreatedAt->diffForHumans() }}</span>
                                </div>
                            </div>
                            <div class="side">
                                <span class="match-badge {{ $tier }}">{{ round($score) }}% match</span>
                            </div>
                        </div>
                    @empty
                        <div class="empty-state">
                            <i class="fa-solid fa-lightbulb"></i>
                            No recommendations right now. Join a group and post a few topics, then hit Refresh to get personalized suggestions.
                        </div>
                    @endforelse
                </div>
            </div>
        </div>

        <div class="right-info-panel">
            <section class="panel">
                <div class="profile-mini">
                    <div class="avatar">{{ strtoupper($initials ?: 'S') }}</div>
                    <div>
                        <div class="name">{{ $displayName }}</div>
                        <div class="role">Student Account</div>
                    </div>
                </div>
            </section>

            <div class="announcement-banner">
                <div class="tag"><i class="fa-solid fa-bullhorn"></i> How this works</div>
                <div class="body">Topics are ranked by a machine-learning model using your interests and recent activity. Dismiss to clear the list, or Refresh to regenerate it.</div>
            </div>
        </div>
    </div>
</div>
@endsection
