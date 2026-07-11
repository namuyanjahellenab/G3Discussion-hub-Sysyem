@extends('layouts.app')

@section('content')
<div class="blacklist-page-shell">
    <div class="blacklist-page-header d-flex justify-content-between align-items-start flex-wrap gap-3 mb-4">
        <div class="d-flex align-items-start gap-3">
            <div class="blacklist-title-icon">
                <i class="fa-solid fa-user-shield"></i>
            </div>
            <div class="blacklist-title-block">
                <h2 class="fw-bold mb-2">Blacklist Management</h2>
                <p class="text-muted mb-0">Monitor and manage restricted users across the hub.</p>
            </div>
        </div>

        <div class="blacklist-actions d-flex flex-wrap gap-2">
            <button type="button" class="btn btn-warning" data-bs-toggle="modal" data-bs-target="#issueWarningModal">
                <i class="bi bi-exclamation-triangle"></i> Issue Warning
            </button>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addBlacklistModal">
                <i class="bi bi-plus-lg"></i> Add Manual Blacklist
            </button>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <div class="blacklist-layout">
        <div class="card blacklist-table-card mb-4">
            <div class="card-body blacklist-table-card-body">
                <div class="table-responsive blacklist-table-wrap">
                    <table class="table align-middle blacklist-table mb-0">
                        <thead>
                            <tr class="text-uppercase small text-muted">
                                <th>User</th>
                                <th>Reason</th>
                                <th>Start Date</th>
                                <th>End Date</th>
                                <th>Type</th>
                                <th class="blacklist-action-col">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($blacklist as $entry)
                                @php
                                    $initials = collect(explode(' ', trim($entry->user?->UserName ?? '?')))
                                        ->map(fn($p) => strtoupper(substr($p, 0, 1)))->join('');
                                    $isExpired = $entry->EndDate < now();
                                @endphp
                                <tr class="{{ $isExpired ? 'opacity-50' : '' }}">
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <div class="avatar-badge bg-primary bg-opacity-25 text-primary">{{ $initials }}</div>
                                            <span class="fw-medium">{{ $entry->user?->UserName ?? 'Unknown User' }}</span>
                                        </div>
                                    </td>
                                    <td>{{ $entry->Reason }}</td>
                                    <td>{{ $entry->StartDate->format('d M Y') }}</td>
                                    <td>{{ $entry->EndDate->format('d M Y') }}</td>
                                    <td>
                                        <span class="badge {{ $entry->Type === 'Auto' ? 'bg-warning' : 'bg-primary' }}">
                                            {{ $entry->Type }}
                                        </span>
                                    </td>
                                    <td class="blacklist-action-col">
                                        @if(!$isExpired)
                                            <form method="POST" action="{{ route('admin.blacklist.destroy', $entry->BlacklistID) }}" onsubmit="return confirm('Remove this blacklist entry?');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-outline-secondary btn-sm">Remove</button>
                                            </form>
                                        @else
                                            <span class="text-muted small">Expired</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="text-center py-5">
                                        <div class="blacklist-empty-state">
                                            <div class="blacklist-empty-icon">
                                                <i class="fa-solid fa-shield-check"></i>
                                            </div>
                                            <div class="fw-semibold mb-1">No blacklist records found.</div>
                                            <div class="text-muted small">Try adjusting filters or add a new entry.</div>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="blacklist-footer-row d-flex justify-content-between align-items-center flex-wrap gap-3 mt-4 pt-3">
                    <form method="GET" action="{{ route('admin.blacklist') }}" id="expiredForm" class="blacklist-toggle-form">
                        <div class="form-check d-flex align-items-center gap-2 mb-0">
                            <input class="form-check-input" type="checkbox" name="show_expired" value="1"
                                   id="showExpired" onchange="document.getElementById('expiredForm').submit()"
                                   {{ $showExpired ? 'checked' : '' }}>
                            <label class="form-check-label text-muted" for="showExpired">
                                Show expired / historical records
                            </label>
                        </div>
                    </form>

                    <div class="blacklist-pagination-wrap">
                        {{ $blacklist->links() }}
                    </div>
                </div>
            </div>
        </div>

        {{-- Live Activity Log --}}
        <div class="card blacklist-activity-card">
            <div class="card-body blacklist-activity-body">
                <h5 class="fw-bold mb-3 d-flex align-items-center gap-2"><i class="bi bi-journal-text"></i> Live Activity Log</h5>

                @forelse($activityLog as $event)
                    @php
                        $tagClass = match($event['tag']) {
                            'Auto' => 'bg-warning text-dark',
                            'Admin' => 'bg-primary',
                            default => 'bg-secondary text-dark',
                        };
                    @endphp
                    <div class="activity-row mb-3">
                        <div class="d-flex flex-wrap align-items-start gap-2 mb-1">
                            <span class="badge {{ $tagClass }}">{{ $event['tag'] }}</span>
                            <span class="text-body">{{ $event['text'] }}</span>
                        </div>
                        <div class="text-muted small">{{ $event['time']->format('d M Y') }}</div>
                    </div>
                @empty
                    <p class="text-muted small mb-0">No recent activity.</p>
                @endforelse
            </div>
        </div>
    </div>
</div>

{{-- Add Manual Blacklist Modal --}}
<div class="modal fade" id="addBlacklistModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form method="POST" action="{{ route('admin.blacklist.store') }}">
            @csrf
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add Manual Blacklist</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">User</label>
                        <select name="UserID" class="form-select" required>
                            <option value="">Select a user...</option>
                            @foreach($users as $user)
                                <option value="{{ $user->UserID }}">{{ $user->UserName }} ({{ $user->Email }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Reason</label>
                        <input type="text" name="Reason" class="form-control" placeholder="e.g. Spam, Abuse" required>
                    </div>
                    <div class="row">
                        <div class="col-6 mb-3">
                            <label class="form-label">Start Date</label>
                            <input type="date" name="StartDate" class="form-control" value="{{ now()->format('Y-m-d') }}" required>
                        </div>
                        <div class="col-6 mb-3">
                            <label class="form-label">End Date</label>
                            <input type="date" name="EndDate" class="form-control" required>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add to Blacklist</button>
                </div>
          </div>
        </form>
    </div>
</div>

{{-- Issue Warning Modal --}}
<div class="modal fade" id="issueWarningModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form method="POST" action="{{ route('admin.warning.store') }}">
            @csrf
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Issue Warning</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">User</label>
                        <select name="UserID" class="form-select" required>
                            <option value="">Select a user...</option>
                            @foreach($users as $user)
                                <option value="{{ $user->UserID }}">{{ $user->UserName }} ({{ $user->Email }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Reason</label>
                        <textarea name="Reason" class="form-control" rows="3" placeholder="e.g. Inappropriate language" required></textarea>
                    </div>
                    <p class="text-muted small mb-0">
                        2 active warnings will automatically blacklist the user for {{ \App\Http\Controllers\Admin\AdminWarningController::AUTO_BLACKLIST_DAYS }} days.
                    </p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning">Issue Warning</button>
                </div>
            </div>
        </form>
    </div>
</div>
@endsection

@push('styles')
<style>
    .blacklist-page-shell {
        max-width: 1500px;
        margin: 0 auto;
        padding: 1.5rem 1.5rem 2.5rem;
    }

    .blacklist-page-header {
        margin-bottom: 1.75rem;
    }

    .blacklist-title-block h2 {
        margin-bottom: 0.5rem;
    }

    .blacklist-title-block p {
        max-width: 56rem;
        line-height: 1.6;
    }

    .blacklist-title-icon {
        width: 3rem;
        height: 3rem;
        border-radius: 999px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        background: var(--luna-lightest);
        color: var(--luna-mid);
        box-shadow: var(--shadow-soft);
        flex: 0 0 auto;
    }

    .blacklist-title-icon i {
        font-size: 1.1rem;
    }

    .blacklist-actions {
        margin-top: 0.25rem;
    }

    .blacklist-table-card,
    .blacklist-activity-card {
        border: 1px solid var(--surface-border);
        box-shadow: var(--shadow-soft);
    }

    .blacklist-table-card-body,
    .blacklist-activity-body {
        padding: 1.25rem;
    }

    .blacklist-table thead th {
        padding: 1rem 1rem 0.9rem;
        letter-spacing: 0.08em;
        white-space: nowrap;
        color: var(--text-muted);
    }

    .blacklist-table tbody td {
        padding: 1rem;
    }

    .blacklist-action-col {
        width: 1%;
        white-space: nowrap;
    }

    .avatar-badge {
        width: 32px; height: 32px; border-radius: 50%;
        display: flex; align-items: center; justify-content: center;
        font-weight: 600; font-size: 0.75rem;
    }

    .blacklist-empty-state {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 0.35rem;
        color: var(--text-body);
    }

    .blacklist-empty-icon {
        width: 2.5rem;
        height: 2.5rem;
        border-radius: 999px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        background: var(--surface-bg);
        color: var(--luna-mid);
        margin-bottom: 0.3rem;
    }

    .blacklist-empty-icon i {
        font-size: 1rem;
    }

    .blacklist-footer-row {
        border-top: 1px solid var(--surface-border);
    }

    .blacklist-toggle-form .form-check {
        min-height: 1.5rem;
    }

    .blacklist-pagination-wrap .pagination {
        margin-bottom: 0;
        gap: 0.35rem;
        flex-wrap: wrap;
    }

    .blacklist-pagination-wrap .page-item {
        display: flex;
    }

    .blacklist-pagination-wrap .page-link {
        border: 1px solid var(--surface-border);
        border-radius: 999px !important;
        color: var(--text-body);
        min-width: 2.4rem;
        text-align: center;
        padding: 0.45rem 0.8rem;
        box-shadow: var(--shadow-soft);
        background: var(--surface-card);
    }

    .blacklist-pagination-wrap .page-link:hover {
        background: var(--surface-bg);
        color: var(--luna-dark);
    }

    .blacklist-pagination-wrap .page-item.active .page-link {
        background: var(--luna-mid);
        border-color: var(--luna-mid);
        color: #fff;
    }

    .blacklist-pagination-wrap .page-item.disabled .page-link {
        background: var(--surface-bg);
        color: var(--text-muted);
    }

    .activity-row {
        border-left: 3px solid var(--luna-mid);
        padding: 0.9rem 1rem;
        margin-bottom: 0.85rem;
        background: var(--surface-card);
        border: 1px solid var(--surface-border);
        border-left-width: 3px;
        border-radius: var(--radius-md);
        box-shadow: var(--shadow-soft);
    }

    .activity-row:last-child {
        margin-bottom: 0;
    }

    .activity-row .badge {
        letter-spacing: 0.03em;
        font-size: 0.7rem;
        padding: 0.35rem 0.65rem;
    }

    @media (max-width: 991.98px) {
        .blacklist-page-shell {
            padding-inline: 1rem;
        }

        .blacklist-table-card-body,
        .blacklist-activity-body {
            padding: 1rem;
        }
    }
</style>
@endpush