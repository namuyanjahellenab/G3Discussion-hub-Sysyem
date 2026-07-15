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
                <p class="text-muted mb-0">Posts and messages the ML gateway detected as spam, auto-flagged for review.</p>
            </div>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <div class="card flagged-content-table-card mb-4">
        <div class="card-body flagged-content-table-card-body">
            <h5 class="fw-bold mb-3 d-flex align-items-center gap-2"><i class="bi bi-chat-square-text"></i> Flagged Posts</h5>
            <div class="table-responsive flagged-content-table-wrap">
                <table class="table align-middle flagged-content-table mb-0">
                    <thead>
                        <tr class="text-uppercase small text-muted">
                            <th>Content</th>
                            <th>Author</th>
                            <th>Topic / Group</th>
                            <th>Reason</th>
                            <th>Date</th>
                            <th class="flagged-content-action-col">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($flaggedPosts as $post)
                            <tr>
                                <td class="flagged-content-excerpt">{{ \Illuminate\Support\Str::limit($post->Content, 120) }}</td>
                                <td>{{ $post->author?->UserName ?? 'Unknown User' }}</td>
                                <td>
                                    {{ $post->topic?->Title ?? 'Deleted topic' }}
                                    @if($post->topic?->group)
                                        <div class="text-muted small">{{ $post->topic->group->GroupName }}</div>
                                    @endif
                                </td>
                                <td>{{ $post->FlaggedReason }}</td>
                                <td>{{ $post->CreatedAt->format('d M Y') }}</td>
                                <td class="flagged-content-action-col">
                                    <div class="d-flex gap-2">
                                        <form method="POST" action="{{ route('admin.flagged-content.dismiss', $post->PostID) }}">
                                            @csrf
                                            <button type="submit" class="btn btn-outline-secondary btn-sm">Dismiss</button>
                                        </form>
                                        <form method="POST" action="{{ route('admin.flagged-content.destroy', $post->PostID) }}" onsubmit="return confirm('Delete this post permanently?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-outline-danger btn-sm">Delete</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center py-5">
                                    <div class="flagged-content-empty-state">
                                        <div class="flagged-content-empty-icon">
                                            <i class="fa-solid fa-shield-check"></i>
                                        </div>
                                        <div class="fw-semibold mb-1">No flagged posts.</div>
                                        <div class="text-muted small">Nothing has tripped spam detection recently.</div>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="flagged-content-pagination-wrap mt-4 pt-3">
                {{ $flaggedPosts->links() }}
            </div>
        </div>
    </div>

    <div class="card flagged-content-table-card">
        <div class="card-body flagged-content-table-card-body">
            <h5 class="fw-bold mb-3 d-flex align-items-center gap-2"><i class="bi bi-people"></i> Flagged Group Chat Messages</h5>
            <div class="table-responsive flagged-content-table-wrap">
                <table class="table align-middle flagged-content-table mb-0">
                    <thead>
                        <tr class="text-uppercase small text-muted">
                            <th>Message</th>
                            <th>Author</th>
                            <th>Date</th>
                            <th class="flagged-content-action-col">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($flaggedMessages as $message)
                            <tr>
                                <td class="flagged-content-excerpt">{{ \Illuminate\Support\Str::limit($message->body, 120) }}</td>
                                <td>{{ $message->user?->UserName ?? 'Unknown User' }}</td>
                                <td>{{ $message->CreatedAt->format('d M Y') }}</td>
                                <td class="flagged-content-action-col">
                                    <form method="POST" action="{{ route('admin.flagged-content.messages.dismiss', $message->MessageID) }}">
                                        @csrf
                                        <button type="submit" class="btn btn-outline-secondary btn-sm">Dismiss</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="text-center py-5">
                                    <div class="flagged-content-empty-state">
                                        <div class="flagged-content-empty-icon">
                                            <i class="fa-solid fa-shield-check"></i>
                                        </div>
                                        <div class="fw-semibold mb-1">No flagged messages.</div>
                                        <div class="text-muted small">Nothing has tripped spam detection recently.</div>
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

    .flagged-content-title-icon i {
        font-size: 1.1rem;
    }

    .flagged-content-table-card {
        border: 1px solid var(--surface-border);
        box-shadow: var(--shadow-soft);
    }

    .flagged-content-table-card-body {
        padding: 1.25rem;
    }

    .flagged-content-table thead th {
        padding: 1rem 1rem 0.9rem;
        letter-spacing: 0.08em;
        white-space: nowrap;
        color: var(--text-muted);
    }

    .flagged-content-table tbody td {
        padding: 1rem;
    }

    .flagged-content-excerpt {
        max-width: 320px;
    }

    .flagged-content-action-col {
        width: 1%;
        white-space: nowrap;
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
