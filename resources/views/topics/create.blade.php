@extends('layouts.app')

{{-- This page renders its own inline error block below — skip the layout's
     generic global banner to avoid showing the same error twice. --}}
@php($hideGlobalErrors = true)

@section('content')
<style>
    .content-workspace { padding: 28px; max-width: 700px; margin: 0 auto; }
    .page-header { margin-bottom: 20px; }
    .page-header p.eyebrow { text-transform: uppercase; color: var(--text-muted); font-size: 0.75rem; font-weight: 600; letter-spacing: 0.5px; margin: 0 0 4px 0; }
    .page-header h1 { font-size: 22px; font-weight: 800; margin: 0; }

    .panel { background: var(--surface-card); border: 1px solid var(--surface-border); border-radius: var(--radius-lg); overflow: hidden; box-shadow: var(--shadow-soft); }
    .panel-body { padding: 24px; }
    .field-grid { display: grid; gap: 16px; }
    .form-label { font-weight: 500; color: var(--text-heading); font-size: 0.88rem; }

    .btn { display: inline-flex; align-items: center; justify-content: center; gap: 8px; padding: 0.55rem 1rem; border-radius: var(--radius-md); font-size: 13px; font-weight: 600; text-decoration: none; border: 1px solid transparent; box-shadow: var(--shadow-soft); cursor: pointer; }
    .btn-primary { background: var(--luna-mid); color: #fff; }
    .btn-primary:hover { background: var(--luna-dark); color: #fff; }
    .btn-outline { background: #fff; border: 1px solid var(--surface-border); color: var(--text-body); }
    .btn-outline.active { border-color: var(--luna-mid); color: var(--luna-mid); background: var(--luna-lightest); }
    .btn-outline:hover { border-color: var(--luna-mid); color: var(--luna-mid); }

    .audience-toggle { display: flex; gap: 8px; }

    .attach-row { display: flex; gap: 8px; margin-top: 4px; }
    .attach-row .btn-outline { font-size: 12px; padding: 0.4rem 0.75rem; }
    .attach-filename { font-size: 0.78rem; color: var(--text-muted); margin-top: 6px; }

    .exclude-panel { display: none; border: 1px solid var(--surface-border); border-radius: var(--radius-md); padding: 12px; margin-top: 12px; }
    .exclude-panel.open { display: block; }
    .exclude-panel input[type="text"] { width: 100%; border: 1px solid var(--surface-border); border-radius: var(--radius-md); padding: 8px 12px; margin-bottom: 10px; font-size: 13px; }
    .member-row { display: flex; align-items: center; gap: 10px; padding: 7px 4px; border-radius: var(--radius-md); }
    .member-row:hover { background: var(--surface-bg); }
    .member-row .avatar { width: 30px; height: 30px; border-radius: 50%; background: var(--luna-mid); color: #fff; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 11px; flex-shrink: 0; }
    .member-row .meta { flex: 1; }
    .member-row .mname { font-size: 13px; font-weight: 600; color: var(--text-heading); }

    .switch { width: 34px; height: 19px; border-radius: 20px; background: var(--luna-mid); position: relative; cursor: pointer; flex-shrink: 0; border: none; }
    .switch.off { background: var(--surface-border); }
    .switch::after { content: ''; position: absolute; top: 2px; left: 17px; width: 15px; height: 15px; background: #fff; border-radius: 50%; transition: 0.15s; }
    .switch.off::after { left: 2px; }

    .audience-count { font-size: 0.78rem; color: var(--text-muted); margin-top: 10px; }
    .audience-count strong { color: var(--text-heading); }

    .empty-state { padding: 16px; text-align: center; color: var(--text-muted); font-size: 13px; }
</style>

<div class="content-workspace">
    <div class="page-header">
        <p class="eyebrow">{{ $group->GroupName }}</p>
        <h1>New Topic</h1>
    </div>

    <div class="panel">
        <div class="panel-body">
            @if($errors->any())
                <div class="alert alert-danger">{{ $errors->first() }}</div>
            @endif

            <form method="POST" action="{{ route('topics.store') }}" enctype="multipart/form-data">
                @csrf
                <input type="hidden" name="GroupID" value="{{ $group->GroupID }}">
                <input type="hidden" name="audience" id="audienceField" value="everyone">

                <div class="field-grid">
                    <div>
                        <label class="form-label">Title</label>
                        <input type="text" name="Title" class="form-control" value="{{ old('Title') }}" required>
                    </div>

                    <div>
                        <label class="form-label">Your question or discussion topic</label>
                        <textarea name="Content" class="form-control" rows="5" required>{{ old('Content') }}</textarea>

                        <input type="file" name="attachment" id="topicAttachment" style="display:none;">
                        <div class="attach-row">
                            <button type="button" class="btn-outline btn" onclick="document.getElementById('topicAttachment').click()">
                                <i class="fa-solid fa-paperclip"></i> Attach file
                            </button>
                            <button type="button" class="btn-outline btn" onclick="document.getElementById('topicAttachment').setAttribute('accept','image/*'); document.getElementById('topicAttachment').click()">
                                <i class="fa-solid fa-image"></i> Attach image
                            </button>
                        </div>
                        <div class="attach-filename" id="attachFilename"></div>
                    </div>

                    <div>
                        <label class="form-label">Post to</label>
                        <div class="audience-toggle">
                            <button type="button" class="btn btn-outline active" id="audienceEveryoneBtn" onclick="setAudience('everyone')">
                                Everyone ({{ $groupMembers->count() }})
                            </button>
                            <button type="button" class="btn btn-outline" id="audienceCustomBtn" onclick="setAudience('custom')">
                                Custom audience &#9662;
                            </button>
                        </div>

                        <div class="exclude-panel" id="excludePanel">
                            <input type="text" id="memberSearch" placeholder="Search members..." oninput="filterMembers()">
                            @forelse($groupMembers as $membership)
                                @php $memberUser = $membership->user; @endphp
                                <div class="member-row" data-name="{{ strtolower($memberUser->UserName ?? $memberUser->name ?? '') }}">
                                    <div class="avatar">{{ Str::initials($memberUser->UserName ?? $memberUser->name ?? '?') }}</div>
                                    <div class="meta">
                                        <div class="mname">{{ $memberUser->UserName ?? $memberUser->name ?? 'Member' }}</div>
                                    </div>
                                    <input type="hidden" name="exclude[]" value="{{ $memberUser->UserID }}" disabled class="exclude-input">
                                    <button type="button" class="switch" onclick="toggleMember(this)"></button>
                                </div>
                            @empty
                                <div class="empty-state">No other members in this group yet.</div>
                            @endforelse
                            <div class="audience-count">
                                Visible to <strong id="audienceCount">{{ $groupMembers->count() + 1 }} of {{ $groupMembers->count() + 1 }}</strong> members
                            </div>
                        </div>
                    </div>
                </div>

                <div style="margin-top: 20px; display: flex; gap: 10px;">
                    <button type="submit" class="btn btn-primary">Post Topic</button>
                    <a href="{{ route('groups.topics', $group) }}" class="btn btn-outline">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
const totalMembers = {{ $groupMembers->count() + 1 }};

document.getElementById('topicAttachment').addEventListener('change', function () {
    document.getElementById('attachFilename').textContent = this.files[0] ? this.files[0].name : '';
});

function setAudience(mode) {
    document.getElementById('audienceField').value = mode;
    document.getElementById('audienceEveryoneBtn').classList.toggle('active', mode === 'everyone');
    document.getElementById('audienceCustomBtn').classList.toggle('active', mode === 'custom');
    document.getElementById('excludePanel').classList.toggle('open', mode === 'custom');
}

function toggleMember(button) {
    const excluded = button.classList.toggle('off');
    const input = button.previousElementSibling;
    input.disabled = !excluded;
    updateAudienceCount();
}

function updateAudienceCount() {
    const excludedCount = document.querySelectorAll('.exclude-input:not([disabled])').length;
    document.getElementById('audienceCount').textContent = `${totalMembers - excludedCount} of ${totalMembers}`;
}

function filterMembers() {
    const term = document.getElementById('memberSearch').value.toLowerCase();
    document.querySelectorAll('.member-row').forEach((row) => {
        row.style.display = row.dataset.name.includes(term) ? 'flex' : 'none';
    });
}
</script>
@endsection
