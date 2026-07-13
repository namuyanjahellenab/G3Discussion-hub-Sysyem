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
@endphp

<style>
    .content-workspace { padding: 28px; max-width: 1200px; }
    .breadcrumb-row { color: var(--text-muted); font-size: 0.8rem; margin-bottom: 8px; }
    .breadcrumb-row a { color: var(--text-muted); text-decoration: none; }
    .breadcrumb-row a:hover { color: var(--luna-mid); }
    .breadcrumb-row span.current { color: var(--text-heading); font-weight: 600; }

    .page-header { display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 24px; flex-wrap: wrap; gap: 12px; }
    .page-header h1 { font-size: 24px; font-weight: 800; margin: 0; }

    .btn { display: inline-flex; align-items: center; justify-content: center; gap: 8px; padding: 0.55rem 1rem; border-radius: var(--radius-md); font-size: 13px; font-weight: 600; text-decoration: none; border: 1px solid transparent; box-shadow: var(--shadow-soft); }
    .btn-primary { background: var(--luna-mid); color: #fff; }
    .btn-primary:hover { background: var(--luna-dark); color: #fff; }

    .forum-layout { display: flex; gap: 20px; align-items: flex-start; }
    .forum-main { flex: 1; min-width: 0; }

    .topic-toolbar { display: flex; gap: 12px; margin-bottom: 20px; flex-wrap: wrap; }
    .topic-toolbar .search-wrap { flex: 1; min-width: 220px; position: relative; }
    .topic-toolbar .search-wrap i { position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: var(--text-muted); }
    .topic-toolbar .search-wrap input { padding-left: 38px; }
    .topic-toolbar select { max-width: 180px; }

    .panel { background: var(--surface-card); border: 1px solid var(--surface-border); border-radius: var(--radius-lg); overflow: hidden; box-shadow: var(--shadow-soft); }

    .topic-row { display: flex; flex-direction: column; gap: 6px; padding: 16px 20px; border-bottom: 1px solid var(--surface-border); text-decoration: none; color: inherit; }
    .topic-row:last-child { border-bottom: none; }
    .topic-row:hover { background: var(--surface-bg); }
    .topic-row .badges { display: flex; gap: 6px; }
    .topic-row .title { color: var(--text-heading); font-weight: 700; font-size: 13.5px; margin: 0; }
    .topic-row .meta { color: var(--text-muted); font-size: 12px; }

    .empty-state { padding: 32px 20px; text-align: center; color: var(--text-muted); font-size: 13.5px; }

    .pagination-row { display: flex; justify-content: space-between; align-items: center; margin-top: 16px; color: var(--text-muted); font-size: 12.5px; flex-wrap: wrap; gap: 10px; }
    .pagination { margin: 0; }
    .pagination .page-link { color: var(--luna-mid); border-color: var(--surface-border); }
    .pagination .page-item.active .page-link { background-color: var(--luna-mid); border-color: var(--luna-mid); }
    .pagination .page-link:hover { color: var(--luna-dark); }

    .right-info-panel { display: flex; flex-direction: column; gap: 20px; max-width: 300px; }
    .profile-mini { display: flex; align-items: center; gap: 10px; padding: 16px 20px; }
    .profile-mini .avatar { width: 40px; height: 40px; border-radius: 50%; background: var(--luna-mid); color: #fff; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 13px; }
    .profile-mini .name { font-weight: 700; color: var(--text-heading); font-size: 13.5px; }
    .profile-mini .role { color: var(--text-muted); font-size: 11.5px; }

    .panel-header { padding: 16px 20px; border-bottom: 1px solid var(--surface-border); }
    .panel-header h2 { font-size: 15px; font-weight: 700; margin: 0; color: var(--text-heading); }
    .group-info-body { padding: 16px 20px; }
    .group-info-body .stat { display: flex; justify-content: space-between; font-size: 13px; color: var(--text-body); padding: 6px 0; }
    .group-info-body .stat strong { color: var(--text-heading); }
    .group-info-body a { display: inline-flex; align-items: center; gap: 6px; margin-top: 10px; font-size: 12.5px; font-weight: 600; color: var(--luna-mid); text-decoration: none; }
    .group-info-body a:hover { color: var(--luna-dark); }

    .announcement-banner { background: var(--luna-mid); color: #fff; border-radius: var(--radius-lg); padding: 18px 20px; }
    .announcement-banner .tag { display: flex; align-items: center; gap: 8px; text-transform: uppercase; font-size: 0.72rem; font-weight: 700; letter-spacing: 0.5px; opacity: 0.9; margin-bottom: 8px; }
    .announcement-banner .body { font-size: 0.85rem; font-weight: 500; line-height: 1.4; }

    @media (max-width: 1024px) {
        .forum-layout { flex-direction: column; }
        .right-info-panel { max-width: 100%; }
    }
</style>

<div class="content-workspace">
    <div class="breadcrumb-row">
        <a href="{{ route('dashboard') }}">Home</a> &rsaquo;
        <a href="{{ route('forum.index') }}">{{ $group->GroupName }}</a> &rsaquo;
        <span class="current">Forum</span>
    </div>

    <div class="page-header">
        <h1>{{ $group->GroupName }}</h1>
        <a href="{{ route('topics.create', $group) }}" class="btn btn-primary"><i class="fa-solid fa-plus"></i> New Topic</a>
    </div>

    <div class="forum-layout">
        <div class="forum-main">
            <form method="GET" action="{{ route('groups.topics', $group) }}" class="topic-toolbar">
                <div class="search-wrap">
                    <i class="fa-solid fa-magnifying-glass"></i>
                    <input type="text" name="search" value="{{ $search }}" placeholder="Search topics..." class="form-control">
                </div>
                <select name="filter" class="form-select" onchange="this.form.submit()">
                    <option value="all" {{ $filter === 'all' ? 'selected' : '' }}>Filter: All</option>
                    <option value="open" {{ $filter === 'open' ? 'selected' : '' }}>Open</option>
                    <option value="answered" {{ $filter === 'answered' ? 'selected' : '' }}>Answered</option>
                    <option value="discussion" {{ $filter === 'discussion' ? 'selected' : '' }}>Discussion</option>
                </select>
            </form>

            <div class="panel">
                @forelse($topics as $topic)
                    @php $meta = $statusMap[$topic->Status] ?? $statusMap['open']; @endphp
                    <a href="{{ route('topics.show', $topic) }}" class="topic-row">
                        <div class="badges">
                            @if($topic->IsPinned)
                                <span class="badge bg-primary">Pinned</span>
                            @endif
                            <span class="badge {{ $meta['badge'] }}">{{ $meta['label'] }}</span>
                        </div>
                        <p class="title">{{ $topic->Title }}</p>
                        <div class="meta">
                            by {{ $topic->creator?->UserName ?? $topic->creator?->name ?? 'a member' }}
                            &bull; {{ $topic->posts_count }} {{ Str::plural('reply', $topic->posts_count) }}
                            &bull; {{ $topic->CreatedAt->diffForHumans() }}
                        </div>
                    </a>
                @empty
                    <div class="empty-state">No topics found.</div>
                @endforelse
            </div>

            <div class="pagination-row">
                <span>Showing {{ $topics->firstItem() ?? 0 }}-{{ $topics->lastItem() ?? 0 }} of {{ $topics->total() }} topics</span>
                {{ $topics->links() }}
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

            <section class="panel">
                <div class="panel-header"><h2>Group Info</h2></div>
                <div class="group-info-body">
                    <div class="stat"><span>Topics</span><strong>{{ $topics->total() }}</strong></div>
                    <a href="{{ route('forum.index') }}"><i class="fa-solid fa-arrow-left"></i> Back to all groups</a>
                </div>
            </section>

            <div class="announcement-banner">
                <div class="tag"><i class="fa-solid fa-bullhorn"></i> Tip</div>
                <div class="body">Pin important topics and mark answers as accepted to keep this forum easy to navigate.</div>
            </div>
        </div>
    </div>
</div>
@endsection
