@extends('layouts.app')

@section('content')
<style>
    .content-workspace { padding: 28px; }
    .page-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 24px; flex-wrap: wrap; gap: 12px; }
    .page-header p.eyebrow { text-transform: uppercase; color: var(--text-muted); font-size: 0.75rem; font-weight: 600; letter-spacing: 0.5px; margin: 0 0 4px 0; }
    .page-header h1 { font-size: 24px; font-weight: 800; margin: 0; }
    .page-header p.sub { color: var(--text-muted); font-size: 13.5px; margin-top: 4px; }

    .toolbar { display: flex; align-items: center; justify-content: space-between; gap: 16px; margin-bottom: 20px; flex-wrap: wrap; }
    .toolbar select { padding: 9px 14px; border-radius: var(--radius-md); border: 1px solid var(--surface-border); background: var(--surface-card); font-size: 13.5px; font-weight: 600; color: var(--text-heading); min-width: 260px; }

    .live-indicator { display: flex; align-items: center; gap: 8px; font-size: 12.5px; color: var(--text-muted); font-weight: 600; }
    .live-dot { width: 8px; height: 8px; border-radius: 50%; background: #2ecc71; box-shadow: 0 0 0 0 rgba(46, 204, 113, 0.6); animation: live-pulse 1.6s infinite; }
    @keyframes live-pulse {
        0% { box-shadow: 0 0 0 0 rgba(46, 204, 113, 0.5); }
        70% { box-shadow: 0 0 0 6px rgba(46, 204, 113, 0); }
        100% { box-shadow: 0 0 0 0 rgba(46, 204, 113, 0); }
    }

    .panel { background: var(--surface-card); border: 1px solid var(--surface-border); border-radius: var(--radius-lg); overflow: hidden; box-shadow: var(--shadow-soft); }
    .panel-header { padding: 16px 20px; border-bottom: 1px solid var(--surface-border); display: flex; justify-content: space-between; align-items: center; }
    .panel-header h2 { font-size: 15px; font-weight: 700; margin: 0; color: var(--text-heading); }

    table.roster-table { width: 100%; border-collapse: collapse; }
    .roster-table thead th { text-align: left; font-size: 11.5px; text-transform: uppercase; letter-spacing: 0.4px; color: var(--text-muted); font-weight: 700; padding: 12px 20px; border-bottom: 1px solid var(--surface-border); }
    .roster-table tbody td { padding: 14px 20px; border-bottom: 1px solid var(--surface-border); font-size: 13.5px; vertical-align: middle; }
    .roster-table tbody tr:last-child td { border-bottom: none; }
    .roster-table .student-name { font-weight: 700; color: var(--text-heading); }
    .roster-table .student-email { color: var(--text-muted); font-size: 12px; margin-top: 2px; }

    .empty-state { padding: 40px 20px; text-align: center; color: var(--text-muted); font-size: 13.5px; }
</style>

<div class="content-workspace">
    <div class="page-header">
        <div>
            <p class="eyebrow">Students</p>
            <h1>Group Roster</h1>
            <p class="sub">Pick a group to see who's in it — this list refreshes automatically as membership changes.</p>
        </div>
        <div class="live-indicator"><span class="live-dot"></span> <span id="live-updated-label">Live</span></div>
    </div>

    <div class="toolbar">
        <select id="group-select" onchange="if (this.value) { window.location = '{{ route('lecturer.students.index') }}?group=' + this.value; }">
            <option value="">Select a group…</option>
            @forelse($groups as $group)
                <option value="{{ $group->GroupID }}" {{ (int) $selectedGroupId === (int) $group->GroupID ? 'selected' : '' }}>
                    {{ $group->GroupName }} ({{ $group->member_count }} {{ Str::plural('student', $group->member_count) }})
                </option>
            @empty
                <option value="" disabled>No groups exist yet</option>
            @endforelse
        </select>
    </div>

    <section class="panel">
        <div class="panel-header">
            <h2>{{ $selectedGroupId ? ($groups->firstWhere('GroupID', $selectedGroupId)->GroupName ?? 'Roster') : 'Roster' }}</h2>
            <span id="student-count-label" style="font-size:12.5px; color: var(--text-muted); font-weight:600;">
                {{ $students->count() }} {{ Str::plural('student', $students->count()) }}
            </span>
        </div>

        <table class="roster-table">
            <thead>
                <tr>
                    <th>Student</th>
                    <th>Account Status</th>
                    <th>Membership</th>
                    <th>Last Active</th>
                    <th>Joined</th>
                </tr>
            </thead>
            <tbody id="roster-tbody">
                @forelse($students as $membership)
                    <tr>
                        <td>
                            <div class="student-name">{{ $membership->user?->UserName ?? $membership->user?->Handle ?? 'Unknown' }}</div>
                            <div class="student-email">{{ $membership->user?->Email }}</div>
                        </td>
                        <td><span class="badge bg-secondary-subtle">{{ $membership->user?->Status ?? '—' }}</span></td>
                        <td><span class="badge bg-secondary-subtle">{{ ucfirst($membership->Status ?? '—') }}</span></td>
                        <td>{{ optional($membership->user?->LastActive)?->diffForHumans() ?? '—' }}</td>
                        <td>{{ optional($membership->CreatedAt)?->format('M j, Y') ?? '—' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5">
                            <div class="empty-state">
                                {{ $groups->isEmpty() ? 'No groups exist yet.' : 'No students in this group yet.' }}
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </section>
</div>

<script>
    (function () {
        const groupId = {{ $selectedGroupId ? (int) $selectedGroupId : 'null' }};
        const tbody = document.getElementById('roster-tbody');
        const countLabel = document.getElementById('student-count-label');
        const liveLabel = document.getElementById('live-updated-label');

        if (!groupId) return;

        function escapeHtml(value) {
            const div = document.createElement('div');
            div.textContent = value ?? '';
            return div.innerHTML;
        }

        function render(students) {
            if (!students.length) {
                tbody.innerHTML = '<tr><td colspan="5"><div class="empty-state">No students in this group yet.</div></td></tr>';
                return;
            }

            tbody.innerHTML = students.map(function (s) {
                return '<tr>'
                    + '<td><div class="student-name">' + escapeHtml(s.name) + '</div>'
                    + '<div class="student-email">' + escapeHtml(s.email) + '</div></td>'
                    + '<td><span class="badge bg-secondary-subtle">' + escapeHtml(s.account_status || '—') + '</span></td>'
                    + '<td><span class="badge bg-secondary-subtle">' + escapeHtml(s.membership_status ? s.membership_status.charAt(0).toUpperCase() + s.membership_status.slice(1) : '—') + '</span></td>'
                    + '<td>' + escapeHtml(s.last_active || '—') + '</td>'
                    + '<td>' + escapeHtml(s.joined_at || '—') + '</td>'
                    + '</tr>';
            }).join('');
        }

        async function pollRoster() {
            try {
                const res = await fetch('{{ route('lecturer.students.poll') }}?group=' + groupId, {
                    headers: { 'Accept': 'application/json' },
                });
                if (!res.ok) return;

                const data = await res.json().catch(() => null);
                if (!data || data.success !== true) return;

                render(data.students);
                countLabel.textContent = data.count + ' ' + (data.count === 1 ? 'student' : 'students');
                liveLabel.textContent = 'Updated ' + new Date().toLocaleTimeString();
            } catch (e) {
                console.error('Roster poll error', e);
            }
        }

        setInterval(pollRoster, 8000);
    })();
</script>
@endsection
