<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lecturer Dashboard - Discussion Hub</title>
    <style>
        :root {
            --primary: #2563EB;
            --secondary: #60A5FA;
            --light: #DBEAFE;
            --navy: #0F172A;
            --gray-50: #F8FAFC;
            --gray-100: #F1F5F9;
            --gray-200: #E2E8F0;
            --gray-400: #94A3B8;
            --gray-500: #64748B;
            --gray-700: #334155;
            --sidebar-width: 240px;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: var(--gray-50);
            color: var(--navy);
        }

        /* ---------- Layout shell ---------- */
        .app-shell {
            display: flex;
            min-height: 100vh;
        }

        /* ---------- Sidebar ---------- */
        .sidebar {
            width: var(--sidebar-width);
            background: var(--navy);
            color: #fff;
            display: flex;
            flex-direction: column;
            position: fixed;
            top: 0;
            left: 0;
            bottom: 0;
            z-index: 40;
            transition: transform 0.25s ease;
        }

        .sidebar.collapsed {
            transform: translateX(-100%);
        }

        .sidebar-brand {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 22px 20px;
            font-weight: 700;
            font-size: 17px;
            letter-spacing: 0.3px;
            border-bottom: 1px solid rgba(255,255,255,0.08);
        }

        .sidebar-brand .dot {
            width: 10px;
            height: 10px;
            border-radius: 3px;
            background: var(--secondary);
        }

        .sidebar-nav {
            flex: 1;
            padding: 16px 12px;
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .sidebar-nav a {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 11px 14px;
            border-radius: 8px;
            color: #CBD5E1;
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            transition: background 0.15s ease, color 0.15s ease;
        }

        .sidebar-nav a:hover {
            background: rgba(255,255,255,0.06);
            color: #fff;
        }

        .sidebar-nav a.active {
            background: var(--primary);
            color: #fff;
        }

        .sidebar-nav .icon {
            width: 18px;
            height: 18px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 15px;
        }

        .sidebar-footer {
            padding: 16px 20px;
            border-top: 1px solid rgba(255,255,255,0.08);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .sidebar-footer .avatar {
            width: 34px;
            height: 34px;
            border-radius: 50%;
            background: var(--primary);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 13px;
        }

        .sidebar-footer .meta { line-height: 1.3; }
        .sidebar-footer .meta .name { font-size: 13px; font-weight: 600; }
        .sidebar-footer .meta .role { font-size: 11px; color: var(--gray-400); }

        /* ---------- Main content ---------- */
        .main {
            flex: 1;
            margin-left: var(--sidebar-width);
            transition: margin-left 0.25s ease;
            width: 100%;
        }

        .main.expanded {
            margin-left: 0;
        }

        .topbar {
            background: #fff;
            border-bottom: 1px solid var(--gray-200);
            padding: 14px 28px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: sticky;
            top: 0;
            z-index: 30;
        }

        .topbar-left {
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .sidebar-toggle {
            background: var(--gray-100);
            border: none;
            width: 38px;
            height: 38px;
            border-radius: 8px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
            color: var(--gray-700);
            transition: background 0.15s ease;
        }

        .sidebar-toggle:hover { background: var(--gray-200); }

        .topbar-search input {
            border: 1px solid var(--gray-200);
            background: var(--gray-50);
            border-radius: 8px;
            padding: 9px 14px;
            font-size: 13px;
            width: 280px;
            outline: none;
        }

        .topbar-search input:focus {
            border-color: var(--primary);
            background: #fff;
        }

        .topbar-right {
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .badge-pill {
            background: var(--light);
            color: var(--primary);
            font-size: 12px;
            font-weight: 700;
            padding: 6px 14px;
            border-radius: 999px;
            letter-spacing: 0.3px;
        }

        .bell {
            width: 38px;
            height: 38px;
            border-radius: 8px;
            background: var(--gray-100);
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            cursor: pointer;
        }

        .bell .dot {
            position: absolute;
            top: 8px;
            right: 8px;
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background: #EF4444;
        }

        /* ---------- Page content ---------- */
        .content {
            padding: 28px;
            max-width: 1200px;
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
            font-size: 22px;
            font-weight: 800;
            letter-spacing: -0.3px;
        }

        .page-header p {
            color: var(--gray-500);
            font-size: 13.5px;
            margin-top: 4px;
        }

        .btn-primary {
            background: var(--primary);
            color: #fff;
            border: none;
            padding: 11px 20px;
            border-radius: 8px;
            font-size: 13.5px;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
            transition: background 0.15s ease;
        }

        .btn-primary:hover { background: #1D4ED8; }

        /* ---------- Stat cards ---------- */
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
        }

        .stat-card .value {
            font-size: 28px;
            font-weight: 800;
            color: var(--navy);
        }

        .stat-card.accent .value { color: var(--primary); }

        /* ---------- Panels ---------- */
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
        }

        .panel-header a {
            font-size: 12.5px;
            color: var(--primary);
            text-decoration: none;
            font-weight: 600;
        }

        .panel-body {
            padding: 8px 0;
        }

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
        }

        .quiz-edit-link {
            font-size: 12px;
            color: var(--primary);
            text-decoration: none;
            font-weight: 600;
        }

        /* ---------- Overlay for mobile sidebar ---------- */
        .sidebar-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, 0.4);
            z-index: 35;
        }

        .sidebar-overlay.show { display: block; }

        /* ---------- Responsive ---------- */
        @media (max-width: 1024px) {
            .sidebar {
                transform: translateX(-100%);
                box-shadow: 4px 0 24px rgba(0,0,0,0.15);
            }
            .sidebar.open {
                transform: translateX(0);
            }
            .main {
                margin-left: 0;
            }
            .panels-grid {
                grid-template-columns: 1fr;
            }
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            .topbar-search {
                display: none;
            }
        }

        @media (max-width: 560px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
            .content {
                padding: 18px;
            }
        }
    </style>
</head>
<body>

    <div class="app-shell">

        <div class="sidebar-overlay" id="sidebarOverlay"></div>

        <aside class="sidebar" id="sidebar">
            <div class="sidebar-brand">
                <span class="dot"></span>
                DISCUSSION HUB
            </div>

            <nav class="sidebar-nav">
                <a href="{{ route('dashboard') }}" class="active">
                    <span class="icon">&#9635;</span> Dashboard
                </a>
                <a href="{{ url('/forum') }}">
                    <span class="icon">&#128172;</span> Forum
                </a>
                <a href="{{ url('/marks') }}">
                    <span class="icon">&#9733;</span> Marks
                </a>
                <a href="{{ url('/quizzes') }}">
                    <span class="icon">&#128196;</span> Quizzes
                </a>
                <a href="{{ url('/settings') }}">
                    <span class="icon">&#9881;</span> Settings
                </a>
            </nav>

            <div class="sidebar-footer">
                <div class="avatar">{{ strtoupper(substr(auth()->user()->UserName ?? 'L', 0, 1)) }}</div>
                <div class="meta">
                    <div class="name">{{ auth()->user()->UserName ?? 'Lecturer' }}</div>
                    <div class="role">Lecturer</div>
                </div>
            </div>
        </aside>

        <div class="main" id="mainContent">

            <header class="topbar">
                <div class="topbar-left">
                    <button class="sidebar-toggle" id="sidebarToggle" aria-label="Toggle sidebar">&#9776;</button>
                    <div class="topbar-search">
                        <input type="text" placeholder="Search quizzes, students...">
                    </div>
                </div>
                <div class="topbar-right">
                    <span class="badge-pill">LECTURER MODE</span>
                    <div class="bell">
                        &#128276;
                        <span class="dot"></span>
                    </div>
                </div>
            </header>

            <main class="content">

                <div class="page-header">
                    <div>
                        <h1>Lecturer Dashboard</h1>
                        <p>Overview of your quizzes and recent student activity.</p>
                    </div>
                    <a href="{{ url('/quizzes/create') }}" class="btn-primary">+ Schedule Quiz</a>
                </div>

                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="label">Total Quizzes</div>
                        <div class="value">{{ $quizzes->count() }}</div>
                    </div>
                    <div class="stat-card accent">
                        <div class="label">Upcoming</div>
                        <div class="value">{{ $upcoming }}</div>
                    </div>
                    <div class="stat-card">
                        <div class="label">Active Now</div>
                        <div class="value">{{ $active }}</div>
                    </div>
                    <div class="stat-card">
                        <div class="label">Closed</div>
                        <div class="value">{{ $closed }}</div>
                    </div>
                </div>

                <div class="panels-grid">

                    <section class="panel">
                        <div class="panel-header">
                            <h2>Your Quizzes</h2>
                            <a href="{{ url('/quizzes') }}">View all</a>
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

                    <section class="panel">
                        <div class="panel-header">
                            <h2>Recent Submissions</h2>
                        </div>
                        <div class="panel-body">
                            @forelse ($recentResults as $result)
                                <div class="result-row">
                                    <div>
                                        <div class="user">Student #{{ $result->UserID }}</div>
                                        <div class="meta" style="color:#94A3B8; font-size:12px; margin-top:2px;">
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

    <script>
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('sidebarOverlay');
        const toggleBtn = document.getElementById('sidebarToggle');
        const mainContent = document.getElementById('mainContent');

        function isMobile() {
            return window.innerWidth <= 1024;
        }

        function toggleSidebar() {
            if (isMobile()) {
                sidebar.classList.toggle('open');
                overlay.classList.toggle('show');
            } else {
                sidebar.classList.toggle('collapsed');
                mainContent.classList.toggle('expanded');
            }
        }

        toggleBtn.addEventListener('click', toggleSidebar);
        overlay.addEventListener('click', () => {
            sidebar.classList.remove('open');
            overlay.classList.remove('show');
        });

        window.addEventListener('resize', () => {
            if (!isMobile()) {
                sidebar.classList.remove('open');
                overlay.classList.remove('show');
            }
        });
    </script>

</body>
</html>