@extends('layouts.app')

@section('content')
@php
    $displayName = auth()->user()->UserName ?? auth()->user()->name ?? 'Lecturer';
    $initials = Str::initials($displayName);

    $statusMap = [
        'answered' => ['label' => 'Answered', 'badge' => 'bg-success-subtle'],
        'discussion' => ['label' => 'Discussion', 'badge' => 'bg-warning'],
        'open' => ['label' => 'Open', 'badge' => 'bg-secondary-subtle'],
    ];
@endphp

<style>
    .content-workspace { padding: 28px; }
    .page-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 24px; flex-wrap: wrap; gap: 12px; }
    .page-header p.eyebrow { text-transform: uppercase; color: var(--text-muted); font-size: 0.75rem; font-weight: 600; letter-spacing: 0.5px; margin: 0 0 4px 0; }
    .page-header h1 { font-size: 24px; font-weight: 800; margin: 0; }
    .page-header p.sub { color: var(--text-muted); font-size: 13.5px; margin-top: 4px; }

    .date-badge { display: flex; align-items: center; gap: 8px; background: var(--surface-card); border: 1px solid var(--surface-border); border-radius: var(--radius-md); padding: 10px 16px; font-size: 13px; font-weight: 600; color: var(--text-heading); box-shadow: var(--shadow-soft); }
    .date-badge i { color: var(--luna-mid); }

    .stats-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; margin-bottom: 20px; }
    .stat-card { background: var(--surface-card); border: 1px solid var(--surface-border); border-top: 3px solid var(--luna-mid); border-radius: var(--radius-lg); padding: 18px 20px; box-shadow: var(--shadow-soft); }
    .stat-card-top { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 10px; }
    .stat-card .label { font-size: 12.5px; color: var(--text-muted); font-weight: 600; }
    .stat-icon { width: 32px; height: 32px; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 13px; }
    .stat-icon.blue { background: var(--luna-lightest); color: var(--luna-dark); }
    .stat-icon.pink { background: #F4EBF7; color: #8E6AAE; }
    .stat-icon.amber { background: var(--accent-amber-bg); color: var(--accent-amber); }
    .stat-icon.red { background: var(--accent-danger-bg); color: var(--accent-danger); }
    .stat-card .value { font-size: 26px; font-weight: 800; color: var(--text-heading); }

    .actions-grid { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 16px; margin-bottom: 24px; }
    .action-card { border-radius: var(--radius-lg); padding: 20px; display: flex; align-items: center; justify-content: space-between; text-decoration: none; transition: transform 0.15s ease, box-shadow 0.15s ease; min-height: 88px; box-shadow: var(--shadow-soft); }
    .action-card:hover { transform: translateY(-2px); box-shadow: var(--shadow-medium); }
    .action-card.primary { background: linear-gradient(135deg, var(--luna-mid), var(--luna-dark)); color: #fff; }
    .action-card.light { background: var(--surface-card); border: 1px solid var(--surface-border); color: var(--text-heading); }
    .action-card .left { display: flex; align-items: center; gap: 14px; }
    .action-card .icon-box { width: 42px; height: 42px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 16px; flex-shrink: 0; }
    .action-card.primary .icon-box { background: rgba(255,255,255,0.18); color: #fff; }
    .action-card.light .icon-box { background: var(--luna-lightest); color: var(--luna-dark); }
    .action-card .title { font-size: 14px; font-weight: 700; }
    .action-card .subtitle { font-size: 12px; opacity: 0.85; margin-top: 2px; font-weight: 500; }
    .action-card .chevron { font-size: 14px; opacity: 0.8; }

    .content-layout { display: flex; gap: 20px; align-items: flex-start; }
    .content-main { flex: 1; min-width: 0; }
    .right-info-panel { display: flex; flex-direction: column; gap: 20px; max-width: 300px; }

    .panel { background: var(--surface-card); border: 1px solid var(--surface-border); border-radius: var(--radius-lg); overflow: hidden; box-shadow: var(--shadow-soft); }
    .panel-header { padding: 16px 20px; border-bottom: 1px solid var(--surface-border); display: flex; justify-content: space-between; align-items: center; }
    .panel-header h2 { font-size: 15px; font-weight: 700; margin: 0; color: var(--text-heading); }

    table.discussion-table { width: 100%; border-collapse: collapse; }
    .discussion-table thead th { text-align: left; font-size: 11.5px; text-transform: uppercase; letter-spacing: 0.4px; color: var(--text-muted); font-weight: 700; padding: 12px 20px; border-bottom: 1px solid var(--surface-border); }
    .discussion-table thead th.actions-col { text-align: right; }
    .discussion-table tbody td { padding: 14px 20px; border-bottom: 1px solid var(--surface-border); font-size: 13.5px; vertical-align: middle; }
    .discussion-table tbody tr:last-child td { border-bottom: none; }
    .discussion-table .title { font-weight: 700; color: var(--text-heading); }
    .discussion-table .started-by { color: var(--text-muted); font-size: 12px; margin-top: 2px; }
    .row-actions { display: flex; gap: 14px; justify-content: flex-end; }
    .row-actions a { color: var(--text-muted); text-decoration: none; font-size: 13px; }
    .row-actions a:hover { color: var(--luna-mid); }

    .profile-mini { display: flex; align-items: center; gap: 10px; padding: 16px 20px; }
    .profile-mini .avatar { width: 40px; height: 40px; border-radius: 50%; background: var(--luna-mid); color: #fff; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 13px; }
    .profile-mini .name { font-weight: 700; color: var(--text-heading); font-size: 13.5px; }
    .profile-mini .role { color: var(--text-muted); font-size: 11.5px; }

    .course-row { display: flex; justify-content: space-between; align-items: center; padding: 12px 20px; border-bottom: 1px solid var(--surface-border); font-size: 13px; }
    .course-row:last-child { border-bottom: none; }
    .course-row .name { font-weight: 600; color: var(--text-heading); }
    .course-row .count { color: var(--text-muted); font-size: 12px; }

    .empty-state { padding: 40px 20px; text-align: center; color: var(--text-muted); font-size: 13.5px; }

    @media (max-width: 1024px) {
        .stats-grid { grid-template-columns: repeat(2, 1fr); }
        .actions-grid { grid-template-columns: 1fr; }
        .content-layout { flex-direction: column; }
        .right-info-panel { max-width: 100%; }
    }
</style>

<div class="content-workspace">
    <div class="page-header">
        <div>
            <p class="eyebrow">Overview</p>
            <h1>Welcome back, {{ $displayName }}</h1>
            <p class="sub">Here's what's happening across your {{ $activeCoursesCount }} active {{ Str::plural('course', $activeCoursesCount) }} today.</p>
        </div>
        <div class="date-badge">
            <i class="fa-regular fa-calendar"></i> {{ now()->format('l, M j, Y') }}
        </div>
    </div>

    @if(session('status'))
        <div class="alert alert-success" style="margin-bottom: 20px;">{{ session('status') }}</div>
    @endif

    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-card-top">
                <div class="label">Total Students</div>
                <div class="stat-icon blue"><i class="fa-solid fa-users"></i></div>
            </div>
            <div class="value">{{ number_format($totalStudents) }}</div>
        </div>

        <div class="stat-card">
            <div class="stat-card-top">
                <div class="label">Active Discussions</div>
                <div class="stat-icon pink"><i class="fa-solid fa-comments"></i></div>
            </div>
            <div class="value">{{ number_format($activeDiscussions) }}</div>
        </div>

        <div class="stat-card">
            <div class="stat-card-top">
                <div class="label">Unanswered Questions</div>
                <div class="stat-icon amber"><i class="fa-solid fa-circle-question"></i></div>
            </div>
            <div class="value">{{ number_format($unansweredQuestions) }}</div>
        </div>
    </div>

    <div class="actions-grid">
        <a href="{{ route('forum.index') }}" class="action-card primary">
            <div class="left">
                <div class="icon-box"><i class="fa-solid fa-plus"></i></div>
                <div><div class="title">Start a Discussion</div></div>
            </div>
            <i class="fa-solid fa-chevron-right chevron"></i>
        </a>

        <a href="{{ route('announcements.create') }}" class="action-card primary">
            <div class="left">
                <div class="icon-box"><i class="fa-solid fa-bullhorn"></i></div>
                <div><div class="title">Create Announcement</div></div>
            </div>
            <i class="fa-solid fa-chevron-right chevron"></i>
        </a>

        <a href="{{ route('marks.index') }}" class="action-card light">
            <div class="left">
                <div class="icon-box"><i class="fa-solid fa-chart-simple"></i></div>
                <div>
                    <div class="title">View Academic Reports</div>
                    <div class="subtitle">Analyze quiz &amp; participation results</div>
                </div>
            </div>
            <i class="fa-solid fa-chevron-right chevron"></i>
        </a>
    </div>

    <div class="content-layout">
        <div class="content-main">
            <section class="panel">
                <div class="panel-header">
                    <h2>Recent Discussions</h2>
                    <a href="{{ route('forum.index') }}" class="btn btn-primary btn-sm">See All</a>
                </div>

                <table class="discussion-table">
                    <thead>
                        <tr>
                            <th>Title</th>
                            <th>Course</th>
                            <th>Replies</th>
                            <th>Status</th>
                            <th class="actions-col">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($recentDiscussions as $discussion)
                            @php $meta = $statusMap[$discussion->Status] ?? $statusMap['open']; @endphp
                            <tr>
                                <td>
                                    <div class="title">{{ $discussion->Title }}</div>
                                    <div class="started-by">Started by {{ $discussion->creator?->UserName ?? $discussion->creator?->name ?? 'a member' }}</div>
                                </td>
                                <td><span class="badge bg-secondary-subtle">{{ $discussion->group?->GroupName ?? '—' }}</span></td>
                                <td>{{ $discussion->posts_count }}</td>
                                <td><span class="badge {{ $meta['badge'] }}">{{ $meta['label'] }}</span></td>
                                <td>
                                    <div class="row-actions">
                                        <a href="{{ route('topics.show', $discussion) }}" title="View"><i class="fa-regular fa-eye"></i></a>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5">
                                    <div class="empty-state">No discussions yet in your courses.</div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </section>
        </div>

        <div class="right-info-panel">
            <section class="panel">
                <div class="profile-mini">
                    <div class="avatar">{{ strtoupper($initials ?: 'L') }}</div>
                    <div>
                        <div class="name">Dr. {{ $displayName }}</div>
                        <div class="role">Lecturer Account</div>
                    </div>
                </div>
            </section>

            <section class="panel">
                <div class="panel-header"><h2>Your Courses</h2></div>
                @forelse($courses as $course)
                    <div class="course-row">
                        <span class="name">{{ $course->GroupName }}</span>
                        <span class="count">{{ $course->student_count }} {{ Str::plural('student', $course->student_count) }}</span>
                    </div>
                @empty
                    <div class="empty-state">No courses yet — schedule a quiz for a group to see it here.</div>
                @endforelse
            </section>
        </div>
    </div>
</div>
@endsection
