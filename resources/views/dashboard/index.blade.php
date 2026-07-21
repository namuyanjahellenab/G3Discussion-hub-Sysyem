@extends('layouts.app')

@section('content')
@php
    $displayName = Auth::user()->UserName ?? Auth::user()->name ?? 'Student User';
    $initials = Str::initials($displayName);
@endphp

<style>
    .content-workspace { padding: 28px; max-width: 1200px; }
    .page-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 24px; flex-wrap: wrap; gap: 12px; }
    .page-header p.eyebrow { text-transform: uppercase; color: var(--text-muted); font-size: 0.75rem; font-weight: 600; letter-spacing: 0.5px; margin: 0 0 4px 0; }
    .page-header h1 { font-size: 24px; font-weight: 800; margin: 0; }

    .stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 28px; }
    .stat-card { background: var(--surface-card); border: 1px solid var(--surface-border); border-top: 3px solid var(--luna-mid); border-radius: var(--radius-lg); padding: 18px 20px; box-shadow: var(--shadow-soft); }
    .stat-card .label { font-size: 12px; color: var(--text-muted); font-weight: 600; text-transform: uppercase; letter-spacing: 0.4px; }
    .stat-card .value { font-size: 26px; font-weight: 800; color: var(--text-heading); margin-top: 6px; }
    .stat-card .value small { font-size: 13px; font-weight: 600; color: var(--text-muted); }

    .panels-grid { display: grid; grid-template-columns: 1.4fr 1fr; gap: 20px; margin-bottom: 20px; }
    .panel { background: var(--surface-card); border: 1px solid var(--surface-border); border-radius: var(--radius-lg); overflow: hidden; box-shadow: var(--shadow-soft); }
    .panel-header { padding: 16px 20px; border-bottom: 1px solid var(--surface-border); display: flex; justify-content: space-between; align-items: center; }
    .panel-header h2 { font-size: 15px; font-weight: 700; margin: 0; }
    .panel-header a { font-size: 12.5px; color: var(--luna-mid); text-decoration: none; font-weight: 600; }

    .groups-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; padding: 20px; }
    .group-card { width: 100%; aspect-ratio: 1 / 1; box-sizing: border-box; background: var(--surface-bg); border: 1px solid var(--surface-border); border-radius: var(--radius-md); padding: 20px; display: flex; flex-direction: column; align-items: center; justify-content: center; text-align: center; }
    .group-card-icon { width: 40px; height: 40px; background: var(--luna-lightest); color: var(--luna-dark); border-radius: 10px; display: flex; align-items: center; justify-content: center; margin-bottom: 12px; }
    .group-card h5 { font-size: 1rem; font-weight: 700; margin: 0 0 2px 0; color: var(--text-heading); }
    .group-card p { color: var(--text-muted); font-size: 0.8rem; margin: 0 0 16px 0; }

    .notif-row { display: flex; align-items: flex-start; gap: 10px; padding: 13px 20px; border-bottom: 1px solid var(--surface-border); font-size: 13.5px; }
    .notif-row:last-child { border-bottom: none; }
    .activity-dot { width: 7px; height: 7px; border-radius: 50%; background: var(--luna-mid); margin-top: 6px; flex-shrink: 0; }
    .notif-row .notif-content { flex: 1; min-width: 0; }
    .notif-row .notif-type { font-weight: 700; color: var(--text-heading); white-space: normal; overflow-wrap: break-word; word-break: break-word; }
    .notif-row .meta { color: var(--text-muted); font-size: 12px; margin-top: 2px; white-space: normal; overflow-wrap: break-word; word-break: break-word; }

    .upcoming-quiz-card { padding: 20px; }
    .upcoming-quiz-card .title { font-weight: 700; color: var(--text-heading); font-size: 14px; }
    .upcoming-quiz-card .meta { color: var(--text-muted); font-size: 12.5px; margin-top: 4px; }
    .upcoming-quiz-card .btn { margin-top: 14px; }

    .btn { display: inline-flex; align-items: center; gap: 8px; padding: 0.55rem 1rem; border-radius: var(--radius-md); font-size: 13px; font-weight: 600; text-decoration: none; border: 1px solid transparent; box-shadow: var(--shadow-soft); }
    .btn-primary { background: var(--luna-mid); color: #fff; }
    .btn-primary:hover { background: var(--luna-dark); color: #fff; }

    .empty-state { padding: 32px 20px; text-align: center; color: var(--text-muted); font-size: 13.5px; }

    .side-panel { display: flex; flex-direction: column; gap: 20px; }
    .profile-mini { display: flex; align-items: center; gap: 10px; padding: 16px 20px; }
    .profile-mini .avatar { width: 40px; height: 40px; border-radius: 50%; background: var(--luna-mid); color: #fff; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 13px; }
    .profile-mini .name { font-weight: 700; color: var(--text-heading); font-size: 13.5px; }
    .profile-mini .role { color: var(--text-muted); font-size: 11.5px; }

    .announcement-banner { background: var(--luna-mid); color: #fff; border-radius: var(--radius-lg); padding: 18px 20px; }
    .announcement-banner .tag { display: flex; align-items: center; gap: 8px; text-transform: uppercase; font-size: 0.72rem; font-weight: 700; letter-spacing: 0.5px; opacity: 0.9; margin-bottom: 8px; }
    .announcement-banner .body { font-size: 0.85rem; font-weight: 500; line-height: 1.4; }

    @media (max-width: 1024px) {
        .panels-grid { grid-template-columns: 1fr; }
        .stats-grid { grid-template-columns: repeat(2, 1fr); }
    }
</style>

<div class="content-workspace">
    <div class="page-header">
        <div>
            <p class="eyebrow">Overview</p>
            <h1>My Dashboard</h1>
        </div>
    </div>

    <div class="stats-grid">
        <div class="stat-card">
            <div class="label">Groups Joined</div>
            <div class="value">{{ $joined_groups->count() }}</div>
        </div>
        <div class="stat-card">
            <div class="label">Unread Notifications</div>
            <div class="value">{{ $notificationsCount }}</div>
        </div>
        <div class="stat-card">
            <div class="label">Participation</div>
            <div class="value">{{ $snapshot['participation'] }}<small> / 10</small></div>
        </div>
        <div class="stat-card">
            <div class="label">Quiz Average</div>
            <div class="value">{{ $snapshot['quiz_average'] ?? '—' }}</div>
        </div>
    </div>

    <div class="panels-grid">
        <div style="display: flex; flex-direction: column; gap: 20px;">
            <section class="panel">
                <div class="panel-header">
                    <h2>My Groups</h2>
                    <a href="{{ route('groups.index') }}">See all</a>
                </div>
                <div class="groups-grid">
                    @forelse($joined_groups as $group)
                        <div class="group-card">
                            <div class="group-card-icon"><i class="fa-solid fa-users"></i></div>
                            <h5>{{ $group->GroupName }}</h5>
                            <p>{{ $group->member_count ?? 0 }} members</p>
                            <a href="{{ route('groups.topics', $group) }}" class="btn btn-primary">
                                View Forum <i class="fa-solid fa-arrow-right" style="font-size: 0.75rem;"></i>
                            </a>
                        </div>
                    @empty
                        <div class="empty-state" style="grid-column: 1 / -1;">You haven't joined any groups yet.</div>
                    @endforelse
                </div>
            </section>

            <section class="panel">
                <div class="panel-header"><h2>Notifications</h2></div>
                <div>
                    @forelse($notifications->take(6) as $notification)
                        <div class="notif-row">
                            <span class="activity-dot"></span>
                            <div class="notif-content">
                                <div class="notif-type">{{ $notification->Type }}</div>
                                <div class="meta">{{ $notification->Message }}</div>
                                <div class="meta">{{ $notification->CreatedAt->diffForHumans() }}</div>
                            </div>
                        </div>
                    @empty
                        <div class="empty-state">No notifications yet.</div>
                    @endforelse
                </div>
            </section>
        </div>

        <div class="side-panel">
            <section class="panel">
                <div class="profile-mini">
                    <div class="avatar">{{ strtoupper($initials ?: 'S') }}</div>
                    <div>
                        <div class="name">{{ $displayName }}</div>
                        <div class="role">Student Account</div>
                    </div>
                </div>
            </section>

            <section class="panel upcoming-quiz-card">
                <h2 style="font-size: 15px; font-weight: 700; margin: 0 0 4px 0;">Upcoming Quiz</h2>
                @if($upcomingQuiz)
                    <div class="title">{{ $upcomingQuiz->Title }}</div>
                    <div class="meta">{{ $upcomingQuiz->StartTime->format('d M Y, h:i A') }} · {{ $upcomingQuiz->Duration }} min</div>
                    <a href="{{ url('/quizzes') }}" class="btn btn-primary">View Details</a>
                @else
                    <div class="meta" style="margin-top: 8px;">No upcoming quizzes scheduled.</div>
                @endif
            </section>

            @if($latestAnnouncement)
                <div class="announcement-banner">
                    <div class="tag"><i class="fa-solid fa-bullhorn"></i> Announcement{{ $latestAnnouncement->group ? " — {$latestAnnouncement->group->GroupName}" : '' }}</div>
                    <div class="body">{{ $latestAnnouncement->Message }}</div>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
