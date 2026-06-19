<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Discussion Hub | Group</title>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: "Segoe UI", system-ui, -apple-system, BlinkMacSystemFont, sans-serif;
            background: linear-gradient(180deg, #f0f9ff 0%, #e0f2fe 100%);
            color: #1f2937;
        }
        a { text-decoration: none; }
        .top-nav { background: #fff; border-bottom: 1px solid #e2e8f0; }
        .nav-inner { max-width: 1280px; margin: 0 auto; padding: 14px 18px; display:flex; align-items:center; justify-content:space-between; gap:10px; }
        .brand { display:flex; align-items:center; gap:10px; color:#2563eb; font-weight:700; }
        .nav-actions { display:flex; align-items:center; gap:10px; }
        .avatar { width:40px; height:40px; border-radius:50%; background:linear-gradient(90deg,#2563eb,#10b981); color:#fff; display:flex; align-items:center; justify-content:center; font-weight:700; }
        .logout-btn { background:#eff6ff; color:#2563eb; border:none; border-radius:10px; padding:10px 14px; font-weight:600; cursor:pointer; }
        .container { max-width: 1280px; margin: 0 auto; padding: 24px 18px 40px; }
        .group-header {
            background: linear-gradient(90deg, #2563eb, #1e40af);
            color: #fff;
            border-radius: 18px;
            padding: 26px;
            box-shadow: 0 15px 35px rgba(37,99,235,0.18);
            margin-bottom: 18px;
        }
        .group-header h1 { margin:0 0 8px; font-size:1.8rem; }
        .group-header p { margin:0; opacity:0.9; }
        .toolbar { display:flex; flex-wrap:wrap; gap:12px; justify-content:space-between; align-items:center; margin-bottom:16px; }
        .search-box { flex:1; min-width:240px; display:flex; align-items:center; gap:10px; background:#fff; padding:10px 14px; border-radius:12px; border:1px solid #dbe4f0; }
        .search-box input { width:100%; border:none; outline:none; }
        .create-btn {
            background: linear-gradient(90deg, #10b981, #059669);
            color:#fff;
            border:none;
            border-radius:10px;
            padding:12px 16px;
            font-weight:600;
            cursor:pointer;
        }
        .filters { display:flex; gap:8px; flex-wrap:wrap; margin-bottom:18px; }
        .filter-btn {
            background:#fff;
            color:#475569;
            border:1px solid #dbe4f0;
            border-radius:999px;
            padding:10px 12px;
            font-weight:600;
            cursor:pointer;
        }
        .filter-btn.active { background:#eef6ff; color:#2563eb; border-color:#bfdbfe; }
        .thread-list { display:flex; flex-direction:column; gap:16px; }
        .thread-card {
            background:#fff;
            border-radius:16px;
            box-shadow:0 12px 24px rgba(15,23,42,0.06);
            padding:18px;
        }
        .thread-top { display:flex; justify-content:space-between; gap:12px; align-items:flex-start; }
        .thread-top h3 { margin:0 0 8px; font-size:1.1rem; }
        .tag { display:inline-block; background:#eef6ff; color:#2563eb; border-radius:999px; padding:5px 10px; font-size:0.8rem; font-weight:600; }
        .thread-meta { color:#64748b; font-size:0.9rem; display:flex; gap:12px; flex-wrap:wrap; margin:10px 0; }
        .thread-preview { color:#475569; line-height:1.5; margin-bottom:12px; }
        .thread-actions { display:flex; gap:10px; flex-wrap:wrap; }
        .view-btn, .export-btn {
            border:none;
            border-radius:10px;
            padding:10px 12px;
            font-weight:600;
            cursor:pointer;
        }
        .view-btn { background:#eff6ff; color:#2563eb; }
        .export-btn { background:#f8fafc; color:#334155; }
        .pagination { display:flex; justify-content:center; gap:8px; margin-top:18px; }
        .page-btn { background:#fff; border:1px solid #dbe4f0; color:#475569; border-radius:10px; padding:10px 12px; }
        .page-btn.active { background:#2563eb; color:#fff; border-color:#2563eb; }
        @media (max-width: 640px) {
            .toolbar { align-items:stretch; }
            .create-btn { width:100%; }
            .thread-top { flex-direction:column; }
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
            <div style="display:flex;align-items:center;gap:10px;">
                <span class="avatar">{{ strtoupper(substr(Auth::user()->name ?? 'U', 0, 1)) }}</span>
                <span>{{ Auth::user()->name ?? 'User' }}</span>
            </div>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="logout-btn">Logout</button>
            </form>
        </div>
    </div>
</nav>

<main class="container">
    <section class="group-header">
        <h1>Database Design</h1>
        <p>Schema optimization, query planning, normalization, and performance tuning.</p>
    </section>

    <section class="toolbar">
        <div class="search-box">
            <span>🔎</span>
            <input type="text" placeholder="Search threads in this group...">
        </div>
        <button class="create-btn">Create New Thread</button>
    </section>

    <div class="filters">
        <button class="filter-btn active">All</button>
        <button class="filter-btn">Database</button>
        <button class="filter-btn">Web Dev</button>
        <button class="filter-btn">Java</button>
        <button class="filter-btn">AI</button>
    </div>

    <section class="thread-list">
        @php
            $threads = [
                [
                    'title' => 'Best practices for indexing large tables',
                    'category' => 'Database',
                    'author' => 'Amina K.',
                    'date' => 'June 18, 2026',
                    'replies' => 12,
                    'preview' => 'When dealing with high-volume queries, composite indexes often outperform single-column indexes when the query pattern is predictable.'
                ],
                [
                    'title' => 'How to normalize a product inventory schema?',
                    'category' => 'Database',
                    'author' => 'Omar T.',
                    'date' => 'June 16, 2026',
                    'replies' => 7,
                    'preview' => 'I’m trying to decide whether to split product variants into separate tables or keep them in one flexible structure.'
                ],
                [
                    'title' => 'Should we use SQL views for reporting?',
                    'category' => 'Web Dev',
                    'author' => 'Lily R.',
                    'date' => 'June 14, 2026',
                    'replies' => 9,
                    'preview' => 'Views can simplify repeated reporting logic, but they can also hide performance issues if used without care.'
                ],
            ];
        @endphp

        @foreach ($threads as $thread)
            <article class="thread-card">
                <div class="thread-top">
                    <div>
                        <span class="tag">{{ $thread['category'] }}</span>
                        <h3>{{ $thread['title'] }}</h3>
                    </div>
                    <button class="export-btn">Export to PDF</button>
                </div>
                <div class="thread-meta">
                    <span>Posted by {{ $thread['author'] }}</span>
                    <span>{{ $thread['date'] }}</span>
                    <span>{{ $thread['replies'] }} replies</span>
                </div>
                <div class="thread-preview">{{ $thread['preview'] }}</div>
                <div class="thread-actions">
                    <a href="{{ route('discussions.thread') }}" class="view-btn">View Thread</a>
                    <button class="export-btn">Export to PDF</button>
                </div>
            </article>
        @endforeach
    </section>

    <div class="pagination">
        <button class="page-btn">«</button>
        <button class="page-btn active">1</button>
        <button class="page-btn">2</button>
        <button class="page-btn">3</button>
        <button class="page-btn">»</button>
    </div>
</main>
</body>
</html>
