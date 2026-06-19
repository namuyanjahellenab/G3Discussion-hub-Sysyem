<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Discussion Hub | Thread</title>
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
        .container { max-width: 980px; margin: 0 auto; padding: 24px 18px 40px; }
        .thread-header {
            background:#fff;
            border-radius:16px;
            box-shadow:0 12px 24px rgba(15,23,42,0.06);
            padding:22px;
            margin-bottom:16px;
        }
        .thread-header h1 { margin:0 0 10px; font-size:1.9rem; }
        .tag { display:inline-block; background:#eef6ff; color:#2563eb; border-radius:999px; padding:5px 10px; font-size:0.8rem; font-weight:600; }
        .thread-actions { display:flex; gap:10px; flex-wrap:wrap; margin-top:12px; }
        .btn-primary, .btn-secondary, .btn-social {
            border:none;
            border-radius:10px;
            padding:10px 12px;
            font-weight:600;
            cursor:pointer;
        }
        .btn-primary { background: linear-gradient(90deg, #2563eb, #1e40af); color:#fff; }
        .btn-secondary { background:#f8fafc; color:#334155; }
        .post-card, .reply-card {
            background:#fff;
            border-radius:16px;
            box-shadow:0 12px 24px rgba(15,23,42,0.06);
            padding:18px;
            margin-bottom:16px;
        }
        .post-meta, .reply-meta { display:flex; align-items:center; justify-content:space-between; gap:10px; flex-wrap:wrap; color:#64748b; font-size:0.9rem; }
        .author-row { display:flex; align-items:center; gap:12px; }
        .content { color:#334155; line-height:1.7; margin-top:12px; }
        .reply-form {
            background:#fff;
            border-radius:16px;
            box-shadow:0 12px 24px rgba(15,23,42,0.06);
            padding:18px;
        }
        textarea {
            width:100%;
            min-height:180px;
            border-radius:12px;
            border:1px solid #dbe4f0;
            padding:14px;
            outline:none;
            font-family:inherit;
            resize:vertical;
        }
        textarea:focus { border-color:#2563eb; box-shadow:0 0 0 3px rgba(37,99,235,0.12); }
        .socials { display:flex; gap:8px; }
        .btn-social { background:#eef6ff; color:#2563eb; }
        @media (max-width: 640px) {
            .thread-header h1 { font-size:1.6rem; }
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
    <section class="thread-header">
        <span class="tag">Database</span>
        <h1>Best practices for indexing large tables</h1>
        <div class="thread-actions">
            <button class="btn-primary">Export to PDF</button>
            <div class="socials">
                <button class="btn-social">Facebook</button>
                <button class="btn-social">Twitter</button>
                <button class="btn-social">LinkedIn</button>
            </div>
        </div>
    </section>

    <section class="post-card">
        <div class="post-meta">
            <div class="author-row">
                <span class="avatar" style="width:48px;height:48px;">AK</span>
                <div>
                    <strong>Amina Khan</strong><br>
                    <span>Posted on June 18, 2026 at 09:30 AM</span>
                </div>
            </div>
        </div>
        <div class="content">
            When managing large tables, it is important to choose indexes based on actual query patterns instead of guessing. Composite indexes can greatly improve performance when the queries consistently filter by a small set of columns. I also recommend checking execution plans before adding new indexes to avoid unnecessary overhead.
        </div>
    </section>

    <section>
        @php
            $replies = [
                [
                    'author' => 'Professor Lee',
                    'initials' => 'PL',
                    'time' => 'June 18, 2026 at 11:05 AM',
                    'content' => 'This is a great point. I would also suggest reviewing the cardinality of the columns involved before creating a composite index.'
                ],
                [
                    'author' => 'Jordan Blake',
                    'initials' => 'JB',
                    'time' => 'June 18, 2026 at 01:22 PM',
                    'content' => 'For analytics-heavy workloads, I have seen partial indexes help reduce storage use while still improving query speed.'
                ],
            ];
        @endphp

        @foreach ($replies as $reply)
            <article class="reply-card">
                <div class="reply-meta">
                    <div class="author-row">
                        <span class="avatar" style="width:44px;height:44px;">{{ $reply['initials'] }}</span>
                        <div>
                            <strong>{{ $reply['author'] }}</strong><br>
                            <span>{{ $reply['time'] }}</span>
                        </div>
                    </div>
                    @if ($reply['author'] === 'Professor Lee')
                        <button class="btn-secondary">Mark as Helpful</button>
                    @endif
                </div>
                <div class="content">{{ $reply['content'] }}</div>
            </article>
        @endforeach
    </section>

    <section class="reply-form">
        <h3 style="margin-top:0;">Post a Reply</h3>
        <form>
            @csrf
            <textarea placeholder="Write your response..."></textarea>
            <div style="display:flex; justify-content:space-between; align-items:center; gap:12px; margin-top:12px; flex-wrap:wrap;">
                <span style="color:#64748b; font-size:0.9rem;">Use markdown for formatting if needed.</span>
                <button type="submit" class="btn-primary">Post Reply</button>
            </div>
        </form>
    </section>
</main>
</body>
</html>
