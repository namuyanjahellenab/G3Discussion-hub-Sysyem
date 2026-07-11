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
    <div class="col-6 col-lg-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <div class="stat-icon bg-primary bg-opacity-10 text-primary">
                        <i class="bi bi-people-fill"></i>
                    </div>
                    @php $cls = $activeUsersChange > 0 ? 'success' : ($activeUsersChange < 0 ? 'danger' : 'secondary'); @endphp
                    <span class="badge bg-{{ $cls }}-subtle text-{{ $cls }} fw-semibold">
                        {{ $activeUsersChange > 0 ? '+' : '' }}{{ $activeUsersChange }}%
                    </span>
                </div>
                <div class="fs-2 fw-bold">{{ $activeUsersToday }}</div>
                <div class="text-muted small text-uppercase">Active Users</div>
            </div>
        </div>
    </div>

    <div class="col-6 col-lg-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <div class="stat-icon bg-primary bg-opacity-10 text-primary">
                        <i class="bi bi-file-earmark-text-fill"></i>
                    </div>
                    @php $cls = $postsChange > 0 ? 'success' : ($postsChange < 0 ? 'danger' : 'secondary'); @endphp
                    <span class="badge bg-{{ $cls }}-subtle text-{{ $cls }} fw-semibold">
                        {{ $postsChange > 0 ? '+' : '' }}{{ $postsChange }}%
                    </span>
                </div>
                <div class="fs-2 fw-bold">{{ $postsToday }}</div>
                <div class="text-muted small text-uppercase">Posts Today</div>
            </div>
        </div>
    </div>

    <div class="col-6 col-lg-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <div class="stat-icon bg-secondary bg-opacity-10 text-secondary">
                        <i class="bi bi-question-circle-fill"></i>
                    </div>
                    <span class="badge bg-danger-subtle text-danger fw-semibold">Open</span>
                </div>
                <div class="fs-2 fw-bold">{{ $openQuestions }}</div>
                <div class="text-muted small text-uppercase">Open Questions</div>
            </div>
        </div>
    </div>

    <div class="col-6 col-lg-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <div class="stat-icon bg-primary bg-opacity-10 text-primary">
                        <i class="bi bi-clock-history"></i>
                    </div>
                    <span class="badge bg-secondary-subtle text-secondary fw-semibold">Stable</span>
                </div>
                <div class="fs-2 fw-bold">{{ $quizzesRun }}</div>
                <div class="text-muted small text-uppercase">Quizzes Run</div>
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
                <h5 class="fw-bold mb-3">Top Members</h5>
                <div class="flex-grow-1">
                    @forelse($topMembers->take(3) as $index => $member)
                        @php
                            $palette = ['primary', 'success', 'warning'];
                            $color = $palette[$index % 3];
                            $initials = collect(explode(' ', trim($member->UserName)))->map(fn($p) => strtoupper(substr($p, 0, 1)))->join('');
                        @endphp
                        <div class="d-flex align-items-center justify-content-between mb-3">
                            <div class="d-flex align-items-center gap-2">
                                <div class="avatar-badge bg-{{ $color }} bg-opacity-25 text-{{ $color }}">
                                    {{ $initials }}
                                </div>
                                <span class="fw-medium">{{ $member->UserName }}</span>
                            </div>
                            <span class="fw-semibold text-muted">{{ number_format($member->ParticipationScore, 0) }} pts</span>
                        </div>
                    @empty
                        <p class="text-muted small mb-0">No participation data yet.</p>
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
                        <th>Total Posts</th>
                        <th>Active Users</th>
                        <th style="width: 220px;">Quiz Completion %</th>
                        <th>Flagged Content</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($groupSummary as $row)
                        <tr>
                            <td class="text-primary fw-semibold">{{ $row['GroupName'] }}</td>
                            <td>{{ number_format($row['TotalPosts']) }}</td>
                            <td>{{ $row['ActiveUsers'] }}</td>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <div class="progress flex-grow-1" style="height: 6px;">
                                        <div class="progress-bar bg-primary" style="width: {{ $row['QuizCompletion'] }}%"></div>
                                    </div>
                                    <span class="small text-muted">{{ $row['QuizCompletion'] }}%</span>
                                </div>
                            </td>
                            <td class="{{ $row['FlaggedContent'] > 0 ? 'text-danger fw-semibold' : 'text-muted' }}">
                                {{ $row['FlaggedContent'] }}
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-muted py-3">No groups found.</td></tr>
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
        background-color: #2451e0;
        border-radius: 4px 4px 0 0;
        min-height: 4px;
    }
    .legend-dot {
        display: inline-block;
        width: 8px;
        height: 8px;
        border-radius: 50%;
        background-color: #2451e0;
        margin-right: 4px;
    }
</style>
@endpush
