<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Marks Statistics - Discussion Hub</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=sora:400,500,600,700|inter:400,500,600,700&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.3/css/all.min.css" />
    <link rel="stylesheet" href="{{ asset('css/admin-theme.css') }}">
    <style>
        :root {
            --navy: var(--text-heading);
            --gray-50: var(--surface-bg);
            --gray-100: #EAF1F4;
            --gray-200: var(--surface-border);
            --gray-400: var(--text-muted);
            --gray-500: var(--text-body);
            --gray-700: var(--text-heading);
            --sidebar-width: 0px;
            --primary: var(--luna-mid);
            --primary-dark: var(--luna-dark);
            --secondary: var(--luna-light);
            --light: var(--luna-lightest);
            --danger: var(--accent-danger);
            --danger-light: var(--accent-danger-bg);
            --success: var(--accent-success);
            --success-light: var(--accent-success-bg);
            --amber-light: var(--accent-amber-bg);
            --amber: var(--accent-amber);
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: var(--font-body);
            background: var(--surface-bg);
            color: var(--navy);
        }

        h1, h2, h3, h4, h5, h6 {
            font-family: var(--font-display);
            letter-spacing: -0.02em;
        }

        .app-shell { display: flex; min-height: 100vh; }
        .main { flex: 1; margin-left: 0; transition: margin-left 0.25s ease; width: 100%; }
        .main.expanded { margin-left: 0; }

        .topbar {
            background: #fff;
            border-bottom: 1px solid var(--gray-200);
            min-height: 72px;
            padding: 14px 28px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 18px;
            position: sticky;
            top: 0;
            z-index: 30;
        }
        .topbar-left,
        .topbar-right {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .topbar-search {
            flex: 1;
            max-width: 420px;
            position: relative;
        }
        .topbar-search i {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--gray-400);
            font-size: 13px;
        }
        .topbar-search input {
            width: 100%;
            border: 1px solid var(--gray-200);
            background: var(--gray-50);
            border-radius: 999px;
            padding: 10px 16px 10px 38px;
            font-family: var(--font-body);
            font-size: 13px;
            color: var(--navy);
            outline: none;
        }
        .topbar-search input:focus {
            border-color: var(--luna-light);
            background: #fff;
            box-shadow: 0 0 0 0.2rem rgba(84, 172, 191, 0.18);
        }
        .icon-btn {
            width: 38px;
            height: 38px;
            border-radius: 10px;
            background: var(--gray-100);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--gray-700);
            font-size: 14px;
            cursor: pointer;
            position: relative;
            border: 1px solid transparent;
        }
        .icon-btn:hover { background: #EAF1F4; }
        .icon-btn .dot {
            position: absolute;
            top: 8px;
            right: 8px;
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background: var(--danger);
        }
        .user-chip {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 6px 12px 6px 6px;
            border-radius: 999px;
            background: var(--gray-50);
            border: 1px solid var(--gray-200);
            cursor: pointer;
        }
        .user-chip .avatar {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: var(--luna-mid);
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 12px;
        }
        .user-chip .meta { line-height: 1.2; }
        .user-chip .name { font-size: 13px; font-weight: 600; color: var(--navy); }
        .user-chip .role { font-size: 11px; color: var(--gray-400); }
        .user-chip .chevron { font-size: 10px; color: var(--gray-400); }

        .content {
            padding: 28px;
            max-width: 1280px;
        }

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
            color: var(--gray-500);
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
            background: #fff;
            border: 1px solid var(--gray-200);
            border-radius: 12px;
            padding: 18px 20px;
            display: flex;
            flex-direction: column;
            gap: 6px;
        }
        .stat-card .label {
            font-size: 12.5px;
            color: var(--gray-500);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            font-family: var(--font-body);
        }
        .stat-card .value {
            font-size: 28px;
            font-weight: 800;
            color: var(--navy);
            font-family: var(--font-display);
        }
        .stat-card.accent .value { color: var(--primary); }

        .panels-grid {
            display: grid;
            grid-template-columns: 1.4fr 1fr;
            gap: 20px;
        }

        .panel {
            background: #fff;
            border: 1px solid var(--gray-200);
            border-radius: 12px;
            overflow: hidden;
        }

        .panel-header {
            padding: 16px 20px;
            border-bottom: 1px solid var(--gray-200);
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
            color: var(--primary);
            text-decoration: none;
            font-weight: 600;
        }

        .panel-body { padding: 8px 0; }

        .quiz-row, .result-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 13px 20px;
            border-bottom: 1px solid var(--gray-100);
            font-size: 13.5px;
        }

        .quiz-row:last-child, .result-row:last-child { border-bottom: none; }

        .quiz-row .title {
            font-weight: 600;
            color: var(--navy);
        }

        .quiz-row .meta {
            color: var(--gray-500);
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

        .status-upcoming { background: var(--light); color: var(--primary); }
        .status-active   { background: #DCFCE7; color: #16A34A; }
        .status-closed   { background: var(--gray-100); color: var(--gray-500); }

        .result-row .user { font-weight: 600; }
        .result-row .score { color: var(--primary); font-weight: 700; }

        .empty-state {
            padding: 40px 20px;
            text-align: center;
            color: var(--gray-400);
            font-size: 13.5px;
            font-family: var(--font-body);
        }

        .quiz-edit-link {
            font-size: 12px;
            color: var(--primary);
            text-decoration: none;
            font-weight: 600;
        }

        @media (max-width: 1024px) {
            .main { margin-left: 0; }
            .panels-grid { grid-template-columns: 1fr; }
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
            .topbar-search { display: none; }
        }

        @media (max-width: 560px) {
            .stats-grid { grid-template-columns: 1fr; }
            .content { padding: 18px; }
        }
    </style>
</head>
<body>
    <div class="app-shell">
        @include('layouts.sidebar')

        <div class="main" id="mainContent">
            <header class="topbar">
                <div class="topbar-left">
                    <div class="topbar-search">
                        <i class="fa-solid fa-magnifying-glass"></i>
                        <input type="text" placeholder="Search quizzes, students...">
                    </div>
                </div>
                <div class="topbar-right">
                    <button class="icon-btn" type="button" aria-label="Notifications">
                        <i class="fa-regular fa-bell"></i>
                        <span class="dot"></span>
                    </button>
                    <div class="user-chip" aria-label="Account menu">
                        <div class="avatar">{{ strtoupper(substr(auth()->user()->UserName ?? 'L', 0, 1)) }}</div>
                        <div class="meta">
                            <div class="name">{{ auth()->user()->UserName ?? 'Lecturer' }}</div>
                            <div class="role">Lecturer</div>
                        </div>
                        <i class="fa-solid fa-chevron-down chevron"></i>
                    </div>
                </div>
            </header>

            <main class="content">
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
                                        <div class="meta" style="color:#94A3B8; font-size:12px; margin-top:2px; font-family:var(--font-body);">
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
            </main>
        </div>
    </div>

</body>
</html>
