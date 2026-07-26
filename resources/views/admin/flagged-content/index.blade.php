@extends('layouts.app')

@section('content')
<div class="flagged-content-page-shell">
    <div class="flagged-content-page-header d-flex justify-content-between align-items-start flex-wrap gap-3 mb-4">
        <div class="d-flex align-items-start gap-3">
            <div class="flagged-content-title-icon">
                <i class="fa-solid fa-flag"></i>
            </div>
            <div class="flagged-content-title-block">
                <h2 class="fw-bold mb-2">Flagged Content</h2>
                <p class="text-muted mb-0">Posts, replies, and messages the community reported or the ML gateway detected as spam, flagged for review.</p>
            </div>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <div class="card flagged-content-table-card mb-4">
        <div class="card-body flagged-content-table-card-body">
            <h5 class="fw-bold mb-3 d-flex align-items-center gap-2"><i class="bi bi-chat-square-text"></i> Flagged Posts and Replies</h5>
            <div class="flagged-content-table-wrap posts-replies-table">
                <table class="table align-middle flagged-content-table mb-0">
                    <thead>
                        <tr class="text-uppercase small text-muted">
                            <th class="col-type">Type</th>
                            <th class="col-content">Content</th>
                            <th class="col-author">Author</th>
                            <th class="col-context">Topic / Group</th>
                            <th class="col-flaggedby">Flagged by</th>
                            <th class="col-reason">Reason</th>
                            <th class="col-date">Date</th>
                            <th class="flagged-content-action-col">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($flaggedItems as $item)
                            <tr>
                                <td class="col-type"><span class="badge flagged-type-badge {{ $item['type'] === 'Post' ? 'bg-primary-subtle text-primary' : 'bg-info-subtle text-info' }}">{{ $item['type'] }}</span></td>
                                <td class="flagged-content-excerpt col-content">{{ \Illuminate\Support\Str::limit($item['content'], 120) }}</td>
                                <td class="col-author">{{ $item['author_name'] }}</td>
                                <td class="col-context">
                                    {{ $item['context'] }}
                                    @if($item['group_name'])
                                        <div class="text-muted small">{{ $item['group_name'] }}</div>
                                    @endif
                                </td>
                                <td class="col-flaggedby">{{ $item['flag_count'] }} {{ Str::plural('member', $item['flag_count']) }}</td>
                                <td class="col-reason">{{ $item['reason'] ?: 'Not specified' }}</td>
                                <td class="col-date">{{ $item['date']->setTimezone('Africa/Kampala')->format('d M Y') }}</td>
                                <td class="flagged-content-action-col">
                                    <div class="flagged-action-icons">
                                        <form method="POST" action="{{ $item['dismiss_route'] }}">
                                            @csrf
                                            <button type="submit" class="btn-icon-action btn-icon-secondary">Dismiss</button>
                                        </form>
                                        <form method="POST" action="{{ route('admin.warning.store') }}">
                                            @csrf
                                            <input type="hidden" name="UserID" value="{{ $item['author_id'] }}">
                                            <input type="hidden" name="Reason" value="Flagged {{ strtolower($item['type']) }}: {{ \Illuminate\Support\Str::limit($item['content'], 80) }}">
                                            <button type="submit" class="btn-icon-action btn-icon-warning">Warn ({{ $warningCounts[$item['author_id']] ?? 0 }}/{{ config('moderation.warning_threshold', 2) }})</button>
                                        </form>
                                        <form method="POST" action="{{ $item['destroy_route'] }}" onsubmit="return confirm('Delete this {{ strtolower($item['type']) }} permanently?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn-icon-action btn-icon-danger">Delete</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center py-5">
                                    <div class="flagged-content-empty-state">
                                        <div class="flagged-content-empty-icon">
                                            <i class="fa-solid fa-shield-check"></i>
                                        </div>
                                        <div class="fw-semibold mb-1">No flagged posts or replies.</div>
                                        <div class="text-muted small">Nothing has been reported recently.</div>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="flagged-content-pagination-wrap mt-4 pt-3">
                {{ $flaggedItems->links() }}
            </div>
        </div>
    </div>

    <div class="card flagged-content-table-card">
        <div class="card-body flagged-content-table-card-body">
            <h5 class="fw-bold mb-3 d-flex align-items-center gap-2"><i class="bi bi-people"></i> Flagged Group Chat Messages</h5>
            <div class="flagged-content-table-wrap messages-table">
                <table class="table align-middle flagged-content-table mb-0">
                    <thead>
                        <tr class="text-uppercase small text-muted">
                            <th class="col-content">Message</th>
                            <th class="col-author">Author</th>
                            <th class="col-reason">Reason</th>
                            <th class="col-date">Date</th>
                            <th class="flagged-content-action-col">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($flaggedMessages as $message)
                            <tr>
                                <td class="flagged-content-excerpt col-content">{{ \Illuminate\Support\Str::limit($message->body, 120) }}</td>
                                <td class="col-author">{{ $message->user?->UserName ?? 'Unknown User' }}</td>
                                <td class="col-reason">{{ $message->FlaggedReason ?: ($message->is_spam ? 'Automatic spam detection' : 'Not specified') }}</td>
                                <td class="col-date">{{ $message->CreatedAt->setTimezone('Africa/Kampala')->format('d M Y') }}</td>
                                <td class="flagged-content-action-col">
                                    <div class="flagged-action-icons">
                                        <form method="POST" action="{{ route('admin.flagged-content.messages.dismiss', $message->MessageID) }}">
                                            @csrf
                                            <button type="submit" class="btn-icon-action btn-icon-secondary">Dismiss</button>
                                        </form>
                                        <form method="POST" action="{{ route('admin.warning.store') }}">
                                            @csrf
                                            <input type="hidden" name="UserID" value="{{ $message->user_id }}">
                                            <input type="hidden" name="Reason" value="Flagged message: {{ \Illuminate\Support\Str::limit($message->body, 80) }}">
                                            <button type="submit" class="btn-icon-action btn-icon-warning">Warn ({{ $warningCounts[$message->user_id] ?? 0 }}/{{ config('moderation.warning_threshold', 2) }})</button>
                                        </form>
                                        <form method="POST" action="{{ route('admin.flagged-content.messages.destroy', $message->MessageID) }}" onsubmit="return confirm('Delete this message permanently?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn-icon-action btn-icon-danger">Delete</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center py-5">
                                    <div class="flagged-content-empty-state">
                                        <div class="flagged-content-empty-icon">
                                            <i class="fa-solid fa-shield-check"></i>
                                        </div>
                                        <div class="fw-semibold mb-1">No flagged messages.</div>
                                        <div class="text-muted small">Nothing has been reported or tripped spam detection recently.</div>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection

@push('styles')
<style>
    .flagged-content-page-shell {
        max-width: 1500px;
        margin: 0 auto;
        padding: 1.5rem 1.5rem 2.5rem;
    }

    .flagged-content-page-header {
        margin-bottom: 1.75rem;
    }

    .flagged-content-title-block h2 {
        margin-bottom: 0.5rem;
    }

    .flagged-content-title-block p {
        max-width: 56rem;
        line-height: 1.6;
    }

    .flagged-content-title-icon {
        width: 2.25rem;
        height: 2.25rem;
        border-radius: 999px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        background: var(--luna-lightest);
        color: var(--luna-mid);
        box-shadow: var(--shadow-soft);
        flex: 0 0 auto;
    }

    .flagged-content-title-icon i {
        font-size: 0.85rem;
    }

    .flagged-content-table-card h5 {
        font-size: 0.95rem;
    }

    .flagged-content-table-card h5 i {
        font-size: 0.85rem;
    }

    .flagged-type-badge {
        font-size: 0.68rem;
        padding: 0.25em 0.5em;
        font-weight: 600;
    }

    /* Small text pills with a tinted background per action, instead of the
       original full-size outline buttons that forced the action column too
       wide for the fixed table layout. */
    .flagged-action-icons {
        display: flex;
        gap: 0.35rem;
        flex-wrap: wrap;
    }

    .btn-icon-action {
        display: inline-flex;
        align-items: center;
        padding: 0.3rem 0.6rem;
        border-radius: 999px;
        border: none;
        font-size: 0.68rem;
        font-weight: 700;
        white-space: nowrap;
        cursor: pointer;
        transition: filter 0.15s ease;
    }

    .btn-icon-action:hover { filter: brightness(0.94); }

    .btn-icon-secondary { background: var(--luna-lightest); color: var(--luna-dark); }

    .btn-icon-warning { background: #FEF3C7; color: #92400E; }

    .btn-icon-danger { background: var(--accent-danger-bg); color: var(--accent-danger); }

    .flagged-content-table-card {
        border: 1px solid var(--surface-border);
        box-shadow: var(--shadow-soft);
    }

    .flagged-content-table-card-body {
        padding: 1.25rem;
    }

    .flagged-content-table-wrap {
        /* No horizontal scrollbar - columns shrink and content wraps
           instead of the table ever growing wider than its card. */
        overflow-x: visible;
        width: 100%;
    }

    .flagged-content-table {
        table-layout: fixed;
        width: 100%;
    }

    .flagged-content-table thead th {
        padding: 1rem 1rem 0.9rem;
        letter-spacing: 0.08em;
        white-space: normal;
        word-break: break-word;
        color: var(--text-muted);
    }

    .flagged-content-table tbody td {
        padding: 1rem;
        white-space: normal;
        word-break: break-word;
        vertical-align: top;
    }

    .flagged-content-excerpt {
        max-width: none;
    }

    .flagged-content-action-col {
        white-space: normal;
    }

    /* Posts and Replies table - 8 columns, widths sum to 100%. */
    .posts-replies-table .col-type { width: 7%; }
    .posts-replies-table .col-content { width: 22%; }
    .posts-replies-table .col-author { width: 12%; }
    .posts-replies-table .col-context { width: 14%; }
    .posts-replies-table .col-flaggedby { width: 12%; white-space: nowrap; }
    .posts-replies-table .col-reason { width: 14%; }
    .posts-replies-table .col-date { width: 8%; }
    .posts-replies-table .flagged-content-action-col { width: 11%; }

    /* Flagged Messages table - 5 columns, widths sum to 100%. */
    .messages-table .col-content { width: 30%; }
    .messages-table .col-author { width: 13%; }
    .messages-table .col-reason { width: 16%; }
    .messages-table .col-date { width: 10%; }
    .messages-table .flagged-content-action-col { width: 31%; }

    @media (max-width: 768px) {
        /* Least-essential-at-a-glance columns fold away first so the table
           never has to grow wider than the screen to stay readable. */
        .posts-replies-table .col-context,
        .posts-replies-table .col-flaggedby {
            display: none;
        }

        .posts-replies-table .col-type { width: 10%; }
        .posts-replies-table .col-content { width: 32%; }
        .posts-replies-table .col-author { width: 15%; }
        .posts-replies-table .col-reason { width: 18%; }
        .posts-replies-table .col-date { width: 10%; }
        .posts-replies-table .flagged-content-action-col { width: 15%; }
    }

    .flagged-content-empty-state {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 0.35rem;
        color: var(--text-body);
    }

    .flagged-content-empty-icon {
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

    .flagged-content-empty-icon i {
        font-size: 1rem;
    }

    .flagged-content-pagination-wrap {
        border-top: 1px solid var(--surface-border);
    }

    .flagged-content-pagination-wrap .pagination {
        margin-bottom: 0;
        gap: 0.35rem;
        flex-wrap: wrap;
    }

    .flagged-content-pagination-wrap .page-item {
        display: flex;
    }

    .flagged-content-pagination-wrap .page-link {
        border: 1px solid var(--surface-border);
        border-radius: 999px !important;
        color: var(--text-body);
        min-width: 2.4rem;
        text-align: center;
        padding: 0.45rem 0.8rem;
        box-shadow: var(--shadow-soft);
        background: var(--surface-card);
    }

    .flagged-content-pagination-wrap .page-link:hover {
        background: var(--surface-bg);
        color: var(--luna-dark);
    }

    .flagged-content-pagination-wrap .page-item.active .page-link {
        background: var(--luna-mid);
        border-color: var(--luna-mid);
        color: #fff;
    }

    .flagged-content-pagination-wrap .page-item.disabled .page-link {
        background: var(--surface-bg);
        color: var(--text-muted);
    }

    @media (max-width: 991.98px) {
        .flagged-content-page-shell {
            padding-inline: 1rem;
        }

        .flagged-content-table-card-body {
            padding: 1rem;
        }
    }
</style>
@endpush
