@extends('layouts.app')

@section('content')
<style>
    .content { padding: 28px; }

    .page-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 24px;
        flex-wrap: wrap;
        gap: 12px;
    }
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

    .btn-primary {
        background: var(--luna-mid);
        color: #fff;
        border: 1px solid var(--luna-mid);
        padding: 11px 20px;
        border-radius: 8px;
        font-size: 13.5px;
        font-weight: 600;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        text-decoration: none;
        transition: background 0.15s ease, border-color 0.15s ease;
    }
    .btn-primary:hover {
        background: var(--luna-dark);
        border-color: var(--luna-dark);
    }

    .btn-outline {
        background: transparent;
        color: var(--luna-mid);
        border: 1px solid var(--luna-mid);
        padding: 11px 20px;
        border-radius: 8px;
        font-size: 13.5px;
        font-weight: 600;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        text-decoration: none;
        transition: background 0.15s ease, color 0.15s ease;
    }
    .btn-outline:hover { background: var(--luna-lightest); }

    .stats-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 16px;
        margin-bottom: 28px;
    }
    .stat-card {
        background: var(--surface-card);
        border: 1px solid var(--surface-border);
        border-radius: 12px;
        padding: 18px 20px;
        display: flex;
        flex-direction: column;
        gap: 6px;
    }
    .stat-card .label {
        font-size: 12.5px;
        color: var(--text-muted);
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.4px;
        font-family: var(--font-body);
    }
    .stat-card .value {
        font-size: 28px;
        font-weight: 800;
        color: var(--text-heading);
        font-family: var(--font-display);
    }
    .stat-card.accent .value { color: var(--luna-mid); }

    .panels-grid {
        display: grid;
        grid-template-columns: 1.4fr 1fr;
        gap: 20px;
        /* Grid items stretch to match the tallest sibling by default - "Your
           Quizzes" was always as tall as "Recent Submissions" regardless of
           its own quiz count, leaving dead space below a short quiz list.
           align-items: start lets each panel size to its own content instead,
           so it shrinks with few quizzes and grows as more are scheduled. */
        align-items: start;
    }

    .panel {
        background: var(--surface-card);
        border: 1px solid var(--surface-border);
        border-radius: 12px;
        overflow: hidden;
    }

    .panel-header {
        padding: 16px 20px;
        border-bottom: 1px solid var(--surface-border);
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .panel-header h2 {
        font-size: 15px;
        font-weight: 700;
        font-family: var(--font-display);
    }

    .panel-header a {
        font-size: 12.5px;
        color: var(--luna-mid);
        text-decoration: none;
        font-weight: 600;
    }

    .panel-body { padding: 8px 0; }

    .quiz-row, .result-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 13px 20px;
        border-bottom: 1px solid var(--surface-bg);
        font-size: 13.5px;
    }

    .quiz-row:last-child, .result-row:last-child { border-bottom: none; }

    .quiz-row .title {
        font-weight: 600;
        color: var(--text-heading);
    }

    .quiz-row .meta {
        color: var(--text-muted);
        font-size: 12px;
        margin-top: 2px;
        font-family: var(--font-body);
    }

    .status-pill {
        font-size: 11px;
        font-weight: 700;
        padding: 4px 10px;
        border-radius: 999px;
        text-transform: uppercase;
        letter-spacing: 0.3px;
    }

    .status-upcoming { background: var(--luna-lightest); color: var(--luna-mid); }
    .status-active   { background: var(--accent-success-bg); color: var(--accent-success); }
    .status-closed   { background: var(--surface-bg); color: var(--text-muted); }

    .result-row .user { font-weight: 600; display: flex; align-items: center; gap: 10px; }
    .result-row .score { color: var(--luna-mid); font-weight: 700; text-align: right; }
    .result-row .quiz-name { color: var(--text-muted); font-size: 12px; font-weight: 500; margin-top: 2px; font-family: var(--font-body); }

    .result-avatar {
        width: 30px; height: 30px; min-width: 30px; border-radius: 50%;
        background: var(--luna-mid); color: #fff; font-size: 12px; font-weight: 700;
        display: flex; align-items: center; justify-content: center;
    }

    .submit-badge {
        font-size: 10px; font-weight: 700; padding: 2px 8px; border-radius: 999px;
        text-transform: uppercase; letter-spacing: 0.3px; margin-top: 4px; display: inline-block;
    }
    .submit-manual { background: var(--accent-success-bg); color: var(--accent-success); }
    .submit-auto   { background: var(--accent-danger-bg); color: var(--accent-danger); }

    .empty-state {
        padding: 40px 20px;
        text-align: center;
        color: var(--text-muted);
        font-size: 13.5px;
        font-family: var(--font-body);
    }

    .quiz-edit-link {
        font-size: 12px;
        color: var(--luna-mid);
        text-decoration: none;
        font-weight: 600;
    }

    .results-card-link {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        background: var(--luna-mid);
        color: #fff;
        text-decoration: none;
        font-size: 12px;
        font-weight: 700;
        padding: 7px 14px;
        border-radius: var(--radius-md);
        box-shadow: var(--shadow-soft);
        transition: background 0.15s;
    }
    .results-card-link:hover { background: var(--luna-dark); }
    .results-card-link svg { width: 13px; height: 13px; }

    @media (max-width: 1024px) {
        .panels-grid { grid-template-columns: 1fr; }
        .stats-grid { grid-template-columns: repeat(2, 1fr); }
    }

    @media (max-width: 560px) {
        .stats-grid { grid-template-columns: 1fr; }
        .content { padding: 18px; }
    }
</style>

<div class="content">
    <div class="page-header">
        <div>
            <h1>Marks Statistics</h1>
            <p>Review quiz status and the latest submission results across your assessments.</p>
        </div>
        <div style="display:flex; gap:10px;">
            <a href="{{ route('participation-criteria.edit') }}" class="btn-outline">Participation Criteria</a>
            <a href="{{ route('quiz.schedule') }}" class="btn btn-primary">+ Schedule Quiz</a>
        </div>
    </div>

    <div class="stats-grid">
        <div class="card stat-card">
            <div class="label">Total Quizzes</div>
            <div class="value">{{ $quizzes->count() }}</div>
        </div>
        <div class="card stat-card accent">
            <div class="label">Upcoming</div>
            <div class="value">{{ $upcoming }}</div>
        </div>
        <div class="card stat-card">
            <div class="label">Active Now</div>
            <div class="value">{{ $active }}</div>
        </div>
        <div class="card stat-card">
            <div class="label">Closed</div>
            <div class="value">{{ $closed }}</div>
        </div>
    </div>

    <div class="panels-grid">
        <section class="card panel">
            <div class="panel-header">
                <h2>Your Quizzes</h2>
                <a href="{{ route('quiz.schedule') }}">Schedule new</a>
            </div>
            <div class="panel-body">
                @forelse ($quizzes as $quiz)
                    @php
                        $now = now();
                        $end = $quiz->StartTime->copy()->addMinutes($quiz->Duration);
                        if ($quiz->StartTime > $now) {
                            $status = 'upcoming';
                        } elseif ($now <= $end) {
                            $status = 'active';
                        } else {
                            $status = 'closed';
                        }
                    @endphp
                    <div class="quiz-row">
                        <div>
                            <div class="title">{{ $quiz->Title }}</div>
                            <div class="meta">
                                {{ $quiz->StartTime->format('d M Y, h:i A') }}
                                &middot; {{ $quiz->Duration }} min
                            </div>
                        </div>
                        <div style="display:flex; align-items:center; gap:14px;">
                            <span class="status-pill status-{{ $status }}">{{ $status }}</span>
                            <a href="{{ route('quiz.results', $quiz->QuizID) }}" class="results-card-link">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 17V9m6 8V5M5 21h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                                Results
                            </a>
                        </div>
                    </div>
                @empty
                    <div class="empty-state">
                        You haven't scheduled any quizzes yet.
                    </div>
                @endforelse
            </div>
        </section>

        <section class="card panel">
            <div class="panel-header">
                <h2>Recent Submissions</h2>
            </div>
            <div class="panel-body">
                @forelse ($recentResults as $result)
                    @php($totalMarks = $totalMarksByQuiz[$result->QuizID] ?? null)
                    <div class="result-row">
                        <div class="user">
                            <div class="result-avatar">{{ Str::substr($result->StudentName ?? '?', 0, 1) }}</div>
                            <div>
                                <div>{{ $result->StudentName ?? 'Unknown student' }}</div>
                                <div class="quiz-name">{{ $result->QuizTitle }}</div>
                            </div>
                        </div>
                        <div>
                            <div class="score">{{ rtrim(rtrim(number_format($result->Score, 2), '0'), '.') }}{{ $totalMarks !== null ? ' / '.rtrim(rtrim(number_format($totalMarks, 2), '0'), '.') : '' }}</div>
                            <div class="meta" style="color:var(--text-muted); font-size:11.5px; margin-top:2px; font-family:var(--font-body); text-align:right;">
                                {{ \Carbon\Carbon::parse($result->SubmissionTime)->diffForHumans() }}
                            </div>
                            <span class="submit-badge {{ $result->IsAutoSubmit ? 'submit-auto' : 'submit-manual' }}">
                                {{ $result->IsAutoSubmit ? 'Auto' : 'Manual' }}
                            </span>
                        </div>
                    </div>
                @empty
                    <div class="empty-state">
                        No submissions yet.
                    </div>
                @endforelse
            </div>
        </section>
    </div>
</div>
@endsection
