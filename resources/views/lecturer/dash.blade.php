<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lecturer Dashboard - Discussion Hub</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        :root {
            --primary: #2563EB;
            --primary-dark: #1D4ED8;
            --secondary: #60A5FA;
            --light: #DBEAFE;
            --navy: #0F172A;
            --gray-50: #F8FAFC;
            --gray-100: #F1F5F9;
            --gray-200: #E2E8F0;
            --gray-300: #CBD5E1;
            --gray-400: #94A3B8;
            --gray-500: #64748B;
            --gray-700: #334155;
            --danger: #EF4444;
            --danger-light: #FEE2E2;
            --success: #16A34A;
            --success-light: #DCFCE7;
            --pink-light: #FCE7F3;
            --pink: #DB2777;
            --sidebar-width: 240px;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: var(--gray-50);
            color: var(--navy);
        }

        /* ---------- Layout shell ---------- */
        .app-shell { display: flex; min-height: 100vh; }

        /* ---------- Sidebar ---------- */
        .sidebar {
            width: var(--sidebar-width);
            background: #ffffff;
            color: var(--gray-500);
            border-right: 1px solid var(--gray-200);
            display: flex;
            flex-direction: column;
            position: fixed;
            top: 0; left: 0; bottom: 0;
            z-index: 40;
            transition: transform 0.25s ease;
        }
        .sidebar.collapsed { transform: translateX(-100%); }

        .sidebar-brand {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 22px 20px;
            font-weight: 700;
            font-size: 16px;
            letter-spacing: 0.2px;
            color: var(--navy);
            border-bottom: 1px solid var(--gray-200);
        }
        .sidebar-brand .logo-mark {
            width: 34px;
            height: 34px;
            border-radius: 9px;
            background: var(--primary);
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 15px;
        }

        .sidebar-nav {
            flex: 1;
            padding: 16px 12px;
            display: flex;
            flex-direction: column;
            gap: 2px;
            overflow-y: auto;
        }
        .sidebar-nav a {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px 14px;
            border-radius: 8px;
            color: var(--gray-500);
            text-decoration: none;
            font-size: 13.5px;
            font-weight: 500;
            transition: background 0.15s ease, color 0.15s ease;
        }
        .sidebar-nav a:hover { background: var(--gray-100); color: var(--navy); }
        .sidebar-nav a.active { background: var(--light); color: var(--primary); font-weight: 600; }
        .sidebar-nav .icon { width: 18px; display: inline-flex; justify-content: center; font-size: 14px; }

        .sidebar-section-gap { height: 14px; }

        .sidebar-logout {
            padding: 16px 20px 20px;
            border-top: 1px solid var(--gray-200);
        }
        .sidebar-logout a {
            display: flex;
            align-items: center;
            gap: 12px;
            color: var(--danger);
            text-decoration: none;
            font-size: 13.5px;
            font-weight: 600;
        }

        /* ---------- Main content ---------- */
        .main { flex: 1; margin-left: var(--sidebar-width); transition: margin-left 0.25s ease; width: 100%; }
        .main.expanded { margin-left: 0; }

        .topbar {
            background: #fff;
            border-bottom: 1px solid var(--gray-200);
            padding: 14px 28px;
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 20px;
            position: sticky;
            top: 0;
            z-index: 30;
        }
        .icon-btn {
            width: 36px; height: 36px;
            border-radius: 8px;
            background: var(--gray-100);
            display: flex; align-items: center; justify-content: center;
            color: var(--gray-700);
            font-size: 14px;
            cursor: pointer;
            position: relative;
            border: none;
        }
        .icon-btn .dot {
            position: absolute; top: 7px; right: 7px;
            width: 7px; height: 7px; border-radius: 50%;
            background: var(--danger);
        }
        .user-chip {
            display: flex; align-items: center; gap: 10px;
            padding: 6px 10px 6px 6px;
            border-radius: 999px;
            background: var(--gray-50);
            border: 1px solid var(--gray-200);
            cursor: pointer;
        }
        .user-chip .avatar {
            width: 30px; height: 30px; border-radius: 50%;
            background: var(--primary);
            color: #fff;
            display: flex; align-items: center; justify-content: center;
            font-weight: 700; font-size: 12px;
        }
        .user-chip .name { font-size: 13px; font-weight: 600; color: var(--navy); }
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
        .page-header h1 { font-size: 24px; font-weight: 800; letter-spacing: -0.3px; }
        .page-header p { color: var(--gray-500); font-size: 13.5px; margin-top: 4px; }

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
            background: #fff;
            border: 1px solid var(--gray-200);
            border-radius: 14px;
            padding: 18px 20px;
        }
        .stat-card-top {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 10px;
        }
        .stat-card .label { font-size: 12.5px; color: var(--gray-500); font-weight: 600; }
        .stat-icon {
            width: 30px; height: 30px; border-radius: 8px;
            display: flex; align-items: center; justify-content: center;
            font-size: 13px;
        }
        .stat-icon.blue { background: var(--light); color: var(--primary); }
        .stat-icon.pink { background: var(--pink-light); color: var(--pink); }
        .stat-icon.amber { background: #FEF3C7; color: #D97706; }
        .stat-icon.red { background: var(--danger-light); color: var(--danger); }

        .stat-card .value { font-size: 26px; font-weight: 800; color: var(--navy); margin-bottom: 4px; }
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
            border-radius: 14px;
            padding: 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            text-decoration: none;
            transition: transform 0.15s ease, box-shadow 0.15s ease;
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
        .action-card .title { font-size: 14px; font-weight: 700; }
        .action-card .subtitle { font-size: 12px; opacity: 0.85; margin-top: 2px; font-weight: 500; }
        .action-card .chevron { font-size: 14px; opacity: 0.8; }

        /* ---------- Discussions panel ---------- */
        .panel {
            background: #fff;
            border: 1px solid var(--gray-200);
            border-radius: 14px;
            overflow: hidden;
        }
        .panel-header {
            padding: 18px 22px;
            border-bottom: 1px solid var(--gray-200);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .panel-header h2 { font-size: 16px; font-weight: 700; }
        .panel-header-actions { display: flex; gap: 10px; }
        .btn-ghost {
            border: 1px solid var(--gray-200);
            background: #fff;
            color: var(--gray-700);
            font-size: 12.5px;
            font-weight: 600;
            padding: 7px 14px;
            border-radius: 8px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .btn-link {
            border: none;
            background: var(--light);
            color: var(--primary);
            font-size: 12.5px;
            font-weight: 700;
            padding: 7px 14px;
            border-radius: 8px;
            cursor: pointer;
            text-decoration: none;
        }

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
        .discussion-table .title { font-weight: 700; color: var(--navy); }
        .discussion-table .started-by { color: var(--gray-400); font-size: 12px; margin-top: 2px; }

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

        /* ---------- Overlay for mobile sidebar ---------- */
        .sidebar-overlay { display: none; position: fixed; inset: 0; background: rgba(15,23,42,0.4); z-index: 35; }
        .sidebar-overlay.show { display: block; }

        /* ---------- Responsive ---------- */
        @media (max-width: 1024px) {
            .sidebar { transform: translateX(-100%); box-shadow: 4px 0 24px rgba(0,0,0,0.15); }
            .sidebar.open { transform: translateX(0); }
            .main { margin-left: 0; }
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
            .actions-grid { grid-template-columns: 1fr; }
        }
        @media (max-width: 560px) {
            .stats-grid { grid-template-columns: 1fr; }
            .content { padding: 18px; }
            .discussion-table thead { display: none; }
            .discussion-table, .discussion-table tbody, .discussion-table tr, .discussion-table td { display: block; width: 100%; }
        }
    </style>
</head>
<body>

    <div class="app-shell">

        <div class="sidebar-overlay" id="sidebarOverlay"></div>

        <aside class="sidebar" id="sidebar">
            <div class="sidebar-brand">
                <span class="logo-mark"><i class="fa-solid fa-comments"></i></span>
                Discussion Hub
            </div>

            <nav class="sidebar-nav">
                <a href="{{ route('dashboard') }}" class="active">
                    <span class="icon"><i class="fa-solid fa-table-columns"></i></span> Dashboard
                </a>
               <a href="{{ url('/forum') }}">
                    <span class="icon"><i class="fa-solid fa-comment-dots"></i></span> Discussion Forums
                </a>
                <a href="{{ route('groups.select') }}">
                    <span class="icon"><i class="fa-solid fa-layer-group"></i></span> Groups
                </a>
                <a href="{{ url('/announcements') }}">
                    <span class="icon"><i class="fa-solid fa-bullhorn"></i></span> Announcements
                </a>
                <a href="{{ url('/marks') }}">
                    <span class="icon"><i class="fa-solid fa-star"></i></span> Marks
                </a>

                <div class="sidebar-section-gap"></div>

                <a href="{{ url('/notifications') }}">
                    <span class="icon"><i class="fa-solid fa-bell"></i></span> Notifications
                </a>
                <a href="{{ url('/profile') }}">
                    <span class="icon"><i class="fa-solid fa-user"></i></span> Profile
                </a>
                <a href="{{ url('/settings') }}">
                    <span class="icon"><i class="fa-solid fa-gear"></i></span> Settings
                </a>
            </nav>

            <div class="sidebar-logout">
                <a href="{{ route('logout') }}"
                   onclick="event.preventDefault(); document.getElementById('logout-form').submit();">
                    <span class="icon"><i class="fa-solid fa-right-from-bracket"></i></span> Logout
                </a>
                <form id="logout-form" action="{{ route('logout') }}" method="POST" style="display:none;">
                    @csrf
                </form>
            </div>
        </aside>

        <div class="main" id="mainContent">

            <header class="topbar">
                <button class="icon-btn" id="sidebarToggle" aria-label="Toggle sidebar">
                    <i class="fa-solid fa-bars"></i>
                </button>
                <button class="icon-btn" aria-label="Messages">
                    <i class="fa-regular fa-envelope"></i>
                </button>
                <button class="icon-btn" aria-label="Notifications">
                    <i class="fa-regular fa-bell"></i>
                    <span class="dot"></span>
                </button>
                <div class="user-chip">
                    <div class="avatar">{{ strtoupper(substr(auth()->user()->UserName ?? 'L', 0, 1)) }}</div>
                    <span class="name">Dr. {{ auth()->user()->UserName ?? 'Lecturer' }}</span>
                    <i class="fa-solid fa-chevron-down chevron"></i>
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
                    <div class="stat-card">
                        <div class="stat-card-top">
                            <div class="label">Total Students</div>
                            <div class="stat-icon blue"><i class="fa-solid fa-users"></i></div>
                        </div>
                        <div class="value">{{ number_format($totalStudents ?? 0) }}</div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-card-top">
                            <div class="label">Active Discussions</div>
                            <div class="stat-icon pink"><i class="fa-solid fa-comments"></i></div>
                        </div>
                        <div class="value">{{ number_format($activeDiscussions ?? 0) }}</div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-card-top">
                            <div class="label">Unanswered Questions</div>
                            <div class="stat-icon amber"><i class="fa-solid fa-circle-question"></i></div>
                        </div>
                        <div class="value">{{ number_format($unansweredQuestions ?? 0) }}</div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-card-top">
                            <div class="label">Reported Posts</div>
                            <div class="stat-icon red"><i class="fa-solid fa-flag"></i></div>
                        </div>
                        <div class="value">{{ number_format($reportedPosts ?? 0) }}</div>
                        @if(($reportedPostsChange ?? 0) < 0)
                            
                        @else
                            
                        @endif
                    </div>
                </div>

                <div class="actions-grid">
                    <a href="{{ url('/forum/create') }}" class="action-card primary">
                        <div class="left">
                            <div class="icon-box"><i class="fa-solid fa-plus"></i></div>
                            <div>
                                <div class="title">Create New Discussion</div>
                            </div>
                        </div>
                        <i class="fa-solid fa-chevron-right chevron"></i>
                    </a>

                    <a href="{{ url('/announcements/create') }}" class="action-card primary">
                        <div class="left">
                            <div class="icon-box"><i class="fa-solid fa-bullhorn"></i></div>
                            <div>
                                <div class="title">Create Announcement</div>
                            </div>
                        </div>
                        <i class="fa-solid fa-chevron-right chevron"></i>
                    </a>

                    <a href="{{ url('/reports') }}" class="action-card light">
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
                            <button class="btn-ghost"><i class="fa-solid fa-filter"></i> Filter</button>
                            <a href="{{ url('/forum') }}" class="btn-link">See All</a>
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