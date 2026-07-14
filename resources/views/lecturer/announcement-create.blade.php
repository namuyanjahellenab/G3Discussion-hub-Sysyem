@extends('layouts.app')

@section('content')
<style>
    .content-workspace { padding: 28px; max-width: 700px; }
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
</style>

<div class="content-workspace">
    <div class="page-header">
        <p class="eyebrow">Lecturer</p>
        <h1>Create Announcement</h1>
    </div>

    <div class="panel">
        <div class="panel-body">
            @if($groups->isEmpty())
                <div class="empty-state">
                    You don't have any courses set up yet — schedule a quiz for a group first, then you'll be able to send announcements to it here.
                </div>
            @else
                <form method="POST" action="{{ route('announcements.store') }}">
                    @csrf
                    <div class="field-grid">
                        <div>
                            <label class="form-label">Course</label>
                            <select name="GroupID" class="form-select" required>
                                <option value="">Select a course...</option>
                                @foreach($groups as $group)
                                    <option value="{{ $group->GroupID }}">{{ $group->GroupName }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="form-label">Message</label>
                            <textarea name="message" class="form-control" rows="5" maxlength="1000" placeholder="Write your announcement..." required></textarea>
                        </div>
                    </div>
                    <div style="margin-top: 20px; display: flex; gap: 10px;">
                        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-bullhorn"></i> Send Announcement</button>
                        <a href="{{ route('dashboard') }}" class="btn btn-outline-secondary">Cancel</a>
                    </div>
                </form>
            @endif
        </div>
    </div>
</div>
@endsection
