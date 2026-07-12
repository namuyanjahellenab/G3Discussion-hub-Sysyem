<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lecturer Dashboard - Discussion Hub</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="{{ asset('css/admin-theme.css') }}">
    <style>
        :root {
            --primary: var(--luna-mid);
            --primary-dark: var(--luna-dark);
            --secondary: var(--luna-light);
            --light: var(--luna-lightest);
            --navy: var(--text-heading);
            --gray-50: var(--surface-bg);
            --gray-100: #EAF1F4;
            --gray-200: var(--surface-border);
            --gray-300: #C8D6DE;
            --gray-400: var(--text-muted);
            --gray-500: var(--text-body);
            --gray-700: var(--text-heading);
            --danger: var(--accent-danger);
            --danger-light: var(--accent-danger-bg);
            --success: var(--accent-success);
            --success-light: var(--accent-success-bg);
            --pink-light: #F4EBF7;
            --pink: #8E6AAE;
            --amber-light: var(--accent-amber-bg);
            --amber: var(--accent-amber);
            --sidebar-width: 0px;
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

        /* ---------- Layout shell ---------- */
        .app-shell { display: flex; min-height: 100vh; }

        /* ---------- Main content ---------- */
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
        .topbar-left, .topbar-right {
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

        /* ---------- Page content ---------- */
        .content { padding: 28px; max-width: 1280px; }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 24px;
            flex-wrap: wrap;
            gap: 12px;
        }
        .page-header h1 { font-size: 24px; font-weight: 800; letter-spacing: -0.3px; font-family: var(--font-display); }
        .page-header p { color: var(--gray-500); font-size: 13.5px; margin-top: 4px; font-family: var(--font-body); }

        .date-badge {
            display: flex; align-items: center; gap: 8px;
            background: #fff;
            border: 1px solid var(--gray-200);
            border-radius: 10px;
            padding: 10px 16px;
            font-size: 13px;
            font-weight: 600;
            color: var(--navy);
        }
        .date-badge i { color: var(--primary); }

        /* ---------- Stat cards ---------- */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;
            margin-bottom: 20px;
        }
        .stat-card {
            padding: 18px 20px;
            border-top: 3px solid var(--luna-mid);
        }
        .stat-card-top {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 10px;
        }
        .stat-card .label { font-size: 12.5px; color: var(--gray-500); font-weight: 600; font-family: var(--font-body); }
        .stat-icon {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 13px;
        }
        .stat-icon.blue { background: var(--light); color: var(--primary); }
        .stat-icon.pink { background: var(--pink-light); color: var(--pink); }
        .stat-icon.amber { background: var(--amber-light); color: var(--amber); }
        .stat-icon.red { background: var(--danger-light); color: var(--danger); }

        .stat-card .value { font-size: 26px; font-weight: 800; color: var(--navy); margin-bottom: 4px; font-family: var(--font-display); }
        .stat-card .trend { font-size: 12px; font-weight: 600; display: flex; align-items: center; gap: 4px; }
        .trend.up { color: var(--success); }
        .trend.warn { color: #D97706; }
        .trend.down { color: var(--danger); }

        /* ---------- Quick actions ---------- */
        .actions-grid {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 16px;
            margin-bottom: 24px;
        }
        .action-card {
            border-radius: var(--radius-lg);
            padding: 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            text-decoration: none;
            transition: transform 0.15s ease, box-shadow 0.15s ease;
            min-height: 88px;
        }
        .action-card:hover { transform: translateY(-1px); }
        .action-card.primary {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: #fff;
        }
        .action-card.light {
            background: #fff;
            border: 1px solid var(--gray-200);
            color: var(--navy);
        }
        .action-card .left { display: flex; align-items: center; gap: 14px; }
        .action-card .icon-box {
            width: 42px; height: 42px; border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            font-size: 16px;
        }
        .action-card.primary .icon-box { background: rgba(255,255,255,0.18); color: #fff; }
        .action-card.light .icon-box { background: var(--light); color: var(--primary); }
        .action-card .title { font-size: 14px; font-weight: 700; font-family: var(--font-display); }
        .action-card .subtitle { font-size: 12px; opacity: 0.85; margin-top: 2px; font-weight: 500; font-family: var(--font-body); }
        .action-card .chevron { font-size: 14px; opacity: 0.8; }

        .action-card.btn {
            display: flex;
            padding: 20px;
            border-radius: var(--radius-lg);
            justify-content: space-between;
            align-items: center;
            width: 100%;
            text-align: left;
        }

        /* ---------- Discussions panel ---------- */
        .panel {
            border-radius: var(--radius-lg);
            overflow: hidden;
        }
        .panel-header {
            padding: 18px 22px;
            border-bottom: 1px solid var(--gray-200);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .panel-header h2 { font-size: 16px; font-weight: 700; font-family: var(--font-display); }
        .panel-header-actions { display: flex; gap: 10px; }

        table.discussion-table { width: 100%; border-collapse: collapse; }
        .discussion-table thead th {
            text-align: left;
            font-size: 11.5px;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            color: var(--gray-400);
            font-weight: 700;
            padding: 12px 22px;
            border-bottom: 1px solid var(--gray-100);
        }
        .discussion-table thead th.actions-col { text-align: right; }
        .discussion-table tbody td {
            padding: 14px 22px;
            border-bottom: 1px solid var(--gray-100);
            font-size: 13.5px;
            vertical-align: middle;
        }
        .discussion-table tbody tr:last-child td { border-bottom: none; }
        .discussion-table .title { font-weight: 700; color: var(--navy); font-family: var(--font-display); }
        .discussion-table .started-by { color: var(--gray-400); font-size: 12px; margin-top: 2px; font-family: var(--font-body); }

        .course-pill {
            display: inline-block;
            background: var(--gray-100);
            color: var(--gray-700);
            font-size: 11.5px;
            font-weight: 700;
            padding: 4px 10px;
            border-radius: 6px;
        }

        .status-pill {
            font-size: 10.5px;
            font-weight: 800;
            padding: 4px 10px;
            border-radius: 999px;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            display: inline-block;
        }
        .status-pinned  { background: var(--light); color: var(--primary); }
        .status-open    { background: var(--success-light); color: var(--success); }
        .status-reported{ background: var(--danger-light); color: var(--danger); }
        .status-closed  { background: var(--gray-100); color: var(--gray-500); }

        .row-actions { display: flex; gap: 14px; justify-content: flex-end; color: var(--gray-400); }
        .row-actions a { color: var(--gray-400); text-decoration: none; font-size: 13px; }
        .row-actions a:hover { color: var(--primary); }
        .row-actions a.danger:hover { color: var(--danger); }

        .empty-state { padding: 40px 20px; text-align: center; color: var(--gray-400); font-size: 13.5px; }

        /* ---------- Responsive ---------- */
        @media (max-width: 1024px) {
            .topbar { padding: 14px 20px; flex-wrap: wrap; justify-content: flex-end; }
            .topbar-search { order: 3; flex-basis: 100%; max-width: none; }
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
            .actions-grid { grid-template-columns: 1fr; }
        }
        @media (max-width: 560px) {
            .stats-grid { grid-template-columns: 1fr; }
            .content { padding: 18px; }
            .discussion-table thead { display: none; }
            .discussion-table, .discussion-table tbody, .discussion-table tr, .discussion-table td { display: block; width: 100%; }
            .topbar-right { margin-left: auto; }
            .user-chip .role { display: none; }
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
                        <input type="search" placeholder="Search discussions, students, reports">
                    </div>
                </div>

                <div class="topbar-right">
                    <button class="icon-btn" aria-label="Messages">
                        <i class="fa-regular fa-envelope"></i>
                    </button>
                    <button class="icon-btn" aria-label="Notifications">
                        <i class="fa-regular fa-bell"></i>
                        <span class="dot"></span>
                    </button>
                    <div class="user-chip" aria-label="Account menu">
                        <div class="avatar">{{ strtoupper(substr(auth()->user()->UserName ?? 'L', 0, 1)) }}</div>
                        <div class="meta">
                            <div class="name">Dr. {{ auth()->user()->UserName ?? 'Lecturer' }}</div>
                            <div class="role">Lecturer</div>
                        </div>
                        <i class="fa-solid fa-chevron-down chevron"></i>
                    </div>
                </div>
            </header>

            <main class="content">

                <div class="page-header">
                    <div>
                        {{-- $activeCoursesCount: number of courses currently taught by the lecturer --}}
                        <h1>Welcome back, Dr. {{ auth()->user()->UserName ?? 'Lecturer' }}</h1>
                        <p>Here's what's happening across your {{ $activeCoursesCount ?? 0 }} active courses today.</p>
                    </div>
                    <div class="date-badge">
                        <i class="fa-regular fa-calendar"></i>
                        {{ now()->format('l, M j, Y') }}
                    </div>
                </div>

                {{--
                    Controller should pass:
                    $totalStudents            - int
                    $activeDiscussions        - int
                    $newDiscussionsToday      - int
                    $unansweredQuestions      - int
                    $reportedPosts            - int
                    $reportedPostsChange      - int (can be negative, e.g. -3)
                --}}
                <div class="stats-grid">
                    <div class="card stat-card">
                        <div class="stat-card-top">
                            <div class="label">Total Students</div>
                            <div class="stat-icon blue"><i class="fa-solid fa-users"></i></div>
                        </div>
                        <div class="value">{{ number_format($totalStudents ?? 0) }}</div>
                    </div>

                    <div class="card stat-card">
                        <div class="stat-card-top">
                            <div class="label">Active Discussions</div>
                            <div class="stat-icon pink"><i class="fa-solid fa-comments"></i></div>
                        </div>
                        <div class="value">{{ number_format($activeDiscussions ?? 0) }}</div>
                    </div>

                    <div class="card stat-card">
                        <div class="stat-card-top">
                            <div class="label">Unanswered Questions</div>
                            <div class="stat-icon amber"><i class="fa-solid fa-circle-question"></i></div>
                        </div>
                        <div class="value">{{ number_format($unansweredQuestions ?? 0) }}</div>
                    </div>

                    <div class="card stat-card">
                        <div class="stat-card-top">
                            <div class="label">Reported Posts</div>
                            <div class="stat-icon red"><i class="fa-solid fa-flag"></i></div>
                        </div>
                        <div class="value">{{ number_format($reportedPosts ?? 0) }}</div>
                    </div>
                </div>

                <div class="actions-grid">
                    <a href="{{ url('/forum/create') }}" class="action-card primary btn btn-primary">
                        <div class="left">
                            <div class="icon-box"><i class="fa-solid fa-plus"></i></div>
                            <div>
                                <div class="title">Create New Discussion</div>
                            </div>
                        </div>
                        <i class="fa-solid fa-chevron-right chevron"></i>
                    </a>

                    <a href="{{ url('/announcements/create') }}" class="action-card primary btn btn-primary">
                        <div class="left">
                            <div class="icon-box"><i class="fa-solid fa-bullhorn"></i></div>
                            <div>
                                <div class="title">Create Announcement</div>
                            </div>
                        </div>
                        <i class="fa-solid fa-chevron-right chevron"></i>
                    </a>

                    <a href="{{ url('/reports') }}" class="action-card light btn btn-outline-secondary">
                        <div class="left">
                            <div class="icon-box"><i class="fa-solid fa-chart-simple"></i></div>
                            <div>
                                <div class="title">View Academic Reports</div>
                                <div class="subtitle">Analyze participation metrics</div>
                            </div>
                        </div>
                        <i class="fa-solid fa-chevron-right chevron" style="color: var(--gray-400);"></i>
                    </a>
                </div>

                {{-- $recentDiscussions: collection of discussion threads, each expected to expose
                     Title, StarterName, Course (code), Replies (count), Status (pinned|open|reported|closed) --}}
                <section class="panel">
                    <div class="panel-header">
                        <h2>Recent Discussions</h2>
                        <div class="panel-header-actions">
                            <button class="btn btn-outline-secondary btn-sm"><i class="fa-solid fa-filter"></i> Filter</button>
                            <a href="{{ url('/forum') }}" class="btn btn-primary btn-sm">See All</a>
                        </div>
                    </div>

                    <table class="discussion-table">
                        <thead>
                            <tr>
                                <th>Title</th>
                                <th>Course</th>
                                <th>Replies</th>
                                <th>Status</th>
                                <th class="actions-col">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($recentDiscussions ?? [] as $discussion)
                                <tr>
                                    <td>
                                        <div class="title">{{ $discussion->Title }}</div>
                                        <div class="started-by">Started by {{ $discussion->StarterName }}</div>
                                    </td>
                                    <td><span class="course-pill">{{ $discussion->Course }}</span></td>
                                    <td>{{ $discussion->Replies }}</td>
                                    <td>
                                        <span class="status-pill status-{{ strtolower($discussion->Status) }}">
                                            {{ $discussion->Status }}
                                        </span>
                                    </td>
                                    <td>
                                        <div class="row-actions">
                                            <a href="{{ route('forum.show', $discussion->id) }}" title="View">
                                                <i class="fa-regular fa-eye"></i>
                                            </a>
                                            <a href="{{ route('forum.lock', $discussion->id) }}" title="Lock">
                                                <i class="fa-solid fa-lock"></i>
                                            </a>
                                            <a href="{{ route('forum.delete', $discussion->id) }}" title="Delete" class="danger"
                                               onclick="return confirm('Delete this discussion?');">
                                                <i class="fa-regular fa-trash-can"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5">
                                        <div class="empty-state">No discussions yet.</div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </section>

            </main>
        </div>
    </div>

</body>
</html>