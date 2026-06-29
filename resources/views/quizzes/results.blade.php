<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quiz Results | Discussion Hub</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Inter', sans-serif;
            background: #F0F4FF;
            min-height: 100vh;
            padding: 32px 24px;
        }

        .results-card {
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 2px 12px rgba(37,99,235,0.07);
            max-width: 900px;
            margin: 0 auto;
            padding: 36px 40px;
        }

        /* Header */
        .results-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            margin-bottom: 32px;
        }

        .results-title {
            font-size: 22px;
            font-weight: 700;
            color: #0F172A;
            margin-bottom: 4px;
        }

        .results-meta {
            font-size: 13px;
            color: #64748B;
        }

        .results-meta span { margin-right: 12px; }

        .btn-export {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: #2563EB;
            color: #fff;
            border: none;
            border-radius: 8px;
            padding: 10px 20px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            transition: background 0.15s;
            white-space: nowrap;
        }

        .btn-export:hover { background: #1D4ED8; }

        .btn-export svg { width: 16px; height: 16px; }

        /* Stats */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;
            margin-bottom: 32px;
        }

        .stat-card {
            background: #EFF6FF;
            border-radius: 12px;
            padding: 20px 16px;
            text-align: center;
        }

        .stat-value {
            font-size: 28px;
            font-weight: 700;
            color: #2563EB;
            margin-bottom: 4px;
            line-height: 1;
        }

        .stat-label {
            font-size: 12px;
            color: #64748B;
            font-weight: 500;
        }

        /* Divider */
        .divider {
            border: none;
            border-top: 1px solid #E2E8F0;
            margin-bottom: 24px;
        }

        /* Table */
        .results-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
        }

        .results-table thead tr { background: #2563EB; }

        .results-table thead th {
            color: #fff;
            font-weight: 600;
            padding: 14px 16px;
            text-align: left;
            font-size: 13px;
        }

        .results-table thead th:first-child { border-radius: 8px 0 0 8px; }
        .results-table thead th:last-child  { border-radius: 0 8px 8px 0; }

        .results-table tbody tr {
            border-bottom: 1px solid #F1F5F9;
            transition: background 0.1s;
        }

        .results-table tbody tr:hover { background: #F8FAFF; }

        .results-table tbody td {
            padding: 14px 16px;
            color: #0F172A;
            vertical-align: middle;
        }

        .row-num { color: #2563EB; font-weight: 700; font-size: 13px; }
        .score-text { font-weight: 600; }

        .badge {
            display: inline-block;
            padding: 3px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            border: 1.5px solid;
        }

        .badge-manual { color: #16A34A; border-color: #16A34A; background: #F0FDF4; }
        .badge-auto   { color: #DC2626; border-color: #DC2626; background: #FEF2F2; }

        /* States */
        .state-loading, .state-error, .state-empty {
            text-align: center;
            padding: 40px 0;
            font-size: 14px;
        }

        .state-loading { color: #64748B; }
        .state-error   { color: #DC2626; }
        .state-empty   { color: #94A3B8; }

        .spinner {
            width: 28px; height: 28px;
            border: 3px solid #DBEAFE;
            border-top-color: #2563EB;
            border-radius: 50%;
            animation: spin 0.7s linear infinite;
            margin: 0 auto 12px;
        }

        @keyframes spin { to { transform: rotate(360deg); } }

        @media (max-width: 640px) {
            .results-card { padding: 24px 16px; }
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
            .results-header { flex-direction: column; gap: 16px; }
        }
    </style>
</head>
<body>

<div class="results-card">

    <div class="results-header">
        <div>
            <h1 class="results-title" id="quizTitle">Loading...</h1>
            <p class="results-meta">
                <span id="totalMarksLabel"></span>
                <span id="quizIdLabel"></span>
            </p>
        </div>
        <a href="#" class="btn-export" id="exportBtn">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M12 10v6m0 0l-3-3m3 3l3-3"/>
                <rect x="3" y="3" width="18" height="14" rx="2"/>
                <path d="M3 17v2a2 2 0 002 2h14a2 2 0 002-2v-2"/>
            </svg>
            Export PDF
        </a>
    </div>

    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-value" id="statAttempted">—</div>
            <div class="stat-label">Students Attempted</div>
        </div>
        <div class="stat-card">
            <div class="stat-value" id="statAvg">—</div>
            <div class="stat-label">Average Score</div>
        </div>
        <div class="stat-card">
            <div class="stat-value" id="statHighest">—</div>
            <div class="stat-label">Highest Score</div>
        </div>
        <div class="stat-card">
            <div class="stat-value" id="statAuto">—</div>
            <div class="stat-label">Auto-Submitted</div>
        </div>
    </div>

    <hr class="divider">

    <div id="tableContainer">
        <div class="state-loading">
            <div class="spinner"></div>
            <p>Loading results…</p>
        </div>
    </div>

</div>

<script>
    const quizID = {{ $quizID ?? 0 }};

    async function loadResults() {
        try {
            const res = await fetch(`/web/quiz/${quizID}/results`, {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            if (!res.ok) throw new Error('Server error ' + res.status);
            const data = await res.json();

            document.getElementById('quizTitle').textContent      = data.Title ?? 'Quiz Results';
            document.getElementById('totalMarksLabel').textContent = `Total Marks: ${parseFloat(data.TotalMarks).toFixed(2)}`;
            document.getElementById('quizIdLabel').textContent     = `· Quiz ID: ${data.QuizID}`;
            document.getElementById('exportBtn').href              = `/quiz/${quizID}/export-pdf`;

            const results = data.Results ?? [];

            const attempted = results.length;
            const avg       = attempted > 0
                ? (results.reduce((s, r) => s + parseFloat(r.Score), 0) / attempted).toFixed(1)
                : '0';
            const highest   = attempted > 0
                ? Math.max(...results.map(r => parseFloat(r.Score)))
                : 0;
            const autoCount = results.filter(r => r.IsAutoSubmit == 1).length;

            document.getElementById('statAttempted').textContent = attempted;
            document.getElementById('statAvg').textContent       = avg;
            document.getElementById('statHighest').textContent   = highest;
            document.getElementById('statAuto').textContent      = autoCount;

            const container = document.getElementById('tableContainer');

            if (results.length === 0) {
                container.innerHTML = `<div class="state-empty"><p>No submissions yet.</p></div>`;
                return;
            }

            const rows = results.map((r, i) => {
                const score   = parseFloat(r.Score).toFixed(2);
                const total   = parseFloat(data.TotalMarks).toFixed(2);
                const badge   = r.IsAutoSubmit == 1
                    ? '<span class="badge badge-auto">Auto</span>'
                    : '<span class="badge badge-manual">Manual</span>';
                const dateStr = r.SubmissionTime
                    ? new Date(r.SubmissionTime).toLocaleString('en-GB', {
                        day: '2-digit', month: 'short', year: 'numeric',
                        hour: '2-digit', minute: '2-digit'
                      })
                    : '—';
                return `
                <tr>
                    <td class="row-num">${i + 1}</td>
                    <td>${r.StudentName ?? '—'}</td>
                    <td class="score-text">${score} / ${total}</td>
                    <td>${badge}</td>
                    <td>${dateStr}</td>
                </tr>`;
            }).join('');

            container.innerHTML = `
                <table class="results-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Student</th>
                            <th>Score</th>
                            <th>Submission Type</th>
                            <th>Submitted At</th>
                        </tr>
                    </thead>
                    <tbody>${rows}</tbody>
                </table>`;

        } catch (err) {
            document.getElementById('tableContainer').innerHTML =
                `<div class="state-error">Failed to load results. (${err.message})</div>`;
        }
    }

    loadResults();
</script>

</body>
</html>