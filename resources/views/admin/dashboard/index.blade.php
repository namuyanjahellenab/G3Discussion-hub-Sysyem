@extends('layouts.app')

@section('content')
<style>
    /* Same date-badge treatment as the lecturer dashboard - shows today's
       date, refreshed on every page load since it's rendered server-side. */
    .date-badge { display: flex; align-items: center; gap: 8px; background: var(--surface-card); border: 1px solid var(--surface-border); border-radius: var(--radius-md); padding: 10px 16px; font-size: 13px; font-weight: 600; color: var(--text-heading); box-shadow: var(--shadow-soft); }
    .date-badge i { color: var(--luna-mid); }
</style>
<div class="container-fluid px-4">
    <div class="d-flex justify-content-between align-items-start mb-4 flex-wrap gap-2">
        <div>
            <h2 class="fw-bold mb-1">Admin Panel</h2>
            <p class="text-muted mb-0">Global system overview and real-time community activity.</p>
        </div>
        <div class="d-flex align-items-center gap-2">
            <div class="date-badge">
                <i class="fa-regular fa-calendar"></i> {{ now()->format('l, M j, Y') }}
            </div>
            <a href="{{ route('admin.statistics.export') }}" class="btn btn-outline-secondary btn-sm">
                <i class="fa-solid fa-arrow-down-to-line"></i> Export Data
            </a>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-6 col-md-4 col-lg">
            <a href="{{ route('admin.statistics') }}" class="text-decoration-none">
                <div class="card shadow-sm h-100 border-0">
                    <div class="card-body">
                        <div class="rounded-2 bg-primary bg-opacity-10 text-primary d-inline-flex align-items-center justify-content-center mb-2"
                             style="width:36px;height:36px;">
                            <i class="fa-solid fa-chart-column"></i>
                        </div>
                        <div class="fw-bold text-dark small">STATISTICS DASHBOARD</div>
                        <div class="text-muted" style="font-size:0.75rem;">View growth metrics and traffic.</div>
                    </div>
                </div>
            </a>
        </div>
        <div class="col-6 col-md-4 col-lg">
            <a href="{{ route('admin.blacklist') }}" class="text-decoration-none">
                <div class="card shadow-sm h-100 border-0">
                    <div class="card-body">
                        <div class="rounded-2 bg-danger bg-opacity-10 text-danger d-inline-flex align-items-center justify-content-center mb-2"
                             style="width:36px;height:36px;">
                            <i class="fa-solid fa-shield-halved"></i>
                        </div>
                        <div class="fw-bold text-dark small">BLACKLIST MANAGEMENT</div>
                        <div class="text-muted" style="font-size:0.75rem;">Manage banned users and IPs.</div>
                    </div>
                </div>
            </a>
        </div>
        <div class="col-6 col-md-4 col-lg">
            <a href="{{ route('admin.groups.index') }}" class="text-decoration-none">
                <div class="card shadow-sm h-100 border-0">
                    <div class="card-body">
                        <div class="rounded-2 bg-primary bg-opacity-10 text-primary d-inline-flex align-items-center justify-content-center mb-2"
                             style="width:36px;height:36px;">
                            <i class="fa-solid fa-people-group"></i>
                        </div>
                        <div class="fw-bold text-dark small">GROUPS AND MEMBERS</div>
                        <div class="text-muted" style="font-size:0.75rem;">Coordinate study cohorts.</div>
                    </div>
                </div>
            </a>
        </div>
        <div class="col-6 col-md-4 col-lg">
            <a href="{{ route('announcements.index') }}" class="text-decoration-none">
                <div class="card shadow-sm h-100 border-0">
                    <div class="card-body">
                        <div class="rounded-2 bg-secondary bg-opacity-10 text-secondary d-inline-flex align-items-center justify-content-center mb-2"
                             style="width:36px;height:36px;">
                            <i class="fa-solid fa-bullhorn"></i>
                        </div>
                        <div class="fw-bold text-dark small">ANNOUNCEMENTS</div>
                        <div class="text-muted" style="font-size:0.75rem;">Broadcast system updates.</div>
                    </div>
                </div>
            </a>
        </div>
        <div class="col-6 col-md-4 col-lg">
            <a href="{{ route('settings.index') }}" class="text-decoration-none">
                <div class="card shadow-sm h-100 border-0">
                    <div class="card-body">
                        <div class="rounded-2 bg-secondary bg-opacity-10 text-secondary d-inline-flex align-items-center justify-content-center mb-2"
                             style="width:36px;height:36px;">
                            <i class="fa-solid fa-gear"></i>
                        </div>
                        <div class="fw-bold text-dark small">SYSTEM SETTINGS</div>
                        <div class="text-muted" style="font-size:0.75rem;">Configure platform defaults.</div>
                    </div>
                </div>
            </a>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="card shadow-sm border-0">
                <div class="card-body">
                    <div class="text-muted small">Total Users</div>
                    <div class="fs-4 fw-bold">{{ number_format($totalUsers) }}</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card shadow-sm border-0">
                <div class="card-body">
                    <div class="text-muted small">Students</div>
                    <div class="fs-4 fw-bold">{{ number_format($totalStudents) }}</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card shadow-sm border-0">
                <div class="card-body">
                    <div class="text-muted small">Lecturers</div>
                    <div class="fs-4 fw-bold">{{ number_format($totalLecturers) }}</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card shadow-sm border-0">
                <div class="card-body">
                    <div class="text-muted small">Groups</div>
                    <div class="fs-4 fw-bold">{{ number_format($totalGroups) }}</div>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm border-0">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <span class="fw-bold"><i class="fa-solid fa-bolt text-primary"></i> Live Activity Log</span>
            <span class="badge bg-danger-subtle text-danger">LIVE</span>
        </div>
        <div class="card-body p-0">
            <ul class="list-group list-group-flush">
                @forelse($recentTopics as $topic)
                    <li class="list-group-item d-flex align-items-center gap-2">
                        <span class="badge bg-secondary-subtle fw-semibold" title="{{ $topic->CreatedAt->format('M j, Y g:i A') }}" style="font-size: 0.72rem;">{{ $topic->CreatedAt->diffForHumans() }}</span>
                        <span>
                            <strong>{{ $topic->creator->UserName ?? 'Unknown' }}</strong>
                            posted a new topic: <em>"{{ $topic->Title }}"</em>
                        </span>
                    </li>
                @empty
                    <li class="list-group-item text-muted text-center py-4">No recent activity yet.</li>
                @endforelse
            </ul>
        </div>
    </div>
</div>
@endsection