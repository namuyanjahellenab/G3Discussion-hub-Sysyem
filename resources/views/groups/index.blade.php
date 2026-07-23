@extends('layouts.app')

@section('content')
{{-- Card styling intentionally mirrors dashboard/index.blade.php's "My
     Groups" widget (.group-card / .group-card-icon / .groups-grid) so this
     full listing looks like a bigger version of the same card, not a
     different design. --}}
<style>
    .groups-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 16px; }
    .group-card { position: relative; background: var(--surface-bg); border: 1px solid var(--surface-border); border-radius: var(--radius-md); padding: 20px; display: flex; flex-direction: column; align-items: center; text-align: center; }
    .group-card-icon { width: 40px; height: 40px; background: var(--luna-lightest); color: var(--luna-dark); border-radius: 10px; display: flex; align-items: center; justify-content: center; margin-bottom: 12px; }
    .group-card h5 { font-size: 1rem; font-weight: 700; margin: 0 0 2px 0; color: var(--text-heading); }
    .group-card p { color: var(--text-muted); font-size: 0.8rem; margin: 0 0 16px 0; }
    .group-card .code-badge { position: absolute; top: 14px; left: 14px; font-size: 0.68rem; font-weight: 700; text-transform: uppercase; padding: 3px 8px; border-radius: 999px; }
    .group-card .code-badge-right { position: absolute; top: 14px; right: 14px; font-size: 0.68rem; font-weight: 700; text-transform: uppercase; padding: 3px 8px; border-radius: 999px; }
</style>

<div class="container-fluid py-5">
    <div class="mb-4">
        <h1 class="fw-bold mb-1" style="font-size: 1.75rem; color: var(--text-heading);">View Discussion Groups</h1>
    </div>
    <div class="mb-5">
        <form method="GET" action="{{ route('groups.index') }}" class="input-group shadow-sm" style="border-radius: 8px; overflow: hidden;">
            <span class="input-group-text bg-white border-end-0 text-muted ps-3">
                <i class="bi bi-search"></i>
            </span>
            <input type="text" name="search" value="{{ $search ?? '' }}" class="form-control border-start-0 ps-2 py-2.5" placeholder="Search for courses, lecturers or topics..." style="font-size: 0.95rem;">
            @if(($search ?? '') !== '')
                <a href="{{ route('groups.index') }}" class="btn btn-outline-secondary px-3 d-flex align-items-center" title="Clear search">
                    <i class="bi bi-x-lg"></i>
                </a>
            @endif
            <button class="btn btn-primary px-4 fw-medium d-flex align-items-center" type="submit">
                Search <i class="bi bi-arrow-right short ms-2"></i>
            </button>
        </form>
    </div>

    @if(isset($trendingGroups) && $trendingGroups->isNotEmpty())
        <h2 class="fw-bold mb-3" style="font-size: 1.15rem; color: var(--text-heading);">
            <i class="bi bi-graph-up-arrow me-1" style="color: var(--luna-mid);"></i> Trending Groups
        </h2>
    @endif

    {{-- One continuous grid so trending cards flow straight into the rest —
         no separate row-wrapping section between them. --}}
    <div class="groups-grid">
        @if(isset($trendingGroups))
            @foreach($trendingGroups as $group)
                <div class="group-card">
                    <span class="code-badge" style="background-color: var(--accent-amber-bg); color: var(--accent-amber);">Trending</span>
                    <span class="code-badge-right" style="background-color: var(--luna-subtle-bg); color: var(--luna-mid);">{{ $group->CourseCode ?? 'CSC301' }}</span>
                    <div class="group-card-icon"><i class="fa-solid fa-users"></i></div>
                    <h5>{{ $group->GroupName }}</h5>
                    <p>{{ $group->member_count ?? 0 }} members</p>
                    @include('groups.partials.group-card-actions', ['group' => $group])
                </div>
            @endforeach
        @endif
        @foreach($groups as $group)
            <div class="group-card">
                <span class="code-badge" style="background-color: {{ $group->userJoined ? 'var(--accent-danger-bg)' : 'var(--luna-subtle-bg)' }}; color: {{ $group->userJoined ? 'var(--accent-danger)' : 'var(--luna-mid)' }};">
                    {{ $group->CourseCode ?? 'CSC301' }}
                </span>
                <div class="group-card-icon"><i class="fa-solid fa-users"></i></div>
                <h5>{{ $group->GroupName }}</h5>
                <p>{{ $group->member_count ?? 0 }} members</p>
                @include('groups.partials.group-card-actions', ['group' => $group])
            </div>
        @endforeach
        @if($groups->isEmpty())
            <div class="text-center" style="grid-column: 1 / -1; padding: 40px 0; color: var(--text-muted);">
                @if(($search ?? '') !== '')
                    No groups match "{{ $search }}".
                @else
                    No groups yet.
                @endif
            </div>
        @endif
    </div>

    <div class="text-center mt-5">
        <p class="text-muted italic small" style="font-size: 0.85rem;">
            You can join additional groups at any time from your dashboard. Some groups may require administrative approval.
        </p>
    </div>
</div>
@endsection
