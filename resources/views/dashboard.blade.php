<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Discussion Hub | Dashboard</title>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: "Segoe UI", system-ui, -apple-system, BlinkMacSystemFont, sans-serif;
            background: linear-gradient(180deg, #f0f9ff 0%, #e0f2fe 100%);
            color: #1f2937;
        }
        a { text-decoration: none; }
        .top-nav {
            background: #fff;
            border-bottom: 1px solid #e2e8f0;
            position: sticky;
            top: 0;
            z-index: 20;
        }
        .nav-inner {
            max-width: 1280px;
            margin: 0 auto;
            padding: 14px 18px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
        }
        .brand { display: flex; align-items: center; gap: 10px; font-weight: 700; color: #2563eb; }
        .nav-actions { display: flex; align-items: center; gap: 10px; }
        .profile-menu {
            position: relative;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            cursor: pointer;
            padding: 8px 12px;
            border-radius: 999px;
            background: #f8fafc;
        }
        .profile-menu:hover { background: #eef6ff; }
        .profile-dropdown {
            position: absolute;
            right: 0;
            top: calc(100% + 8px);
            background: #fff;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
            min-width: 180px;
            display: none;
            box-shadow: 0 12px 24px rgba(15, 23, 42, 0.08);
            overflow: hidden;
        }
        .profile-dropdown a,
        .profile-dropdown button {
            display: block;
            width: 100%;
            text-align: left;
            padding: 12px 14px;
            background: #fff;
            border: none;
            color: #334155;
            font-size: 0.95rem;
            cursor: pointer;
        }
        .profile-dropdown a:hover,
        .profile-dropdown button:hover { background: #f8fafc; }
        .avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(90deg, #2563eb, #10b981);
            color: #fff;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
        }
        .logout-btn, .create-btn, .view-btn, .filter-btn {
            border: none;
            cursor: pointer;
            transition: transform 0.15s ease, box-shadow 0.2s ease, background 0.2s ease;
        }
        .logout-btn:hover, .create-btn:hover, .view-btn:hover, .filter-btn:hover { transform: translateY(-1px); }
        .logout-btn {
            background: #eff6ff;
            color: #2563eb;
            padding: 10px 14px;
            border-radius: 10px;
            font-weight: 600;
        }
        .container { max-width: 1280px; margin: 0 auto; padding: 24px 18px 40px; }
        .welcome-panel {
            background: linear-gradient(90deg, #2563eb, #1e40af);
            color: #fff;
            padding: 28px;
            border-radius: 18px;
            box-shadow: 0 15px 35px rgba(37, 99, 235, 0.18);
            margin-bottom: 18px;
        }
        .welcome-panel h2 { margin: 0 0 8px; font-size: 1.8rem; }
        .toolbar {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 18px;
        }
        .search-box {
            flex: 1;
            min-width: 240px;
            display: flex;
            align-items: center;
            gap: 10px;
            background: #fff;
            border-radius: 12px;
            border: 1px solid #dbe4f0;
            padding: 10px 14px;
        }
        .search-box input {
            border: none;
            outline: none;
            width: 100%;
            font-size: 0.95rem;
        }
        .create-btn {
            background: linear-gradient(90deg, #10b981, #059669);
            color: #fff;
            padding: 12px 16px;
            border-radius: 10px;
            font-weight: 600;
            box-shadow: 0 10px 20px rgba(16, 185, 129, 0.18);
        }
        .filters { display: flex; gap: 8px; flex-wrap: wrap; }
        .filter-btn {
            background: #fff;
            color: #475569;
            border: 1px solid #dbe4f0;
            padding: 10px 12px;
            border-radius: 999px;
            font-weight: 600;
        }
        .filter-btn.active {
            background: #eef6ff;
            color: #2563eb;
            border-color: #bfdbfe;
        }
        .content-grid {
            display: grid;
            grid-template-columns: 2.2fr 1fr;
            gap: 18px;
        }
        .panel {
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 12px 24px rgba(15, 23, 42, 0.06);
            padding: 18px;
        }
        .group-list { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 16px; }
        .group-card {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            padding: 18px;
        }
        .group-card h3 { margin: 0 0 8px; font-size: 1rem; }
        .group-card p { margin: 0; color: #64748b; font-size: 0.92rem; }
        .meta-row {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            margin: 14px 0;
            color: #64748b;
            font-size: 0.9rem;
        }
        .view-btn {
            background: #eff6ff;
            color: #2563eb;
            padding: 10px 12px;
            border-radius: 10px;
            font-weight: 600;
        }
        .activity-list { list-style: none; margin: 0; padding: 0; }
        .activity-item {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            padding: 12px 0;
            border-bottom: 1px solid #eef2f7;
        }
        .activity-item:last-child { border-bottom: none; }
        .dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: #10b981;
            margin-top: 7px;
        }
        .activity-item strong { display: block; }
        @media (max-width: 900px) {
            .content-grid { grid-template-columns: 1fr; }
            .group-list { grid-template-columns: 1fr; }
        }
        @media (max-width: 640px) {
            .nav-inner { flex-wrap: wrap; }
            .container { padding: 18px 12px 30px; }
            .welcome-panel { padding: 22px 18px; }
            .toolbar { align-items: stretch; }
            .create-btn { width: 100%; }
        }
    </style>
</head>
<body>
<nav class="top-nav">
    <div class="nav-inner">
        <a href="{{ route('dashboard') }}" class="brand">
            <span class="avatar" style="width:36px;height:36px;">DH</span>
            <span>Discussion Hub</span>
        </a>
        <div class="nav-actions">
            <div class="profile-menu" onclick="toggleProfileMenu()">
                <span class="avatar">{{ strtoupper(substr(Auth::user()->name ?? 'U', 0, 1)) }}</span>
                <span>{{ Auth::user()->name ?? 'User' }}</span>
                <span style="color:#64748b;">▾</span>
                <div class="profile-dropdown" id="profileDropdown">
                    <a href="{{ route('profile.edit') }}">Profile</a>
                    <a href="{{ route('dashboard') }}">Dashboard</a>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit">Logout</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</nav>

<main class="container">
    <section class="welcome-panel">
        <h2>Welcome back, {{ Auth::user()->name ?? 'Student' }}</h2>
        <p style="margin:0; opacity:0.9;">Here’s what’s happening in your discussion spaces today.</p>
    </section>

    <section class="toolbar">
        <div class="search-box">
            <span>🔎</span>
            <input type="text" placeholder="Search discussions, topics, or members...">
        </div>
        <a href="{{ route('discussions.group') }}" class="create-btn">Create New Discussion</a>
    </section>

    <div class="filters">
        <button class="filter-btn active">All Groups</button>
        <button class="filter-btn">My Groups</button>
        <button class="filter-btn">Following</button>
    </div>

    <section class="content-grid" style="margin-top:18px;">
        <div>
            <div class="panel">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;">
                    <h3 style="margin:0;">Discussion Groups</h3>
                    <span style="color:#64748b; font-size:0.9rem;">Updated 5 mins ago</span>
                </div>
                <div class="group-list">
                    @php
                        $groups = [
                            ['name' => 'Database Design', 'description' => 'Schema optimization, queries, and indexing discussions.', 'members' => 128, 'posts' => 24],
                            ['name' => 'Web Development', 'description' => 'Frontend frameworks, APIs, and deployment practices.', 'members' => 206, 'posts' => 41],
                            ['name' => 'Java Programming', 'description' => 'Core concepts, OOP, and coding interview problem solving.', 'members' => 94, 'posts' => 17],
                            ['name' => 'Research Methods', 'description' => 'Peer review, citations, and academic project planning.', 'members' => 72, 'posts' => 11],
                        ];
                    @endphp
                    @foreach ($groups as $group)
                        <div class="group-card">
                            <h3>{{ $group['name'] }}</h3>
                            <p>{{ $group['description'] }}</p>
                            <div class="meta-row">
                                <span>{{ $group['members'] }} members</span>
                                <span>{{ $group['posts'] }} recent posts</span>
                            </div>
                            <a href="{{ route('discussions.group') }}" class="view-btn">View Group</a>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        <aside class="panel">
            <h3 style="margin:0 0 14px;">Recent Activity</h3>
            <ul class="activity-list">
                @php
                    $activities = [
                        ['title' => 'New reply in Database Design', 'detail' => 'Amina replied to “Best indexing strategy.”'],
                        ['title' => 'Thread updated', 'detail' => 'Professor Lee commented on “REST API best practices.”'],
                        ['title' => 'New discussion started', 'detail' => 'Jordan created “How to structure a final project?”'],
                        ['title' => 'Member joined', 'detail' => 'Tariq joined Web Development group.'],
                    ];
                @endphp
                @foreach ($activities as $activity)
                    <li class="activity-item">
                        <span class="dot"></span>
                        <div>
                            <strong>{{ $activity['title'] }}</strong>
                            <span style="color:#64748b; font-size:0.92rem;">{{ $activity['detail'] }}</span>
                        </div>
                    </li>
                @endforeach
            </ul>
        </aside>
    </section>
</main>
<script>
    function toggleProfileMenu() {
        const menu = document.getElementById('profileDropdown');
        menu.style.display = menu.style.display === 'block' ? 'none' : 'block';
    }

    document.addEventListener('click', function (event) {
        const menu = document.getElementById('profileDropdown');
        const trigger = document.querySelector('.profile-menu');
        if (trigger && !trigger.contains(event.target) && menu) {
            menu.style.display = 'none';
        }
    });
</script>
<script src="/js/quiz-alert.js"></script>
</body>
</html>
