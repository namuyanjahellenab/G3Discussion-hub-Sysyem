@extends('layouts.app')

@section('content')
<style>
    .content-workspace { padding: 28px; }
    .page-header { margin-bottom: 24px; }
    .page-header p.eyebrow { text-transform: uppercase; color: var(--text-muted); font-size: 0.75rem; font-weight: 600; letter-spacing: 0.5px; margin: 0 0 4px 0; }
    .page-header h1 { font-size: 24px; font-weight: 800; margin: 0; }

    .panel { background: var(--surface-card); border: 1px solid var(--surface-border); border-radius: var(--radius-lg); overflow: hidden; box-shadow: var(--shadow-soft); }
    .panel-body { padding: 24px; }

    .field-grid { display: grid; gap: 16px; }

    .btn { display: inline-flex; align-items: center; justify-content: center; gap: 8px; padding: 0.55rem 1rem; border-radius: var(--radius-md); font-size: 13px; font-weight: 600; text-decoration: none; border: 1px solid transparent; box-shadow: var(--shadow-soft); cursor: pointer; }
    .btn-primary { background: var(--luna-mid); color: #fff; }
    .btn-primary:hover { background: var(--luna-dark); color: #fff; }

    .empty-state { padding: 24px; text-align: center; color: var(--text-muted); font-size: 13.5px; }

    .exclude-panel { border: 1px solid var(--surface-border); border-radius: var(--radius-md); padding: 12px; max-height: 220px; overflow-y: auto; margin-top: 8px; }
    .exclude-option { display: flex; align-items: center; gap: 8px; padding: 6px 4px; font-size: 13px; }
    .exclude-hint { font-size: 0.78rem; color: var(--text-muted); margin-top: 6px; }
</style>

<div class="content-workspace">
    <div class="page-header">
        <p class="eyebrow">{{ $isAdmin ? 'Admin' : 'Lecturer' }}</p>
        <h1>Create Announcement</h1>
    </div>

    <div class="panel">
        <div class="panel-body">
            @if($groups->isEmpty())
                <div class="empty-state">There are no groups set up yet.</div>
            @else
                <form method="POST" action="{{ route('announcements.store') }}">
                    @csrf
                    <div class="field-grid">
                        <div>
                            <label class="form-label">Audience</label>
                            <select name="GroupID" id="announcementGroup" class="form-select" {{ $isAdmin ? '' : 'required' }}>
                                @if($isAdmin)
                                    <option value="">Campus-wide (every active student)</option>
                                @else
                                    <option value="">Select a group...</option>
                                @endif
                                @foreach($groups as $group)
                                    <option value="{{ $group->GroupID }}">{{ $group->GroupName }} ({{ $group->member_count }})</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="form-label">Message</label>
                            <textarea name="message" class="form-control" rows="5" maxlength="1000" placeholder="Write your announcement..." required></textarea>
                        </div>
                        <div>
                            <label class="form-label">Exclude specific members (optional)</label>
                            @foreach($groups as $group)
                                <div class="exclude-panel" data-group-panel="{{ $group->GroupID }}" style="display: none;">
                                    @forelse($group->students as $membership)
                                        <label class="exclude-option">
                                            <input type="checkbox" name="exclude[]" value="{{ $membership->UserID }}">
                                            {{ $membership->user->UserName ?? $membership->user->name ?? 'Member' }}
                                        </label>
                                    @empty
                                        <span class="empty-state" style="padding: 8px;">No active members in this group.</span>
                                    @endforelse
                                </div>
                            @endforeach
                            <p class="exclude-hint">Checked members will not receive this announcement.</p>
                        </div>
                    </div>
                    <div style="margin-top: 20px; display: flex; gap: 10px;">
                        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-bullhorn"></i> Send Announcement</button>
                        <a href="{{ route('announcements.index') }}" class="btn btn-outline-secondary">Cancel</a>
                    </div>
                </form>
            @endif
        </div>
    </div>
</div>

<script>
(function () {
    const select = document.getElementById('announcementGroup');
    if (!select) return;

    function syncExcludePanels() {
        document.querySelectorAll('[data-group-panel]').forEach((panel) => {
            panel.style.display = panel.dataset.groupPanel === select.value ? 'block' : 'none';
        });
    }

    select.addEventListener('change', syncExcludePanels);
    syncExcludePanels();
})();
</script>
@endsection
