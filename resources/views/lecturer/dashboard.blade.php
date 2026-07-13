<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lecturer Dashboard | Discussion Hub</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=sora:400,500,600,700|inter:400,500,600,700&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.3/css/all.min.css" />
    <link rel="stylesheet" href="{{ asset('css/admin-theme.css') }}">
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; font-family: var(--font-body); background: var(--surface-bg); color: var(--text-body); }
        h1, h2, h3 { font-family: var(--font-display); color: var(--text-heading); }

        .app-shell { display: flex; min-height: 100vh; }
        .main { flex: 1; min-width: 0; }

        .topbar {
            background: var(--surface-card); border-bottom: 1px solid var(--surface-border);
            padding: 14px 28px; display: flex; align-items: center; justify-content: space-between;
            position: sticky; top: 0; z-index: 30; box-shadow: var(--shadow-soft);
        }
        .topbar-search { flex: 1; max-width: 420px; position: relative; }
        .topbar-search i { position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: var(--text-muted); font-size: 13px; }
        .topbar-search input {
            width: 100%; border: 1px solid var(--surface-border); background: var(--surface-bg);
            border-radius: 999px; padding: 10px 16px 10px 38px; font-size: 13px; outline: none;
        }
        .topbar-right { display: flex; align-items: center; gap: 12px; }
        .icon-btn {
            width: 38px; height: 38px; border-radius: 10px; background: var(--surface-bg);
            display: flex; align-items: center; justify-content: center; cursor: pointer; position: relative;
        }
        .icon-btn .dot { position: absolute; top: 8px; right: 8px; width: 7px; height: 7px; border-radius: 50%; background: var(--accent-danger); }
        .user-chip { display: flex; align-items: center; gap: 10px; padding: 6px 12px 6px 6px; border-radius: 999px; background: var(--surface-bg); border: 1px solid var(--surface-border); }
        .user-chip .avatar { width: 32px; height: 32px; border-radius: 50%; background: var(--luna-mid); color: #fff; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 12px; }
        .user-chip .name { font-size: 13px; font-weight: 600; color: var(--text-heading); }
        .user-chip .role { font-size: 11px; color: var(--text-muted); }

        .content { padding: 28px; max-width: 1200px; }
        .page-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 24px; flex-wrap: wrap; gap: 12px; }
        .page-header h1 { font-size: 24px; font-weight: 800; margin: 0; }
        .page-header p { color: var(--text-muted); font-size: 13.5px; margin-top: 4px; }

        .btn { display: inline-flex; align-items: center; gap: 8px; padding: 0.55rem 1rem; border-radius: var(--radius-md); font-size: 13px; font-weight: 600; text-decoration: none; border: 1px solid transparent; box-shadow: var(--shadow-soft); }
        .btn-primary { background: var(--luna-mid); color: #fff; }
        .btn-primary:hover { background: var(--luna-dark); }

        .stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 28px; }
        .stat-card { background: var(--surface-card); border: 1px solid var(--surface-border); border-top: 3px solid var(--luna-mid); border-radius: var(--radius-lg); padding: 18px 20px; box-shadow: var(--shadow-soft); }
        .stat-card .label { font-size: 12px; color: var(--text-muted); font-weight: 600; text-transform: uppercase; letter-spacing: 0.4px; }
        .stat-card .value { font-size: 28px; font-weight: 800; color: var(--text-heading); margin-top: 6px; }

        .panels-grid { display: grid; grid-template-columns: 1.4fr 1fr; gap: 20px; }
        .panel { background: var(--surface-card); border: 1px solid var(--surface-border); border-radius: var(--radius-lg); overflow: hidden; box-shadow: var(--shadow-soft); }
        .panel-header { padding: 16px 20px; border-bottom: 1px solid var(--surface-border); display: flex; justify-content: space-between; align-items: center; }
        .panel-header h2 { font-size: 15px; font-weight: 700; margin: 0; }
        .panel-header a { font-size: 12.5px; color: var(--luna-mid); text-decoration: none; font-weight: 600; }

        .quiz-row, .result-row { display: flex; justify-content: space-between; align-items: center; padding: 13px 20px; border-bottom: 1px solid var(--surface-border); font-size: 13.5px; }
        .quiz-row:last-child, .result-row:last-child { border-bottom: none; }
        .quiz-row .title { font-weight: 600; color: var(--text-heading); }
        .quiz-row .meta { color: var(--text-muted); font-size: 12px; margin-top: 2px; }

        .status-pill { font-size: 11px; font-weight: 700; padding: 4px 10px; border-radius: 999px; text-transform: uppercase; }
        .status-upcoming { background: var(--luna-lightest); color: var(--luna-dark); }
        .status-active { background: var(--accent-success-bg); color: var(--accent-success); }
        .status-closed { background: var(--surface-bg); color: var(--text-muted); }

        .result-row .score { color: var(--luna-mid); font-weight: 700; }
        .empty-state { padding: 40px 20px; text-align: center; color: var(--text-muted); font-size: 13.5px; }
        .quiz-edit-link { font-size: 12px; color: var(--luna-mid); text-decoration: none; font-weight: 600; margin-left: 12px; }

        @media (max-width: 1024px) {
            .panels-grid { grid-template-columns: 1fr; }
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
        }
    </style>
</head>
<body>
    <div class="app-shell">
        @include('layouts.sidebar')

        <div class="main">
            <header class="topbar">
                <div class="topbar-search">
                    <i class="fa-solid fa-magnifying-glass"></i>
                    <input type="search" placeholder="Search quizzes, students...">
                </div>
                <div class="topbar-right">
                    <button class="icon-btn" type="button" title="Notifications">
                        <i class="fa-regular fa-bell"></i>
                        <span class="dot"></span>
                    </button>
                    <div class="user-chip">
                        <div class="avatar">{{ strtoupper(substr(auth()->user()->UserName ?? 'L', 0, 1)) }}</div>
                        <div>
                            <div class="name">{{ auth()->user()->UserName ?? 'Lecturer' }}</div>
                            <div class="role">Lecturer</div>
                        </div>
                    </div>
                </div>
            </header>

            <main class="content">
                <div class="page-header">
                    <div>
                        <h1>Lecturer Dashboard</h1>
                        <p>Overview of your quizzes and recent student activity.</p>
                    </div>
                    <a href="{{ route('quiz.schedule') }}" class="btn btn-primary">+ Schedule Quiz</a>
                </div>

                <div class="stats-grid">
                    <div class="stat-card"><div class="label">Total Quizzes</div><div class="value">{{ $quizzes->count() }}</div></div>
                    <div class="stat-card"><div class="label">Upcoming</div><div class="value">{{ $upcoming }}</div></div>
                    <div class="stat-card"><div class="label">Active Now</div><div class="value">{{ $active }}</div></div>
                    <div class="stat-card"><div class="label">Closed</div><div class="value">{{ $closed }}</div></div>
                </div>

                <div class="panels-grid">
                    <section class="panel">
                        <div class="panel-header">
                            <h2>Your Quizzes</h2>
                            <a href="{{ url('/quizzes') }}">View all</a>
                        </div>
                        <div>
                            @forelse ($quizzes as $quiz)
                                @php
                                    $now = now();
                                    $end = $quiz->StartTime->copy()->addMinutes($quiz->Duration);
                                    if ($quiz->StartTime > $now) { $status = 'upcoming'; }
                                    elseif ($now <= $end) { $status = 'active'; }
                                    else { $status = 'closed'; }
                                @endphp
                                <div class="quiz-row">
                                    <div>
                                        <div class="title">{{ $quiz->Title }}</div>
                                        <div class="meta">{{ $quiz->StartTime->format('d M Y, h:i A') }} · {{ $quiz->Duration }} min · {{ $quiz->TargetCategory }}</div>
                                    </div>
                                    <div style="display:flex; align-items:center;">
                                        <span class="status-pill status-{{ $status }}">{{ $status }}</span>
                                        <a href="{{ route('quiz.results', $quiz->QuizID) }}" class="quiz-edit-link">Results</a>
                                    </div>
                                </div>
                            @empty
                                <div class="empty-state">You haven't scheduled any quizzes yet.</div>
                            @endforelse
                        </div>
                    </section>

                    <section class="panel">
                        <div class="panel-header"><h2>Recent Submissions</h2></div>
                        <div>
                            @forelse ($recentResults as $result)
                                <div class="result-row">
                                    <div>
                                        <div>Student #{{ $result->UserID }}</div>
                                        <div class="meta">{{ \Carbon\Carbon::parse($result->SubmissionTime)->diffForHumans() }}</div>
                                    </div>
                                    <div class="score">{{ $result->Score }} pts</div>
                                </div>
                            @empty
                                <div class="empty-state">No submissions yet.</div>
                            @endforelse
                        </div>
                    </section>
                </div>
            </main>
        </div>
    </div>
</body>
</html>