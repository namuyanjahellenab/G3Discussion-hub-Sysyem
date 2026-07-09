@extends('layouts.app')

@section('content')
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<style>
    .topic-page-wrap { font-family: 'Inter', sans-serif; padding: 2.5rem; max-width: 900px; margin: 0 auto; }
    .breadcrumb-row { color: #667085; font-size: 0.85rem; margin-bottom: 1.5rem; }
    .breadcrumb-row a { color: #667085; text-decoration: none; }
    .breadcrumb-row span.current { color: #101828; font-weight: 600; }
    .topic-toolbar { display: flex; gap: 12px; margin-bottom: 1.5rem; }
    .topic-search { flex: 1; position: relative; }
    .topic-search input { width: 100%; padding: 10px 14px 10px 38px; border: 1px solid #e4e7ec; border-radius: 10px; font-size: 0.9rem; }
    .topic-search i { position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: #98a2b3; }
    .filter-select { padding: 10px 14px; border: 1px solid #e4e7ec; border-radius: 10px; font-size: 0.9rem; background: #fff; color: #344054; }
    .new-topic-btn { background: #0d52cc; color: #fff; border: none; border-radius: 10px; padding: 10px 18px; font-weight: 600; font-size: 0.9rem; text-decoration: none; display: inline-block; margin-bottom: 1.5rem; }
    .topic-list-card { background: #fff; border: 1px solid #e4e7ec; border-radius: 16px; overflow: hidden; }
    .topic-row { display: flex; align-items: flex-start; gap: 14px; padding: 18px 20px; border-bottom: 1px solid #f2f4f7; text-decoration: none; color: inherit; }
    .topic-row:last-child { border-bottom: none; }
    .topic-row:hover { background: #fafbfc; }
    .topic-badge { width: 28px; height: 28px; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 0.7rem; font-weight: 700; flex-shrink: 0; margin-top: 2px; }
    .badge-pinned { background: #eef4ff; color: #0d52cc; }
    .badge-answered { background: #ecfdf3; color: #12b76a; }
    .badge-open { background: #f2f4f7; color: #667085; }
    .badge-discussion { background: #fef0ff; color: #9e77ed; }
    .topic-status-pill { font-size: 0.65rem; font-weight: 700; text-transform: uppercase; padding: 3px 8px; border-radius: 6px; letter-spacing: 0.3px; }
    .topic-title { color: #101828; font-weight: 700; font-size: 0.98rem; margin: 0 0 4px 0; }
    .topic-meta { color: #98a2b3; font-size: 0.8rem; }
    .pagination-row { display: flex; justify-content: space-between; align-items: center; margin-top: 1.5rem; color: #667085; font-size: 0.85rem; }

    .dashboard-grid-container { display: grid !important; grid-template-columns: 260px 1fr 340px !important; min-height: 100vh !important; width: 100% !important; background-color: #fcfcfd !important; font-family: 'Inter', sans-serif !important; }
    .sidebar-panel { background: #ffffff !important; border-right: 1px solid #e4e7ec !important; padding-top: 24px !important; }
    .sidebar-brand { padding: 0 24px 24px 24px !important; display: flex !important; align-items: center !important; gap: 12px !important; border-bottom: 1px solid #f2f4f7 !important; color: #0d52cc !important; font-weight: 700 !important; font-size: 1.2rem !important; letter-spacing: -0.5px !important; }
    .sidebar-menu { list-style: none !important; padding: 20px 0 !important; margin: 0 !important; }
    .sidebar-menu li a { padding: 12px 24px !important; font-size: 0.95rem !important; display: flex !important; align-items: center !important; gap: 12px !important; color: #667085 !important; text-decoration: none !important; font-weight: 500 !important; }
    .sidebar-menu li.active a { color: #0d52cc !important; background: #eef4ff !important; border-radius: 0 24px 24px 0 !important; margin-right: 12px !important; font-weight: 600 !important; }
    .content-workspace { padding: 3rem 2.5rem !important; background: #fcfcfd !important; }
</style>

<div class="dashboard-grid-container" id="clean-dashboard-root">
    @include('layouts.sidebar')

    <div class="content-workspace">
        <div class="topic-page-wrap">
            <div class="breadcrumb-row">
                <a href="{{ route('forum.index') }}">Home</a> &rsaquo;
                <a href="{{ route('forum.index') }}">{{ $group->GroupName }}</a> &rsaquo;
                <span class="current">Forum</span>
            </div>

            <a href="{{ route('topics.create', $group) }}" class="new-topic-btn"><i class="fa-solid fa-plus"></i> New Topic</a>

            <form method="GET" action="{{ route('groups.topics', $group) }}" class="topic-toolbar">
                <div class="topic-search">
                    <i class="fa-solid fa-magnifying-glass"></i>
                    <input type="text" name="search" value="{{ $search }}" placeholder="Search topics...">
                </div>
                <select name="filter" class="filter-select" onchange="this.form.submit()">
                    <option value="all" {{ $filter === 'all' ? 'selected' : '' }}>Filter: All</option>
                    <option value="open" {{ $filter === 'open' ? 'selected' : '' }}>Open</option>
                    <option value="answered" {{ $filter === 'answered' ? 'selected' : '' }}>Answered</option>
                    <option value="discussion" {{ $filter === 'discussion' ? 'selected' : '' }}>Discussion</option>
                </select>
            </form>

            <div class="topic-list-card">
                @forelse($topics as $topic)
                    @php
                        $statusMap = [
                            'answered' => ['label' => 'ANSWERED', 'badge' => 'badge-answered', 'icon' => 'OK'],
                            'discussion' => ['label' => 'DISCUSSION', 'badge' => 'badge-discussion', 'icon' => 'D'],
                            'open' => ['label' => 'OPEN', 'badge' => 'badge-open', 'icon' => '?'],
                        ];
                        $meta = $statusMap[$topic->Status] ?? $statusMap['open'];
                    @endphp
                    <a href="{{ route('topics.show', $topic) }}" class="topic-row">
                        <div class="topic-badge {{ $topic->IsPinned ? 'badge-pinned' : $meta['badge'] }}">
                            {{ $topic->IsPinned ? 'P' : $meta['icon'] }}
                        </div>
                        <div style="flex: 1;">
                            <div style="display:flex; align-items:center; gap:8px; margin-bottom:4px;">
                                @if($topic->IsPinned)
                                    <span class="topic-status-pill" style="background:#eef4ff;color:#0d52cc;">PINNED</span>
                                @endif
                                <span class="topic-status-pill {{ $meta['badge'] }}">{{ $meta['label'] }}</span>
                            </div>
                            <p class="topic-title">{{ $topic->Title }}</p>
                            <div class="topic-meta">
                                by {{ $topic->creator?->UserName ?? $topic->creator?->name ?? 'a member' }}
                                &bull; {{ $topic->posts_count }} {{ Str::plural('reply', $topic->posts_count) }}
                                &bull; {{ $topic->CreatedAt->diffForHumans() }}
                            </div>
                        </div>
                    </a>
                @empty
                    <div style="padding: 2rem; text-align:center; color: #667085;">No topics found.</div>
                @endforelse
            </div>

            <div class="pagination-row">
                <span>Showing {{ $topics->firstItem() ?? 0 }}-{{ $topics->lastItem() ?? 0 }} of {{ $topics->total() }} topics</span>
                {{ $topics->links() }}
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        var rootGrid = document.getElementById('clean-dashboard-root');
        if (rootGrid) {
            var parentContainer = rootGrid.parentElement;
            Array.from(parentContainer.children).forEach(function (element) {
                if (element !== rootGrid && element.tagName !== 'STYLE' && element.tagName !== 'SCRIPT') {
                    element.style.setProperty('display', 'none', 'important');
                }
            });
            Array.from(document.body.children).forEach(function (element) {
                if (!element.contains(rootGrid) && element !== rootGrid && element.tagName !== 'STYLE' && element.tagName !== 'SCRIPT') {
                    element.style.setProperty('display', 'none', 'important');
                }
            });
        }
    });
</script>
@endsection