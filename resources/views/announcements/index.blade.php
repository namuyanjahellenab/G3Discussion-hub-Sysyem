@extends('layouts.app')

@section('content')
<style>
    .content-workspace { padding: 28px; max-width: 820px; }
    .page-header { margin-bottom: 24px; display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap; }
    .page-header p.eyebrow { text-transform: uppercase; color: var(--text-muted); font-size: 0.75rem; font-weight: 600; letter-spacing: 0.5px; margin: 0 0 4px 0; }
    .page-header h1 { font-size: 24px; font-weight: 800; margin: 0; }

    .btn { display: inline-flex; align-items: center; justify-content: center; gap: 8px; padding: 0.55rem 1rem; border-radius: var(--radius-md); font-size: 13px; font-weight: 600; text-decoration: none; border: 1px solid transparent; box-shadow: var(--shadow-soft); cursor: pointer; }
    .btn-primary { background: var(--luna-mid); color: #fff; }
    .btn-primary:hover { background: var(--luna-dark); color: #fff; }

    .announcement-card { background: var(--surface-card); border: 1px solid var(--surface-border); border-radius: var(--radius-lg); padding: 18px 20px; margin-bottom: 14px; box-shadow: var(--shadow-soft); }
    .announcement-meta { display: flex; align-items: center; gap: 8px; font-size: 0.78rem; color: var(--text-muted); margin-bottom: 8px; flex-wrap: wrap; }
    .announcement-meta .badge { background: var(--luna-lightest); color: var(--luna-dark); font-weight: 600; padding: 3px 10px; border-radius: 999px; }
    .announcement-body { font-size: 0.92rem; color: var(--text-body); line-height: 1.5; white-space: pre-wrap; }

    .empty-state { padding: 40px; text-align: center; color: var(--text-muted); font-size: 13.5px; }
</style>

<div class="content-workspace">
    <div class="page-header">
        <div>
            <p class="eyebrow">Announcements</p>
            <h1>Past Announcements</h1>
        </div>
        <a href="{{ route('announcements.create') }}" class="btn btn-primary"><i class="fa-solid fa-bullhorn"></i> New Announcement</a>
    </div>

    @if(session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    @forelse($announcements as $announcement)
        <div class="announcement-card">
            <div class="announcement-meta">
                <span class="badge">{{ $announcement->group?->GroupName ?? 'Campus-wide' }}</span>
                <span>{{ $announcement->author?->UserName ?? $announcement->author?->name ?? 'Unknown' }}</span>
                <span>&bull;</span>
                <span>{{ $announcement->CreatedAt->diffForHumans() }}</span>
                @if($announcement->exclusions_count)
                    <span>&bull;</span>
                    <span>{{ $announcement->exclusions_count }} member(s) excluded</span>
                @endif
            </div>
            <div class="announcement-body">{{ $announcement->Message }}</div>
        </div>
    @empty
        <div class="empty-state">No announcements sent yet.</div>
    @endforelse

    <div class="mt-3">{{ $announcements->links() }}</div>
</div>
@endsection
