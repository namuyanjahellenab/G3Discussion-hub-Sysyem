@extends('layouts.app')

@section('content')
<style>
    .content { padding: 28px; max-width: 720px; }

    .page-header h1 {
        font-size: 24px;
        font-weight: 800;
        letter-spacing: -0.3px;
        font-family: var(--font-display);
    }
    .page-header p {
        color: var(--text-muted);
        font-size: 13.5px;
        margin-top: 4px;
        font-family: var(--font-body);
    }

    .criteria-card {
        background: var(--surface-card);
        border: 1px solid var(--surface-border);
        border-top: 3px solid var(--luna-mid);
        border-radius: var(--radius-lg);
        box-shadow: var(--shadow-soft);
        padding: 32px 36px;
        margin-top: 24px;
    }

    .field { margin-bottom: 20px; }
    .field label {
        display: block; font-size: 13px; font-weight: 700;
        color: var(--text-heading); margin-bottom: 6px;
    }
    .field select, .field input {
        width: 100%; padding: 10px 12px; border: 1.5px solid var(--surface-border);
        border-radius: 8px; font-size: 14px; font-family: inherit;
    }
    .field select:focus, .field input:focus { outline: none; border-color: var(--luna-mid); }
    .field-hint { font-size: 12px; color: var(--text-muted); margin-top: 4px; }

    .btn-save {
        background: var(--luna-mid); color: #fff; border: none;
        border-radius: 8px; padding: 11px 24px; font-size: 13.5px; font-weight: 700;
        cursor: pointer; transition: background 0.15s;
    }
    .btn-save:hover { background: var(--luna-dark); }

    .alert-success {
        background: var(--accent-success-bg); color: #1B6B3A; border: 1px solid #ABE5C8;
        border-radius: 8px; padding: 12px 16px; font-size: 13.5px; margin-top: 24px;
    }
</style>

<div class="content">
    <div class="page-header">
        <h1>Participation Criteria</h1>
        <p>Set how many marks students earn for posting, replying, and getting an answer accepted.</p>
    </div>

    @if (session('status'))
        <div class="alert-success">{{ session('status') }}</div>
    @endif

    <div class="criteria-card">
        <form method="GET" action="{{ route('participation-criteria.edit') }}" id="groupSwitchForm">
            <div class="field">
                <label for="group_id">Apply to</label>
                <select name="group_id" id="group_id" onchange="document.getElementById('groupSwitchForm').submit()">
                    <option value="" {{ $groupId === null ? 'selected' : '' }}>All groups (platform default)</option>
                    @foreach ($groups as $group)
                        <option value="{{ $group->GroupID }}" {{ $groupId === $group->GroupID ? 'selected' : '' }}>
                            {{ $group->GroupName }}
                        </option>
                    @endforeach
                </select>
                <div class="field-hint">A group with its own criteria uses those values instead of the platform default.</div>
            </div>
        </form>

        <form method="POST" action="{{ route('participation-criteria.update') }}">
            @csrf
            <input type="hidden" name="GroupID" value="{{ $groupId }}">

            <div class="field">
                <label for="PointsPerPost">Points per post</label>
                <input type="text" inputmode="decimal" name="PointsPerPost" id="PointsPerPost" value="{{ old('PointsPerPost', rtrim(rtrim(number_format($criteria->PointsPerPost, 2), '0'), '.')) }}">
            </div>

            <div class="field">
                <label for="PointsPerReply">Points per reply</label>
                <input type="text" inputmode="decimal" name="PointsPerReply" id="PointsPerReply" value="{{ old('PointsPerReply', rtrim(rtrim(number_format($criteria->PointsPerReply, 2), '0'), '.')) }}">
            </div>

            <div class="field">
                <label for="PointsPerAcceptedAnswer">Points per accepted answer</label>
                <input type="text" inputmode="decimal" name="PointsPerAcceptedAnswer" id="PointsPerAcceptedAnswer" value="{{ old('PointsPerAcceptedAnswer', rtrim(rtrim(number_format($criteria->PointsPerAcceptedAnswer, 2), '0'), '.')) }}">
            </div>

            <button type="submit" class="btn-save">Save Criteria</button>
        </form>
    </div>
</div>
@endsection
