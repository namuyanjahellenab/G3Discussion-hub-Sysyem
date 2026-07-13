@extends('layouts.app')

@section('content')
@php
    $displayName = auth()->user()->UserName ?? auth()->user()->name ?? 'Student User';
    $nameParts = explode(' ', $displayName);
    $initials = collect($nameParts)->filter()->map(fn($p) => mb_substr($p, 0, 1))->take(2)->implode('');
@endphp

<style>
    .content-workspace { padding: 28px; max-width: 1200px; }
    .page-header { margin-bottom: 24px; }
    .page-header p.eyebrow { text-transform: uppercase; color: var(--text-muted); font-size: 0.75rem; font-weight: 600; letter-spacing: 0.5px; margin: 0 0 4px 0; }
    .page-header h1 { font-size: 24px; font-weight: 800; margin: 0; }

    .stats-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 16px; margin-bottom: 24px; max-width: 480px; }
    .stat-card { background: var(--surface-card); border: 1px solid var(--surface-border); border-top: 3px solid var(--luna-mid); border-radius: var(--radius-lg); padding: 18px 20px; box-shadow: var(--shadow-soft); }
    .stat-card .label { font-size: 12px; color: var(--text-muted); font-weight: 600; text-transform: uppercase; letter-spacing: 0.4px; }
    .stat-card .value { font-size: 26px; font-weight: 800; color: var(--text-heading); margin-top: 6px; }

    .groups-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 16px; margin-bottom: 24px; }
    .group-card { background: var(--surface-card); border: 1px solid var(--surface-border); border-radius: var(--radius-lg); box-shadow: var(--shadow-soft); padding: 24px; display: flex; flex-direction: column; align-items: center; text-align: center; }
    .group-card-icon { width: 44px; height: 44px; background: var(--luna-lightest); color: var(--luna-dark); border-radius: 10px; display: flex; align-items: center; justify-content: center; margin-bottom: 16px; }
    .group-card h5 { color: var(--text-heading); font-size: 1.1rem; font-weight: 700; margin: 0 0 6px 0; }
    .group-card p { color: var(--text-muted); font-size: 0.9rem; margin: 0 0 16px 0; }

    .btn { display: inline-flex; align-items: center; justify-content: center; gap: 8px; padding: 0.55rem 1rem; border-radius: var(--radius-md); font-size: 13px; font-weight: 600; text-decoration: none; border: 1px solid transparent; box-shadow: var(--shadow-soft); width: 100%; }
    .btn-primary { background: var(--luna-mid); color: #fff; }
    .btn-primary:hover { background: var(--luna-dark); color: #fff; }

    .panel { background: var(--surface-card); border: 1px solid var(--surface-border); border-radius: var(--radius-lg); overflow: hidden; box-shadow: var(--shadow-soft); }
    .panel-header { padding: 16px 20px; border-bottom: 1px solid var(--surface-border); }
    .panel-header h2 { font-size: 15px; font-weight: 700; margin: 0; color: var(--text-heading); }

    .topic-row { display: flex; justify-content: space-between; align-items: center; gap: 12px; padding: 14px 20px; border-bottom: 1px solid var(--surface-border); }
    .topic-row:last-child { border-bottom: none; }
    .topic-row a.title { color: var(--text-heading); font-weight: 700; text-decoration: none; font-size: 13.5px; }
    .topic-row a.title:hover { color: var(--luna-mid); }
    .topic-row .meta { color: var(--text-muted); font-size: 12px; margin-top: 2px; }
    .topic-row .time { color: var(--text-muted); font-size: 12px; white-space: nowrap; }

    .empty-state { padding: 32px 20px; text-align: center; color: var(--text-muted); font-size: 13.5px; }

    .right-info-panel { display: flex; flex-direction: column; gap: 20px; max-width: 300px; }
    .profile-mini { display: flex; align-items: center; gap: 10px; padding: 16px 20px; }
    .profile-mini .avatar { width: 40px; height: 40px; border-radius: 50%; background: var(--luna-mid); color: #fff; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 13px; }
    .profile-mini .name { font-weight: 700; color: var(--text-heading); font-size: 13.5px; }
    .profile-mini .role { color: var(--text-muted); font-size: 11.5px; }

    .announcement-banner { background: var(--luna-mid); color: #fff; border-radius: var(--radius-lg); padding: 18px 20px; }
    .announcement-banner .tag { display: flex; align-items: center; gap: 8px; text-transform: uppercase; font-size: 0.72rem; font-weight: 700; letter-spacing: 0.5px; opacity: 0.9; margin-bottom: 8px; }
    .announcement-banner .body { font-size: 0.85rem; font-weight: 500; line-height: 1.4; }

    .forum-layout { display: flex; gap: 20px; align-items: flex-start; }
    .forum-main { flex: 1; min-width: 0; }

    @media (max-width: 1024px) {
        .forum-layout { flex-direction: column; }
        .right-info-panel { max-width: 100%; }
    }
</style>

<div class="content-workspace">
    <div class="page-header">
        <p class="eyebrow">Community</p>
        <h1>Forum</h1>
    </div>

    <div class="forum-layout">
        <div class="forum-main">
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="label">Groups Joined</div>
                    <div class="value">{{ $joinedGroups->count() }}</div>
                </div>
                <div class="stat-card">
                    <div class="label">Total Topics</div>
                    <div class="value">{{ $topics->count() }}</div>
                </div>
            </div>

            <div class="groups-grid">
                @forelse($joinedGroups as $group)
                    <div class="group-card">
                        <div class="group-card-icon"><i class="fa-solid fa-users"></i></div>
                        <h5>{{ $group->GroupName }}</h5>
                        <p>{{ $group->Description ?? 'Joined discussion group' }}</p>
                        <a href="{{ route('groups.topics', $group) }}" class="btn btn-primary">
                            Open Group Forum <i class="fa-solid fa-arrow-right" style="font-size: 0.75rem;"></i>
                        </a>
                    </div>
                @empty
                    <div class="empty-state" style="grid-column: 1 / -1;">You have not joined any groups yet.</div>
                @endforelse
            </div>

            <div class="panel">
                <div class="panel-header"><h2>Latest Topics</h2></div>
                <div>
                    @forelse($topics as $topic)
                        <div class="topic-row">
                            <div>
                                <a href="{{ route('topics.show', $topic) }}" class="title">{{ $topic->Title }}</a>
                                <div class="meta">Created by {{ $topic->creator?->UserName ?? $topic->creator?->name ?? 'a member' }}</div>
                            </div>
                            <div class="time">{{ $topic->CreatedAt->diffForHumans() }}</div>
                        </div>
                    @empty
                        <div class="empty-state">No topics yet.</div>
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
                <div class="tag"><i class="fa-solid fa-bullhorn"></i> Announcement</div>
                <div class="body">Join groups to unlock topic discussions and replies.</div>
            </div>
        </div>
    </div>
</div>
@endsection