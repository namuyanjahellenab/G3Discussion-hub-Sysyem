@extends('layouts.app')

@section('content')
@php
    $displayName = auth()->user()->UserName ?? auth()->user()->name ?? 'Student User';
    $initials = Str::initials($displayName);
@endphp

<style>
    .content-workspace { padding: 28px; }
    .page-header { margin-bottom: 24px; }
    .page-header p.eyebrow { text-transform: uppercase; color: var(--text-muted); font-size: 0.75rem; font-weight: 600; letter-spacing: 0.5px; margin: 0 0 4px 0; }
    .page-header h1 { font-size: 24px; font-weight: 800; margin: 0; }

    .marks-layout { display: flex; gap: 20px; align-items: flex-start; }
    .marks-main { flex: 1; min-width: 0; display: flex; flex-direction: column; gap: 20px; }

    .group-marks-panel { background: var(--surface-card); border: 1px solid var(--surface-border); border-radius: var(--radius-lg); box-shadow: var(--shadow-soft); overflow: hidden; }
    .group-marks-header { padding: 16px 20px; border-bottom: 1px solid var(--surface-border); display: flex; justify-content: space-between; align-items: center; }
    .group-marks-header h2 { font-size: 15px; font-weight: 700; margin: 0; color: var(--text-heading); }

    .group-stats-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 16px; padding: 20px; }
    .group-stat .icon { width: 40px; height: 40px; border-radius: 10px; display: flex; align-items: center; justify-content: center; margin-bottom: 10px; }
    .group-stat .icon.success { background: var(--accent-success-bg); color: var(--accent-success); }
    .group-stat .icon.luna { background: var(--luna-lightest); color: var(--luna-dark); }
    .group-stat h5 { color: var(--text-heading); font-size: 0.9rem; font-weight: 700; margin: 0 0 6px 0; }
    .group-stat .big-value { font-size: 1.5rem; font-weight: 800; color: var(--text-heading); }
    .group-stat .sub-details { font-size: 0.8rem; color: var(--text-muted); margin-top: 6px; line-height: 1.5; }
    .group-stat .empty-note { font-size: 0.85rem; color: var(--text-muted); margin-top: 4px; }

    .quiz-row { display: flex; justify-content: space-between; align-items: center; gap: 12px; padding: 13px 20px; border-top: 1px solid var(--surface-border); font-size: 13.5px; }
    .quiz-row .score { color: var(--luna-mid); font-weight: 700; }
    .quiz-row .meta { color: var(--text-muted); font-size: 12px; margin-top: 2px; }

    .empty-state { padding: 32px 20px; text-align: center; color: var(--text-muted); font-size: 13.5px; }

    .right-info-panel { display: flex; flex-direction: column; gap: 20px; max-width: 300px; }
    .panel { background: var(--surface-card); border: 1px solid var(--surface-border); border-radius: var(--radius-lg); overflow: hidden; box-shadow: var(--shadow-soft); }
    .profile-mini { display: flex; align-items: center; gap: 10px; padding: 16px 20px; }
    .profile-mini .avatar { width: 40px; height: 40px; border-radius: 50%; background: var(--luna-mid); color: #fff; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 13px; }
    .profile-mini .name { font-weight: 700; color: var(--text-heading); font-size: 13.5px; }
    .profile-mini .role { color: var(--text-muted); font-size: 11.5px; }

    .announcement-banner { background: var(--luna-mid); color: #fff; border-radius: var(--radius-lg); padding: 18px 20px; }
    .announcement-banner .tag { display: flex; align-items: center; gap: 8px; text-transform: uppercase; font-size: 0.72rem; font-weight: 700; letter-spacing: 0.5px; opacity: 0.9; margin-bottom: 8px; }
    .announcement-banner .body { font-size: 0.85rem; font-weight: 500; line-height: 1.4; }

    @media (max-width: 1024px) {
        .marks-layout { flex-direction: column; }
        .right-info-panel { max-width: 100%; }
    }
</style>

<div class="content-workspace">
    <div class="page-header">
        <p class="eyebrow">Performance</p>
        <h1>Marks</h1>
    </div>

    <div class="marks-layout">
        <div class="marks-main">
            @forelse($groups as $group)
                <div class="group-marks-panel">
                    <div class="group-marks-header">
                        <h2>{{ $group['group_name'] }}</h2>
                    </div>

                    <div class="group-stats-row">
                        <div class="group-stat">
                            <div class="icon success"><i class="fa-solid fa-users"></i></div>
                            <h5>Participation</h5>
                            <div class="big-value">{{ $group['participation'] }}/10</div>
                            <div class="sub-details">
                                <div>Posts: {{ $group['post_count'] }}</div>
                                <div>Replies: {{ $group['reply_count'] }}</div>
                                <div>Correct Answers: {{ $group['accepted_count'] }}</div>
                            </div>
                        </div>

                        <div class="group-stat">
                            <div class="icon luna"><i class="fa-solid fa-file-lines"></i></div>
                            <h5>Quiz Performance</h5>
                            @if($group['quiz_average'] !== null)
                                <div class="big-value">{{ $group['quiz_average'] }}%</div>
                                <div class="sub-details">Quizzes taken: {{ $group['quizzes_taken'] }}</div>
                            @else
                                <div class="empty-note">No quizzes attempted yet.</div>
                            @endif
                        </div>
                    </div>

                    @if($group['quizzes']->isNotEmpty())
                        <div>
                            @foreach($group['quizzes'] as $result)
                                <div class="quiz-row">
                                    <div>
                                        <div style="font-weight: 600; color: var(--text-heading);">{{ $result['title'] }}</div>
                                        <div class="meta">
                                            <span class="live-timestamp" data-timestamp="{{ \Carbon\Carbon::parse($result['submitted_at_iso'])->timestamp }}">{{ \Carbon\Carbon::parse($result['submitted_at_iso'])->diffForHumans() }}</span>
                                            @if($result['auto_submitted'])
                                                &bull; auto-submitted
                                            @endif
                                        </div>
                                    </div>
                                    <div class="score">{{ $result['score'] }} pts</div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            @empty
                <div class="group-marks-panel">
                    <div class="empty-state">Join a group to start earning participation and quiz marks.</div>
                </div>
            @endforelse
        </div>

        <div class="right-info-panel">
            <section class="panel">
                <div class="profile-mini">
                    <div class="avatar">{{ strtoupper($initials ?: 'S') }}</div>
                    <div>
                        <div class="name">{{ $displayName }}</div>
                        <div class="role">Student Account</div>
                    </div>
                </div>
            </section>

            <div class="announcement-banner">
                <div class="tag"><i class="fa-solid fa-bullhorn"></i> How marks work</div>
                <div class="body">Participation is scored per group, relative to the platform's most active participant. Quiz marks are shown per group as attempted.</div>
            </div>
        </div>
    </div>
</div>

<script>
    // Same live-timestamp mechanism used on the messages page - re-derives
    // each quiz's "X ago" text from its raw data-timestamp every tick instead
    // of leaving whatever diffForHumans() said at page-load frozen there.
    function updateTimestamps() {
        const now = Math.floor(Date.now() / 1000);
        document.querySelectorAll('.live-timestamp').forEach(el => {
            const timestamp = parseInt(el.getAttribute('data-timestamp'));
            if (!timestamp) return;

            const diff = now - timestamp;
            if (diff < 60) {
                el.textContent = 'Just now';
            } else if (diff < 3600) {
                el.textContent = `${Math.floor(diff / 60)}m ago`;
            } else if (diff < 86400) {
                el.textContent = `${Math.floor(diff / 3600)}h ago`;
            } else {
                el.textContent = `${Math.floor(diff / 86400)}d ago`;
            }
        });
    }
    updateTimestamps();
    setInterval(updateTimestamps, 60000);
</script>
@endsection
