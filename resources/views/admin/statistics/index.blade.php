@extends('layouts.app')

@section('content')
<div class="d-flex justify-content-between align-items-start mb-4 flex-wrap gap-2">
    <div>
        <h2 class="fw-bold mb-1">Statistics Dashboard</h2>
        <p class="text-muted mb-0">Comprehensive platform analytics and user engagement data.</p>
    </div>

    <form method="GET" action="{{ route('admin.statistics') }}" class="d-flex align-items-center gap-2">
        <label for="group_id" class="text-uppercase small text-muted fw-semibold mb-0">Group Filter</label>
        <select name="group_id" id="group_id" class="form-select form-select-sm" onchange="this.form.submit()" style="min-width: 160px;">
            <option value="">All Groups</option>
            @foreach($groups as $group)
                <option value="{{ $group->GroupID }}" {{ (string)$groupId === (string)$group->GroupID ? 'selected' : '' }}>
                    {{ $group->GroupName }}
                </option>
            @endforeach
        </select>
    </form>
</div>

{{-- Stat cards --}}
<div class="row g-3 mb-4">
    <div class="col-6 col-lg-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <div class="stat-icon bg-primary bg-opacity-10 text-primary">
                        <i class="bi bi-file-earmark-text-fill"></i>
                    </div>
                </div>
                <div class="fs-2 fw-bold">{{ $postsToday }}</div>
                <div class="text-muted small text-uppercase">Posts Today</div>
            </div>
        </div>
    </div>

    <div class="col-6 col-lg-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <div class="stat-icon bg-secondary bg-opacity-10 text-secondary">
                        <i class="bi bi-question-circle-fill"></i>
                    </div>
                </div>
                <div class="fs-2 fw-bold">{{ $openQuestions }}</div>
                <div class="text-muted small text-uppercase">Open Questions</div>
            </div>
        </div>
    </div>

    <div class="col-6 col-lg-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <div class="stat-icon bg-primary bg-opacity-10 text-primary">
                        <i class="bi bi-clock-history"></i>
                    </div>
                </div>
                <div class="fs-2 fw-bold">{{ $quizzesRun }}</div>
                <div class="text-muted small text-uppercase">Quizzes Run</div>
            </div>
        </div>
    </div>
</div>

{{-- Account Status Overview --}}
<h6 class="text-uppercase text-muted small fw-bold mb-2">Account Status Overview</h6>
<div class="row g-3 mb-4">
    <div class="col-6 col-lg">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="stat-icon bg-primary bg-opacity-10 text-primary mb-3">
                    <i class="bi bi-people-fill"></i>
                </div>
                <div class="fs-2 fw-bold">{{ $totalUsers }}</div>
                <div class="text-muted small text-uppercase">Total Users</div>
            </div>
        </div>
    </div>

    <div class="col-6 col-lg">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="stat-icon bg-primary bg-opacity-10 text-primary mb-3">
                    <i class="bi bi-mortarboard-fill"></i>
                </div>
                <div class="fs-2 fw-bold">{{ $totalStudents }}</div>
                <div class="text-muted small text-uppercase">Students</div>
            </div>
        </div>
    </div>

    <div class="col-6 col-lg">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="stat-icon bg-primary bg-opacity-10 text-primary mb-3">
                    <i class="bi bi-person-workspace"></i>
                </div>
                <div class="fs-2 fw-bold">{{ $totalLecturers }}</div>
                <div class="text-muted small text-uppercase">Lecturers</div>
            </div>
        </div>
    </div>

    <div class="col-6 col-lg">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="stat-icon bg-primary bg-opacity-10 text-primary mb-3">
                    <i class="bi bi-shield-lock-fill"></i>
                </div>
                <div class="fs-2 fw-bold">{{ $totalAdmins }}</div>
                <div class="text-muted small text-uppercase">Admins</div>
            </div>
        </div>
    </div>

    <div class="col-6 col-lg">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="stat-icon bg-success bg-opacity-10 text-success mb-3">
                    <i class="bi bi-person-check-fill"></i>
                </div>
                <div class="fs-2 fw-bold">{{ $activeStudents }}</div>
                <div class="text-muted small text-uppercase">Active Students</div>
            </div>
        </div>
    </div>

    <div class="col-6 col-lg">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="stat-icon bg-secondary bg-opacity-10 text-secondary mb-3">
                    <i class="bi bi-person-dash-fill"></i>
                </div>
                <div class="fs-2 fw-bold">{{ $inactiveStudents }}</div>
                <div class="text-muted small text-uppercase">Inactive Students</div>
            </div>
        </div>
    </div>
</div>

{{-- Chart + Top Members --}}
<div class="row g-3 mb-4">
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h5 class="fw-bold mb-0">Posts Per Day - This Week</h5>
                    <span class="small text-muted"><span class="legend-dot"></span> Volume</span>
                </div>

                @php $max = max(1, collect($postsPerDay)->max('count')); @endphp
                <div class="d-flex align-items-end justify-content-between bar-chart" style="height: 200px;">
                    @foreach($postsPerDay as $day)
                        <div class="d-flex flex-column align-items-center justify-content-end h-100" style="width: 12%;">
                            <div class="bar-value small text-muted mb-1">{{ $day['count'] }}</div>
                            <div class="bar-fill w-100" style="height: {{ ($day['count'] / $max) * 100 }}%;"></div>
                            <div class="small text-muted mt-2">{{ $day['label'] }}</div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body d-flex flex-column">
                @php
                    $topStudentsHeading = $groupId
                        ? ($groups->firstWhere('GroupID', $groupId)?->GroupName ?? 'Top Students')
                        : 'Top Students';
                @endphp
                <h5 class="fw-bold mb-3">{{ $topStudentsHeading }}</h5>
                <div class="flex-grow-1">
                    @forelse($topMembers->take(5) as $index => $member)
                        @php
                            $palette = ['primary', 'success', 'warning'];
                            $color = $palette[$index % 3];
                            $initials = Str::initials($member->UserName);
                        @endphp
                        <div class="d-flex align-items-center justify-content-between mb-3">
                            <div class="d-flex align-items-center gap-2">
                                <div class="avatar-badge bg-{{ $color }} bg-opacity-25 text-{{ $color }}">
                                    {{ $initials }}
                                </div>
                                <span class="fw-medium">{{ $member->UserName }}</span>
                            </div>
                            <span class="fw-semibold text-muted">{{ number_format($member->TotalScore, 0) }} pts</span>
                        </div>
                    @empty
                        <p class="text-muted small mb-0">No quiz scores yet.</p>
                    @endforelse
                </div>
                <a href="{{ Route::has('admin.blacklist') ? route('admin.blacklist') : '#' }}" class="text-danger text-decoration-none fw-semibold small mt-2">
    <i class="bi bi-slash-circle"></i> Blacklisted: {{ $blacklistedCount }} Users
</a>
            </div>
        </div>
    </div>
</div>

{{-- Group Participation Summary --}}
<div class="card border-0 shadow-sm mb-3">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="fw-bold mb-0">Group Participation Summary</h5>
            <a href="{{ route('admin.statistics.export', ['group_id' => $groupId]) }}" class="text-primary text-decoration-none small fw-semibold">
                Export Report <i class="bi bi-download"></i>
            </a>
        </div>

        <div class="table-responsive">
            <table class="table align-middle">
                <thead>
                    <tr class="text-uppercase small text-muted">
                        <th>Group</th>
                        <th>Total<br>Students</th>
                        <th>Active<br>Students</th>
                        <th>Total<br>Posts</th>
                        <th>Students<br>Participation</th>
                        <th>Quiz<br>Participation</th>
                        <th>Average<br>Score</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($groupSummary as $row)
                        <tr>
                            <td class="text-primary fw-semibold">{{ $row['GroupName'] }}</td>
                            <td>{{ $row['TotalStudents'] }}</td>
                            <td>{{ $row['ActiveUsers'] }}</td>
                            <td>{{ number_format($row['TotalPosts']) }}</td>
                            <td>{{ $row['StudentsParticipation'] }}</td>
                            <td>{{ $row['QuizCompletion'] }}%</td>
                            <td class="fw-semibold">{{ number_format($row['AverageScore'], 2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center text-muted py-3">No groups found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="alert alert-light border d-flex align-items-center gap-2 small text-muted">
    <i class="bi bi-info-circle"></i>
    User activity trends updated daily. Filter by group using dropdown above.
</div>
@endsection

@push('styles')
<style>
    .stat-icon {
        width: 40px;
        height: 40px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.1rem;
    }
    .avatar-badge {
        width: 32px;
        height: 32px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 600;
        font-size: 0.75rem;
    }
    .bar-fill {
        background-color: var(--luna-mid);
        border-radius: 4px 4px 0 0;
        min-height: 4px;
    }
    .legend-dot {
        display: inline-block;
        width: 8px;
        height: 8px;
        border-radius: 50%;
        background-color: var(--luna-mid);
        margin-right: 4px;
    }

    /* This page only: pin the sidebar to the viewport with position:fixed
       (site-wide it's position:sticky - see .app-sidebar-wrapper in
       admin-theme.css). Fixed takes it out of the normal flex flow, so
       without the matching margin-left below the content column would
       start at x:0 and render underneath it. */
    .app-sidebar-wrapper {
        position: fixed;
        top: 0;
        left: 0;
        height: 100vh;
    }

    .flex-fill {
        /* 260px clears the fixed sidebar itself, +32px is breathing room
           between the sidebar and the content - this just shifts the content
           block right into the empty space that was left over. max-width is
           calc'd off the viewport (rather than a fixed px cap) so the content
           extends to near the right edge instead of leaving a dead gap. */
        margin-left: 292px;
        max-width: calc(100% - 292px - 32px);
        overflow-x: hidden;
        transition: margin-left 0.2s ease;
    }

    /* Belt-and-braces: guarantee this screen never grows a horizontal
       scrollbar (only vertical), regardless of anything inside .flex-fill
       above that might otherwise poke past its max-width. */
    html, body {
        overflow-x: hidden;
    }

    body.sidebar-collapsed .flex-fill {
        margin-left: 0;
        max-width: calc(100% - 32px);
    }

    @media (prefers-reduced-motion: reduce) {
        .flex-fill { transition: none; }
    }

    /* Trim the stat cards and the graph so the dashboard fits within the
       viewport width without horizontal scrolling, now that the content
       column is narrower (fixed sidebar reserves 260px - see above). */
    .row.g-3 {
        --bs-gutter-x: 0.5rem;
    }

    .card-body {
        padding: 0.75rem 0.85rem;
    }

    .stat-icon {
        width: 28px;
        height: 28px;
        font-size: 0.85rem;
    }

    .fs-2 {
        font-size: 1.25rem;
    }
</style>
@endpush
