@extends('layouts.app')

@section('content')
@php
    $initials = Str::initials(auth()->user()->name ?? auth()->user()->UserName ?? '');
@endphp

<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
@vite(['resources/css/icons.css'])

<style>
    {{-- Was hardcoded to #fcfcfd/#e4e7ec/#0d52cc - an off-brand blue that
         didn't move with the LUNA theme picker at all, unlike every other
         page's --luna-mid. Swapped for the same custom properties Dashboard/
         Forum/Topics already use, so this page follows the selected theme
         (including Black/Brown/Green) instead of staying a fixed blue. --}}
    {{-- Deliberately NOT named .dashboard-grid-container - that class is
         shared with messages/index.blade.php's genuinely 3-column layout
         (its own inline sidebar + content + right panel). This page has no
         inline sidebar column of its own (it uses the standard
         .app-sidebar-wrapper instead), so it needs its own 2-column grid. --}}
    .quiz-grid-container { display: grid !important; grid-template-columns: 1fr 340px !important; min-height: 100vh !important; width: 100% !important; background-color: var(--surface-bg) !important; font-family: 'Inter', sans-serif !important; }
    .content-workspace { padding: 3rem 2.5rem !important; background: var(--surface-bg) !important; }
    .right-info-panel { border-left: 1px solid var(--surface-border) !important; background: var(--surface-card) !important; padding: 3rem 2rem !important; display: flex !important; flex-direction: column !important; gap: 2.5rem !important; box-sizing: border-box !important; }
    .student-profile-box { background: var(--surface-bg) !important; border: 1px solid var(--surface-border) !important; border-radius: 14px !important; padding: 1.25rem !important; display: flex !important; align-items: center !important; gap: 12px !important; }
    .profile-avatar { width: 44px !important; height: 44px !important; background: var(--luna-mid) !important; color: white !important; font-weight: 700 !important; border-radius: 50% !important; display: flex !important; align-items: center !important; justify-content: center !important; }
    .announcement-banner { background: var(--luna-mid) !important; color: white !important; border-radius: 12px !important; padding: 1.25rem !important; margin-top: auto !important; }

    {{-- Mini stat cards fell back to Bootstrap's plain .card (flat white,
         gray border, no brand accent) - matched to Dashboard/Forum's
         .stat-card treatment (colored top border) instead. --}}
    .quiz-stat-card { background: var(--surface-card); border: 1px solid var(--surface-border); border-top: 3px solid var(--luna-mid); border-radius: var(--radius-lg, 14px); box-shadow: var(--shadow-soft); }
    .quiz-stat-card h5 { color: var(--text-muted); font-size: 12px; text-transform: uppercase; letter-spacing: 0.4px; }
    .quiz-panel-card { background: var(--surface-card); border: 1px solid var(--surface-border); border-radius: var(--radius-lg, 14px); box-shadow: var(--shadow-soft); }
</style>

<div class="quiz-grid-container" id="clean-dashboard-root">

    <div class="content-workspace">
        <div style="margin-bottom: 2rem;">
            <p style="text-transform: uppercase; color: var(--text-muted); font-size: 0.75rem; font-weight: 600; letter-spacing: 0.5px; margin: 0 0 4px 0;">Assessment</p>
            <h1 style="letter-spacing: -0.5px; color: var(--text-heading); font-size: 2rem; font-weight:700; margin: 0;">QUIZZES</h1>
        </div>

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 20px; margin-bottom: 24px;">
            <div class="quiz-stat-card" style="padding: 20px;"><h5 style="font-weight:700;">Available now</h5><div style="font-size:1.4rem; color: var(--luna-mid);">{{ $available->count() }}</div></div>
            <div class="quiz-stat-card" style="padding: 20px;"><h5 style="font-weight:700;">Upcoming</h5><div style="font-size:1.4rem; color: var(--luna-mid);">{{ $upcoming->count() }}</div></div>
            <div class="quiz-stat-card" style="padding: 20px;"><h5 style="font-weight:700;">Completed</h5><div style="font-size:1.4rem; color: var(--luna-mid);">{{ $completed->count() }}</div></div>
            <div class="quiz-stat-card" style="padding: 20px;"><h5 style="font-weight:700;">Total Score</h5><div style="font-size:1.4rem; color: var(--luna-mid);">{{ $scores->sum('Score') }}</div></div>
        </div>

        <div class="quiz-panel-card" style="padding: 24px;">

            <div style="display: flex; gap: 0.5rem; border-bottom: 1px solid var(--surface-border); margin-bottom: 1rem;" id="quiz-tabs">
                <button class="quiz-tab active" data-tab="available">Available <span>({{ $available->count() }})</span></button>
                <button class="quiz-tab" data-tab="upcoming">Upcoming <span>({{ $upcoming->count() }})</span></button>
                <button class="quiz-tab" data-tab="completed">Completed <span>({{ $completed->count() }})</span></button>
                <button class="quiz-tab" data-tab="missed">Missed <span>({{ $missed->count() }})</span></button>
            </div>

            {{-- AVAILABLE --}}
            <div class="quiz-tab-panel" data-panel="available">
                @forelse($available as $quiz)
                    <a href="{{ route('quiz.take', $quiz->QuizID) }}" class="quiz-row">
                        <div>
                            <div class="quiz-row__title">{{ $quiz->Title ?? 'Untitled quiz' }}</div>
                            <div class="quiz-row__meta">Active now</div>
                        </div>
                        <span class="badge bg-success">Take now</span>
                    </a>
                @empty
                    <div class="quiz-empty">No quizzes available right now.</div>
                @endforelse
            </div>

            {{-- UPCOMING --}}
            <div class="quiz-tab-panel" data-panel="upcoming" style="display:none;">
                @forelse($upcoming as $quiz)
                    <div class="quiz-row">
                        <div>
                            <div class="quiz-row__title">{{ $quiz->Title ?? 'Untitled quiz' }}</div>
                            <div class="quiz-row__meta">Starts {{ $quiz->StartTime->diffForHumans() }}</div>
                        </div>
                        <span class="badge bg-secondary">Upcoming</span>
                    </div>
                @empty
                    <div class="quiz-empty">No upcoming quizzes.</div>
                @endforelse
            </div>

            {{-- COMPLETED --}}
            <div class="quiz-tab-panel" data-panel="completed" style="display:none;">
                @forelse($completed as $result)
                    <div class="quiz-row">
                        <div>
                            <div class="quiz-row__title">{{ $result->quiz->Title ?? 'Untitled quiz' }}</div>
                            <div class="quiz-row__meta">Score: {{ $result->Score }} · {{ \Carbon\Carbon::parse($result->SubmissionTime)->diffForHumans() }}</div>
                        </div>
                        <a href="{{ route('quiz.my-review', $result->QuizID) }}" class="btn btn-outline-primary" style="font-size: 0.82rem; padding: 0.35rem 0.8rem;">
                            Review answers
                        </a>
                    </div>
                @empty
                    <div class="quiz-empty">You haven't completed any quizzes yet.</div>
                @endforelse
            </div>

            {{-- MISSED --}}
            <div class="quiz-tab-panel" data-panel="missed" style="display:none;">
                @forelse($missed as $quiz)
                    <div class="quiz-row">
                        <div>
                            <div class="quiz-row__title">{{ $quiz->Title ?? 'Untitled quiz' }}</div>
                            <div class="quiz-row__meta">Closed {{ $quiz->StartTime->copy()->addMinutes($quiz->Duration)->diffForHumans() }}</div>
                        </div>
                        <span class="badge bg-danger">Missed</span>
                    </div>
                @empty
                    <div class="quiz-empty">No missed quizzes.</div>
                @endforelse
            </div>

        </div>
    </div>

    <div class="right-info-panel">
        <div>
            <div style="color: var(--text-muted); font-weight: 700; text-transform: uppercase; font-size: 0.75rem; letter-spacing: 0.5px; margin-bottom: 8px;">Student Info</div>
            <div class="student-profile-box">
                <div class="profile-avatar">{{ $initials ?: 'SU' }}</div>
                <div>
                    <div style="color: var(--text-heading); font-weight: 700; font-size: 0.95rem;">{{ auth()->user()->UserName ?? auth()->user()->name }}</div>
                    <div style="color: var(--text-muted); font-size: 0.8rem;">Student Account</div>
                </div>
            </div>
        </div>
        <div class="announcement-banner">
            <div style="display:flex;align-items:center;gap:8px;margin-bottom:8px;"><i class="fa-solid fa-bullhorn"></i><span style="text-transform: uppercase; font-size: 0.75rem; font-weight: 700; letter-spacing: 0.5px; opacity: 0.9;">Note</span></div>
            <div style="font-size: 0.88rem; font-weight: 500; line-height: 1.4;">Quizzes and scores are available as soon as they are stored in the system.</div>
        </div>
    </div>
</div>

<style>
    .quiz-tab {
        background: none; border: none; padding: 0.6rem 1rem;
        font-size: 0.88rem; font-weight: 600; color: var(--text-muted);
        cursor: pointer; border-bottom: 2px solid transparent;
    }
    .quiz-tab.active { color: var(--luna-mid); border-bottom-color: var(--luna-mid); }
    .quiz-tab span { font-weight: 400; }

    .quiz-row {
        display: flex; justify-content: space-between; align-items: center;
        padding: 12px 4px; border-bottom: 1px solid var(--surface-border);
        text-decoration: none; color: inherit;
    }
    .quiz-row:last-child { border-bottom: none; }
    .quiz-row__title { font-weight: 700; color: var(--text-heading); }
    .quiz-row__meta { color: var(--text-muted); font-size: 0.85rem; }
    .quiz-empty { color: var(--text-muted); padding: 1rem 0; }
</style>

<script>
    document.querySelectorAll('.quiz-tab').forEach(tab => {
        tab.addEventListener('click', () => {
            document.querySelectorAll('.quiz-tab').forEach(t => t.classList.remove('active'));
            document.querySelectorAll('.quiz-tab-panel').forEach(p => p.style.display = 'none');
            tab.classList.add('active');
            document.querySelector(`.quiz-tab-panel[data-panel="${tab.dataset.tab}"]`).style.display = 'block';
        });
    });
</script>
@endsection