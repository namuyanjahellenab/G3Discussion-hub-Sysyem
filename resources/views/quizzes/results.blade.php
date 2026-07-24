@extends('layouts.app')

@section('content')
<style>
    .content { padding: 28px; }

    .results-card {
        background: var(--surface-card);
        border: 1px solid var(--surface-border);
        border-top: 3px solid var(--luna-mid);
        border-radius: var(--radius-lg);
        box-shadow: var(--shadow-soft);
        padding: 36px 40px;
    }

    .results-header {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        margin-bottom: 32px;
        flex-wrap: wrap;
        gap: 16px;
    }

    .results-title { font-size: 22px; font-weight: 700; color: var(--text-heading); margin-bottom: 4px; font-family: var(--font-display); }
    .results-meta { font-size: 13px; color: var(--text-muted); }
    .results-meta span { margin-right: 12px; }

    .btn-export {
        display: inline-flex; align-items: center; gap: 8px;
        background: var(--luna-mid); color: #fff; border: none;
        border-radius: var(--radius-md); padding: 10px 20px;
        font-size: 14px; font-weight: 600; cursor: pointer;
        text-decoration: none; transition: background 0.15s; white-space: nowrap;
    }
    .btn-export:hover { background: var(--luna-dark); }
    .btn-export svg { width: 16px; height: 16px; }

    .stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 32px; }
    .stat-card { background: var(--luna-lightest); border-radius: var(--radius-md); padding: 20px 16px; text-align: center; }
    .stat-value { font-size: 28px; font-weight: 700; color: var(--luna-dark); margin-bottom: 4px; line-height: 1; }
    .stat-label { font-size: 12px; color: var(--text-muted); font-weight: 500; }

    .divider { border: none; border-top: 1px solid var(--surface-border); margin-bottom: 24px; }

    .results-table { width: 100%; border-collapse: collapse; font-size: 14px; }
    .results-table thead tr { background: var(--luna-mid); }
    .results-table thead th { color: #fff; font-weight: 600; padding: 14px 16px; text-align: left; font-size: 13px; }
    .results-table thead th:first-child { border-radius: var(--radius-md) 0 0 var(--radius-md); }
    .results-table thead th:last-child { border-radius: 0 var(--radius-md) var(--radius-md) 0; }
    .results-table tbody tr { border-bottom: 1px solid var(--surface-border); transition: background 0.1s; }
    .results-table tbody tr:hover { background: var(--surface-bg); }
    .results-table tbody td { padding: 14px 16px; color: var(--text-heading); vertical-align: middle; }

    .row-num { color: var(--luna-mid); font-weight: 700; font-size: 13px; }
    .score-text { font-weight: 600; }

    .badge { display: inline-block; padding: 3px 12px; border-radius: 999px; font-size: 12px; font-weight: 600; border: 1.5px solid; }
    .badge-manual { color: var(--accent-success); border-color: var(--accent-success); background: var(--accent-success-bg); }
    .badge-auto { color: var(--accent-danger); border-color: var(--accent-danger); background: var(--accent-danger-bg); }

    .state-loading, .state-error, .state-empty { text-align: center; padding: 40px 0; font-size: 14px; }
    .state-loading { color: var(--text-muted); }
    .state-error { color: var(--accent-danger); }
    .state-empty { color: var(--text-muted); }

    .spinner {
        width: 28px; height: 28px; border: 3px solid var(--luna-lightest);
        border-top-color: var(--luna-mid); border-radius: 50%;
        animation: spin 0.7s linear infinite; margin: 0 auto 12px;
    }
    @keyframes spin { to { transform: rotate(360deg); } }

    @media (max-width: 640px) {
        .results-card { padding: 24px 16px; }
        .stats-grid { grid-template-columns: repeat(2, 1fr); }
        .results-header { flex-direction: column; }
    }
</style>

<div class="content">
    <div class="results-card">

        <div class="results-header">
            <div>
                <h1 class="results-title" id="quizTitle">Loading...</h1>
                <p class="results-meta">
                    <span id="totalMarksLabel"></span>
                    <span id="quizIdLabel"></span>
                </p>
            </div>
            <a href="#" class="btn-export" id="exportBtn" style="pointer-events: none; opacity: 0.5;" aria-disabled="true" title="Exporting quiz results to PDF is not available yet">
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
</div>

<script>
    const quizID = {{ $quizID ?? 0 }};
    const noQuiz = {{ ($noQuiz ?? false) ? 'true' : 'false' }};


    async function loadResults() {
        if (noQuiz) {
            document.getElementById('quizTitle').textContent = 'No Quizzes Yet';
            document.getElementById('totalMarksLabel').textContent = '';
            document.getElementById('quizIdLabel').textContent = '';
            document.getElementById('exportBtn').style.display = 'none';
            document.getElementById('tableContainer').innerHTML =
                `<div class="state-empty"><p>You haven't scheduled any quizzes yet. Once you do, results will appear here.</p></div>`;
            document.querySelector('.stats-grid').style.display = 'none';
            return;
        }
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

            const exportBtn = document.getElementById('exportBtn');
            exportBtn.href = `/quiz/${quizID}/results/export-pdf`;
            exportBtn.removeAttribute('aria-disabled');
            exportBtn.title = 'Export these results to PDF';
            exportBtn.style.pointerEvents = '';
            exportBtn.style.opacity = '';

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
                    <td style="display:flex; gap:8px;">
                        <a href="/quiz/${quizID}/results/${r.ResultID}/review" class="btn-export" style="padding:6px 14px;font-size:12.5px;">Review</a>
                        <a href="/quiz/result/${r.ResultID}/grade" class="btn-export" style="padding:6px 14px;font-size:12.5px;">Grade</a>
                    </td>
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
                            <th>Actions</th>
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
@endsection
