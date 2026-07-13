@extends('layouts.app')

@section('content')
@php
    $displayName = auth()->user()->UserName ?? auth()->user()->name ?? 'Student User';
    $nameParts = explode(' ', $displayName);
    $initials = collect($nameParts)->filter()->map(fn($p) => mb_substr($p, 0, 1))->take(2)->implode('');
@endphp

<style>
    .content-workspace { padding: 28px; max-width: 1200px; }
    .page-header { margin-bottom: 24px; }
    .page-header p.eyebrow { text-transform: uppercase; color: var(--text-muted); font-size: 0.75rem; font-weight: 600; letter-spacing: 0.5px; margin: 0 0 4px 0; }
    .page-header h1 { font-size: 24px; font-weight: 800; margin: 0; }

    .marks-layout { display: flex; gap: 20px; align-items: flex-start; }
    .marks-main { flex: 1; min-width: 0; }

    .cards-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 20px; margin-bottom: 24px; }
    .mark-card { background: var(--surface-card); border: 1px solid var(--surface-border); border-radius: var(--radius-lg); box-shadow: var(--shadow-soft); padding: 24px; }
    .mark-card .icon { width: 44px; height: 44px; border-radius: 10px; display: flex; align-items: center; justify-content: center; margin-bottom: 16px; }
    .mark-card .icon.success { background: var(--accent-success-bg); color: var(--accent-success); }
    .mark-card .icon.luna { background: var(--luna-lightest); color: var(--luna-dark); }
    .mark-card h5 { color: var(--text-heading); font-size: 1.05rem; font-weight: 700; margin: 0 0 8px 0; }
    .mark-card .big-value { font-size: 1.8rem; font-weight: 800; color: var(--text-heading); }
    .mark-card .sub-details { font-size: 0.85rem; color: var(--text-muted); margin-top: 10px; line-height: 1.6; }
    .mark-card .empty-note { font-size: 0.95rem; color: var(--text-muted); margin-top: 8px; }

    .panel { background: var(--surface-card); border: 1px solid var(--surface-border); border-radius: var(--radius-lg); overflow: hidden; box-shadow: var(--shadow-soft); }
    .panel-header { padding: 16px 20px; border-bottom: 1px solid var(--surface-border); }
    .panel-header h2 { font-size: 15px; font-weight: 700; margin: 0; color: var(--text-heading); }

    .quiz-row { display: flex; justify-content: space-between; align-items: center; gap: 12px; padding: 13px 20px; border-bottom: 1px solid var(--surface-border); font-size: 13.5px; }
    .quiz-row:last-child { border-bottom: none; }
    .quiz-row .score { color: var(--luna-mid); font-weight: 700; }
    .quiz-row .meta { color: var(--text-muted); font-size: 12px; margin-top: 2px; }

    .empty-state { padding: 32px 20px; text-align: center; color: var(--text-muted); font-size: 13.5px; }

    .right-info-panel { display: flex; flex-direction: column; gap: 20px; max-width: 300px; }
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
            <div class="cards-grid">
                <div class="mark-card">
                    <div class="icon success"><i class="fa-solid fa-users"></i></div>
                    <h5>Participation</h5>
                    <div class="big-value">{{ $marks['participation'] }}/10</div>
                    <div class="sub-details">
                        <div>Posts: {{ $marks['participation_details']['posts'] }}</div>
                        <div>Replies: {{ $marks['participation_details']['replies'] }}</div>
                        <div>Accepted answers: {{ $marks['participation_details']['accepted_answers'] }}</div>
                    </div>
                </div>

                <div class="mark-card">
                    <div class="icon luna"><i class="fa-solid fa-file-lines"></i></div>
                    <h5>Quiz Performance</h5>
                    @if($marks['quiz_average'] !== null)
                        <div class="big-value">{{ $marks['quiz_average'] }}%</div>
                        <div class="sub-details">Quizzes taken: {{ $marks['quizzes_taken'] }}</div>
                    @else
                        <div class="empty-note">No quizzes attempted yet.</div>
                    @endif
                </div>
            </div>

            <div class="panel">
                <div class="panel-header"><h2>Recent Quiz Results</h2></div>
                <div>
                    @forelse($marks['recent_quizzes'] as $result)
                        <div class="quiz-row">
                            <div>
                                <div style="font-weight: 600; color: var(--text-heading);">Quiz #{{ $result->QuizID }}</div>
                                <div class="meta">{{ \Carbon\Carbon::parse($result->SubmissionTime)->diffForHumans() }}</div>
                            </div>
                            <div class="score">{{ $result->Score }} pts</div>
                        </div>
                    @empty
                        <div class="empty-state">No quiz results yet.</div>
                    @endforelse
                </div>
            </div>
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
                <div class="tag"><i class="fa-solid fa-bullhorn"></i> Summary</div>
                <div class="body">Your academic performance is updated from your latest activities.</div>
            </div>
        </div>
    </div>
</div>
@endsection