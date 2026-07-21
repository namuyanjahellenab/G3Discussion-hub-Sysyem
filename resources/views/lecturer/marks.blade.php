@extends('layouts.app')

@section('content')
<style>
    .content { padding: 28px; max-width: 1280px; }

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

    .result-row .user { font-weight: 600; }
    .result-row .score { color: var(--luna-mid); font-weight: 700; }

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
        <a href="{{ route('quiz.schedule') }}" class="btn btn-primary">+ Schedule Quiz</a>
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
                                &middot; {{ $quiz->TargetCategory }}
                            </div>
                        </div>
                        <div style="display:flex; align-items:center; gap:14px;">
                            <span class="status-pill status-{{ $status }}">{{ $status }}</span>
                            <a href="{{ route('quiz.results', $quiz->QuizID) }}" class="quiz-edit-link">Results</a>
                            <a href="{{ url('/quizzes/'.$quiz->QuizID.'/edit') }}" class="quiz-edit-link">Edit</a>
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
                    <div class="result-row">
                        <div>
                            <div class="user">Student #{{ $result->UserID }}</div>
                            <div class="meta" style="color:var(--text-muted); font-size:12px; margin-top:2px; font-family:var(--font-body);">
                                {{ \Carbon\Carbon::parse($result->SubmissionTime)->diffForHumans() }}
                            </div>
                        </div>
                        <div class="score">{{ $result->Score }} pts</div>
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
