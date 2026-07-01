<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Quiz | Discussion Hub</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Segoe UI', Arial, sans-serif; }

        :root {
            --blue:       #0052CC;
            --blue-dark:  #003D99;
            --blue-light: #DEEBFF;
            --blue-mid:   #E8F0FF;
            --text:       #172B4D;
            --text-mid:   #5E6C84;
            --text-light: #8993A4;
            --border:     #DFE1E6;
            --bg:         #F4F5F7;
            --white:      #FFFFFF;
            --red:        #DE350B;
            --red-bg:     #FFEBE6;
            --green:      #36B37E;
            --green-bg:   #E3FCEF;
            --yellow:     #FFAB00;
            --yellow-bg:  #FFFAE6;
            --radius:     8px;
            --shadow:     0 2px 8px rgba(0,0,0,0.10);
        }

        body {
            background: var(--bg);
            color: var(--text);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        /* ── TOP BAR ── */
        .quiz-topbar {
            background: var(--blue);
            color: white;
            padding: 0 32px;
            height: 56px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: sticky;
            top: 0;
            z-index: 100;
            box-shadow: 0 2px 8px rgba(0,82,204,0.25);
        }

        .topbar-left {
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .logo-icon {
            width: 30px; height: 30px;
            background: rgba(255,255,255,0.2);
            border-radius: 6px;
            display: flex; align-items: center; justify-content: center;
        }
        .logo-icon svg { width: 16px; height: 16px; fill: white; }

        .quiz-title-bar {
            font-size: 15px;
            font-weight: 700;
            letter-spacing: 0.2px;
        }

        .quiz-subtitle {
            font-size: 12px;
            opacity: 0.75;
            margin-top: 1px;
        }

        .topbar-center {
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        /* Timer */
        .timer-wrap {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 2px;
        }

        .timer-label {
            font-size: 10px;
            opacity: 0.75;
            letter-spacing: 0.8px;
            text-transform: uppercase;
        }

        .timer-display {
            font-size: 26px;
            font-weight: 800;
            letter-spacing: 2px;
            font-variant-numeric: tabular-nums;
            transition: color 0.3s;
        }

        .timer-display.warning { color: #FFAB00; }
        .timer-display.danger  { color: #FF5630; animation: pulse 1s infinite; }

        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50%       { opacity: 0.5; }
        }

        .topbar-right {
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .progress-text {
            font-size: 12px;
            opacity: 0.85;
            text-align: right;
        }

        .submit-btn {
            background: white;
            color: var(--blue);
            border: none;
            padding: 8px 18px;
            border-radius: var(--radius);
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 6px;
            transition: background 0.15s, transform 0.1s;
        }
        .submit-btn:hover { background: #E8F0FF; transform: translateY(-1px); }
        .submit-btn svg { width: 14px; height: 14px; }

        /* ── PROGRESS BAR ── */
        .progress-bar-wrap {
            height: 4px;
            background: rgba(255,255,255,0.2);
            position: sticky;
            top: 56px;
            z-index: 99;
        }
        .progress-bar-fill {
            height: 100%;
            background: #36B37E;
            transition: width 0.4s ease;
            width: 0%;
        }

        /* ── MAIN CONTENT ── */
        .quiz-main {
            flex: 1;
            max-width: 860px;
            width: 100%;
            margin: 0 auto;
            padding: 32px 20px 80px;
        }

        /* ── QUESTION NAVIGATION DOTS ── */
        .q-nav {
            display: flex;
            align-items: center;
            gap: 6px;
            margin-bottom: 24px;
            flex-wrap: wrap;
        }

        .q-nav-label {
            font-size: 12px;
            color: var(--text-light);
            margin-right: 4px;
            font-weight: 600;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }

        .q-dot {
            width: 28px; height: 28px;
            border-radius: 50%;
            border: 2px solid var(--border);
            background: white;
            font-size: 11px;
            font-weight: 700;
            color: var(--text-mid);
            cursor: pointer;
            display: flex; align-items: center; justify-content: center;
            transition: all 0.15s;
        }
        .q-dot:hover    { border-color: var(--blue); color: var(--blue); }
        .q-dot.answered { background: var(--green); border-color: var(--green); color: white; }
        .q-dot.current  { background: var(--blue); border-color: var(--blue); color: white; }
        .q-dot.answered.current { background: var(--blue); border-color: var(--blue); }

        /* ── QUESTION CARDS ── */
        .questions-block {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .q-card {
            background: white;
            border: 1.5px solid var(--border);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            overflow: hidden;
            transition: border-color 0.2s;
        }
        .q-card.active-card { border-color: var(--blue); }

        .q-card-header {
            padding: 14px 20px;
            background: var(--blue-mid);
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .q-number {
            font-size: 12px;
            font-weight: 700;
            color: var(--blue);
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }

        .q-marks-badge {
            font-size: 11px;
            background: var(--blue);
            color: white;
            padding: 2px 8px;
            border-radius: 12px;
            font-weight: 600;
        }

        .q-card-body { padding: 20px; }

        .q-text {
            font-size: 15px;
            font-weight: 600;
            color: var(--text);
            line-height: 1.6;
            margin-bottom: 18px;
        }

        /* MCQ Options */
        .options-list {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .option-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 16px;
            border: 1.5px solid var(--border);
            border-radius: var(--radius);
            cursor: pointer;
            transition: all 0.15s;
            user-select: none;
        }
        .option-item:hover { border-color: var(--blue); background: var(--blue-mid); }
        .option-item.selected {
            border-color: var(--blue);
            background: var(--blue-light);
        }

        .option-letter {
            width: 28px; height: 28px;
            border-radius: 50%;
            border: 2px solid var(--border);
            display: flex; align-items: center; justify-content: center;
            font-size: 12px; font-weight: 700;
            color: var(--text-mid);
            flex-shrink: 0;
            transition: all 0.15s;
        }
        .option-item.selected .option-letter {
            background: var(--blue);
            border-color: var(--blue);
            color: white;
        }

        .option-text {
            font-size: 14px;
            color: var(--text);
            flex: 1;
        }

        .option-check {
            width: 18px; height: 18px;
            color: var(--blue);
            display: none;
            flex-shrink: 0;
        }
        .option-item.selected .option-check { display: none; }

        /* Open text */
        .open-textarea {
            width: 100%;
            padding: 12px 14px;
            border: 1.5px solid var(--border);
            border-radius: var(--radius);
            font-size: 14px;
            color: var(--text);
            resize: vertical;
            min-height: 100px;
            outline: none;
            font-family: inherit;
            transition: border-color 0.15s;
        }
        .open-textarea:focus { border-color: var(--blue); box-shadow: 0 0 0 2px rgba(0,82,204,0.1); }

        /* Answered indicator */
        .q-answered-tag {
            display: none;
            align-items: center;
            gap: 4px;
            font-size: 11px;
            font-weight: 700;
            color: var(--green);
            margin-top: 12px;
        }
        .q-answered-tag.show { display: flex; }
        .q-answered-tag svg { width: 13px; height: 13px; }

        /* ── PAGINATION ── */
        .pagination {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-top: 28px;
            padding: 0 4px;
        }

        .page-info {
            font-size: 13px;
            color: var(--text-mid);
        }

        .page-btn {
            display: flex;
            align-items: center;
            gap: 6px;
            padding: 9px 18px;
            border-radius: var(--radius);
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            border: 1.5px solid var(--border);
            background: white;
            color: var(--text);
            transition: all 0.15s;
        }
        .page-btn:hover:not(:disabled) { border-color: var(--blue); color: var(--blue); background: var(--blue-mid); }
        .page-btn:disabled { opacity: 0.4; cursor: not-allowed; }
        .page-btn svg { width: 14px; height: 14px; }
        .page-btn.primary { background: var(--blue); color: white; border-color: var(--blue); }
        .page-btn.primary:hover { background: var(--blue-dark); }

        /* ── STATUS BAR ── */
        .status-bar {
            position: fixed;
            bottom: 0; left: 0; right: 0;
            background: white;
            border-top: 1px solid var(--border);
            padding: 10px 32px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 -2px 8px rgba(0,0,0,0.06);
            z-index: 90;
        }

        .status-answered {
            font-size: 13px;
            color: var(--text-mid);
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .status-answered strong { color: var(--text); }

        .answered-bar {
            width: 120px; height: 6px;
            background: var(--border);
            border-radius: 3px;
            overflow: hidden;
        }
        .answered-fill {
            height: 100%;
            background: var(--green);
            border-radius: 3px;
            transition: width 0.3s;
        }

        .status-hint {
            font-size: 12px;
            color: var(--text-light);
            display: flex;
            align-items: center;
            gap: 5px;
        }
        .status-hint svg { width: 13px; height: 13px; color: var(--yellow); }

        /* ── SUBMIT MODAL ── */
        .modal-overlay {
            display: none;
            position: fixed; inset: 0;
            background: rgba(23,43,77,0.55);
            z-index: 200;
            align-items: center;
            justify-content: center;
        }
        .modal-overlay.show { display: flex; }

        .modal {
            background: white;
            border-radius: 12px;
            padding: 32px;
            max-width: 420px;
            width: 90%;
            box-shadow: 0 8px 32px rgba(0,0,0,0.18);
            text-align: center;
        }

        .modal-icon {
            width: 56px; height: 56px;
            border-radius: 50%;
            background: var(--blue-mid);
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto 16px;
        }
        .modal-icon svg { width: 28px; height: 28px; color: var(--blue); }
        .modal-icon.success-icon { background: var(--green-bg); }
        .modal-icon.success-icon svg { color: var(--green); }
        .modal-icon.warning-icon { background: var(--yellow-bg); }
        .modal-icon.warning-icon svg { color: var(--yellow); }

        .modal h2 { font-size: 18px; color: var(--text); margin-bottom: 8px; }
        .modal p  { font-size: 13px; color: var(--text-mid); line-height: 1.6; margin-bottom: 20px; }

        .modal-stats {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            margin-bottom: 20px;
        }
        .modal-stat {
            background: var(--bg);
            border-radius: 6px;
            padding: 10px;
        }
        .modal-stat-num  { font-size: 22px; font-weight: 800; color: var(--blue); }
        .modal-stat-label{ font-size: 11px; color: var(--text-light); margin-top: 2px; }

        .modal-actions { display: flex; gap: 10px; justify-content: center; }
        .modal-btn {
            padding: 10px 22px;
            border-radius: var(--radius);
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
            border: none;
            transition: background 0.15s;
        }
        .modal-btn-outline {
            background: white;
            color: var(--text);
            border: 1.5px solid var(--border);
        }
        .modal-btn-outline:hover { border-color: var(--blue); color: var(--blue); }
        .modal-btn-primary { background: var(--blue); color: white; }
        .modal-btn-primary:hover { background: var(--blue-dark); }
        .modal-btn-green { background: var(--green); color: white; }

        /* Loading state */
        .loading-spinner {
            display: none;
            width: 20px; height: 20px;
            border: 2px solid rgba(255,255,255,0.3);
            border-top-color: white;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
            margin: 0 auto;
        }
        @keyframes spin { to { transform: rotate(360deg); } }

        /* Auto-submit overlay */
        .auto-submit-overlay {
            display: none;
            position: fixed; inset: 0;
            background: rgba(23,43,77,0.8);
            z-index: 300;
            align-items: center;
            justify-content: center;
            flex-direction: column;
            gap: 16px;
            color: white;
            text-align: center;
        }
        .auto-submit-overlay.show { display: flex; }
        .auto-submit-overlay h2 { font-size: 22px; }
        .auto-submit-overlay p  { font-size: 14px; opacity: 0.8; }

        @media (max-width: 600px) {
            .quiz-topbar { padding: 0 16px; }
            .quiz-main   { padding: 20px 12px 80px; }
            .timer-display { font-size: 20px; }
            .quiz-title-bar { font-size: 13px; }
            .submit-btn span { display: none; }
            .status-bar { padding: 10px 16px; }
        }
    </style>
</head>
<body>

<!-- ── TOP BAR ── -->
<header class="quiz-topbar">
    <div class="topbar-left">
        <div class="logo-icon">
            <svg viewBox="0 0 24 24"><path d="M20 2H4c-1.1 0-2 .9-2 2v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2z"/></svg>
        </div>
        <div>
            <div class="quiz-title-bar" id="quizTitleBar">Loading quiz...</div>
            <div class="quiz-subtitle" id="quizSubtitle">Discussion Hub Assessment</div>
        </div>
    </div>

    <div class="topbar-center">
        <div class="timer-wrap">
            <div class="timer-label">Time Remaining</div>
            <div class="timer-display" id="timerDisplay">--:--</div>
        </div>
    </div>

    <div class="topbar-right">
        <div class="progress-text" id="progressText">0 / 0 answered</div>
        <button class="submit-btn" onclick="showSubmitModal()">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <line x1="22" y1="2" x2="11" y2="13"/>
                <polygon points="22 2 15 22 11 13 2 9 22 2"/>
            </svg>
            <span>Submit Quiz</span>
        </button>
    </div>
</header>

<!-- Progress bar -->
<div class="progress-bar-wrap">
    <div class="progress-bar-fill" id="progressBarFill"></div>
</div>

<!-- ── MAIN ── -->
<main class="quiz-main">
    <!-- Question nav dots -->
    <div class="q-nav" id="qNavDots">
        <span class="q-nav-label">Questions:</span>
    </div>

    <!-- Questions block (3 at a time) -->
    <div class="questions-block" id="questionsBlock">
        <div style="text-align:center;padding:60px 0;color:var(--text-light);">
            <div class="loading-spinner" style="display:block;border-color:var(--border);border-top-color:var(--blue);margin:0 auto 16px;"></div>
            Loading questions...
        </div>
    </div>

    <!-- Pagination -->
    <div class="pagination" id="pagination" style="display:none;">
        <button class="page-btn" id="prevBtn" onclick="changePage(-1)" disabled>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <polyline points="15 18 9 12 15 6"/>
            </svg>
            Previous
        </button>
        <span class="page-info" id="pageInfo"></span>
        <button class="page-btn primary" id="nextBtn" onclick="changePage(1)">
            Next
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <polyline points="9 18 15 12 9 6"/>
            </svg>
        </button>
    </div>
</main>

<!-- ── STATUS BAR ── -->
<div class="status-bar">
    <div class="status-answered">
        <strong id="answeredCount">0</strong>&nbsp;of&nbsp;<strong id="totalCount">0</strong>&nbsp;answered
        <div class="answered-bar">
            <div class="answered-fill" id="answeredFill"></div>
        </div>
    </div>
    <div class="status-hint">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <circle cx="12" cy="12" r="10"/>
            <line x1="12" y1="8" x2="12" y2="12"/>
            <line x1="12" y1="16" x2="12.01" y2="16"/>
        </svg>
        Quiz auto-submits when timer reaches 0:00
    </div>
</div>

<!-- ── SUBMIT CONFIRM MODAL ── -->
<div class="modal-overlay" id="submitModal">
    <div class="modal">
        <div class="modal-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <line x1="22" y1="2" x2="11" y2="13"/>
                <polygon points="22 2 15 22 11 13 2 9 22 2"/>
            </svg>
        </div>
        <h2>Submit Quiz?</h2>
        <p>Once submitted you cannot change your answers.</p>
        <div class="modal-stats">
            <div class="modal-stat">
                <div class="modal-stat-num" id="modalAnswered">0</div>
                <div class="modal-stat-label">Answered</div>
            </div>
            <div class="modal-stat">
                <div class="modal-stat-num" id="modalUnanswered">0</div>
                <div class="modal-stat-label">Unanswered</div>
            </div>
        </div>
        <div class="modal-actions">
            <button class="modal-btn modal-btn-outline" onclick="hideSubmitModal()">Go Back</button>
            <button class="modal-btn modal-btn-primary" onclick="submitQuiz(false)">Submit Now</button>
        </div>
    </div>
</div>

<!-- ── SUCCESS MODAL ── -->
<div class="modal-overlay" id="successModal">
    <div class="modal">
        <div class="modal-icon success-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <polyline points="20 6 9 17 4 12"/>
            </svg>
        </div>
        <h2>Quiz Submitted!</h2>
        <p id="successMsg">Your answers have been saved successfully.</p>
        <div class="modal-stats">
            <div class="modal-stat">
                <div class="modal-stat-num" id="scoreDisplay">—</div>
                <div class="modal-stat-label">Your Score</div>
            </div>
            <div class="modal-stat">
                <div class="modal-stat-num" id="totalMarksDisplay">—</div>
                <div class="modal-stat-label">Total Marks</div>
            </div>
        </div>
        <div class="modal-actions">
            <button class="modal-btn modal-btn-green" onclick="window.location='/dashboard'">
                Back to Dashboard
            </button>
        </div>
    </div>
</div>

<!-- ── AUTO SUBMIT OVERLAY ── -->
<div class="auto-submit-overlay" id="autoSubmitOverlay">
    <div class="loading-spinner" style="display:block;width:40px;height:40px;border-width:3px;"></div>
    <h2>⏰ Time's Up!</h2>
    <p>Auto-submitting your answers...</p>
</div>

<script>
    // ── CONFIG ──────────────────────────────────────────────────
    const QUESTIONS_PER_PAGE = 3;
    const CSRF = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

    // Get quiz ID from URL: /quiz/3/take
    const pathParts = window.location.pathname.split('/');
    const QUIZ_ID   = parseInt(pathParts[pathParts.length - 2]) || 0;

    // ── STATE ───────────────────────────────────────────────────
    let questions      = [];
    let answers        = {};   // { questionID: responseText }
    let currentPage    = 0;
    let totalPages     = 0;
    let timerInterval  = null;
    let secondsLeft    = 0;
    let submitted      = false;
    let totalMarks     = 0;

    // ── INIT ────────────────────────────────────────────────────
    window.addEventListener('DOMContentLoaded', loadQuiz);

    async function loadQuiz() {
        try {
            const res  = await fetch('/web/quiz/join', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept':       'application/json',
                    'X-CSRF-TOKEN': CSRF,
                },
                body: JSON.stringify({ QuizID: QUIZ_ID }),
            });

            const data = await res.json();

            if (!res.ok) {
                showError(data.error ?? data.message ?? 'Could not load quiz.');
                return;
            }

            // Set up quiz
            questions   = data.Questions ?? [];
            secondsLeft = data.AllocatedSeconds ?? (data.Duration * 60);
            totalPages  = Math.ceil(questions.length / QUESTIONS_PER_PAGE);
            totalMarks  = questions.reduce((s, q) => s + parseFloat(q.Marks ?? 0), 0);

            document.getElementById('quizTitleBar').textContent  = data.Title ?? 'Quiz';
            document.getElementById('quizSubtitle').textContent  =
                questions.length + ' Questions · ' + Math.ceil(secondsLeft / 60) + ' min';
            document.getElementById('totalCount').textContent    = questions.length;
            document.getElementById('totalMarksDisplay').textContent = totalMarks;

            buildNavDots();
            renderPage(0);
            startTimer();

        } catch (err) {
            showError('Could not connect to server. Make sure you are logged in.');
        }
    }

    // ── TIMER ───────────────────────────────────────────────────
    function startTimer() {
        updateTimerDisplay();
        timerInterval = setInterval(() => {
            secondsLeft--;
            updateTimerDisplay();
            if (secondsLeft <= 0) {
                clearInterval(timerInterval);
                autoSubmit();
            }
        }, 1000);
    }

    function updateTimerDisplay() {
        const mins = Math.floor(Math.max(0, secondsLeft) / 60);
        const secs = Math.floor(Math.max(0, secondsLeft) % 60);
        const text = String(mins).padStart(2,'0') + ':' + String(secs).padStart(2,'0');
        const el   = document.getElementById('timerDisplay');
        el.textContent = text;

        el.className = 'timer-display';
        if (secondsLeft <= 60)  el.classList.add('danger');
        else if (secondsLeft <= 180) el.classList.add('warning');
    }

    // ── NAV DOTS ────────────────────────────────────────────────
    function buildNavDots() {
        const container = document.getElementById('qNavDots');
        container.innerHTML = '<span class="q-nav-label">Questions:</span>';
        questions.forEach((q, i) => {
            const dot = document.createElement('button');
            dot.className   = 'q-dot' + (i === 0 ? ' current' : '');
            dot.textContent = i + 1;
            dot.id          = 'dot_' + i;
            dot.title       = 'Question ' + (i + 1);
            dot.onclick     = () => jumpToQuestion(i);
            container.appendChild(dot);
        });
    }

    function updateNavDots() {
        const start = currentPage * QUESTIONS_PER_PAGE;
        const end   = Math.min(start + QUESTIONS_PER_PAGE, questions.length);
        questions.forEach((q, i) => {
            const dot = document.getElementById('dot_' + i);
            if (!dot) return;
            dot.className = 'q-dot';
            if (answers[q.QuestionID] !== undefined) dot.classList.add('answered');
            if (i >= start && i < end)               dot.classList.add('current');
        });
    }

    function jumpToQuestion(idx) {
        const page = Math.floor(idx / QUESTIONS_PER_PAGE);
        renderPage(page);
    }

    // ── RENDER PAGE ─────────────────────────────────────────────
    function renderPage(page) {
        currentPage = page;
        const start = page * QUESTIONS_PER_PAGE;
        const end   = Math.min(start + QUESTIONS_PER_PAGE, questions.length);
        const block = document.getElementById('questionsBlock');

        block.innerHTML = '';

        for (let i = start; i < end; i++) {
            const q    = questions[i];
            const card = buildQuestionCard(q, i);
            block.appendChild(card);
        }

        // Pagination
        const pagination = document.getElementById('pagination');
        pagination.style.display = totalPages > 1 ? 'flex' : 'none';

        document.getElementById('prevBtn').disabled = page === 0;
        document.getElementById('pageInfo').textContent =
            'Page ' + (page + 1) + ' of ' + totalPages +
            '  ·  Q' + (start + 1) + '–Q' + end;

        const nextBtn = document.getElementById('nextBtn');
        if (page >= totalPages - 1) {
            nextBtn.innerHTML = `Submit Quiz <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>`;
            nextBtn.onclick = () => showSubmitModal();
        } else {
            nextBtn.innerHTML = `Next <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14"><polyline points="9 18 15 12 9 6"/></svg>`;
            nextBtn.onclick = () => changePage(1);
        }

        updateNavDots();
        updateProgress();
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    function changePage(dir) {
        const newPage = currentPage + dir;
        if (newPage >= 0 && newPage < totalPages) renderPage(newPage);
    }

    // ── BUILD QUESTION CARD ──────────────────────────────────────
    function buildQuestionCard(q, idx) {
        const card = document.createElement('div');
        card.className = 'q-card';
        card.id        = 'qcard_' + q.QuestionID;

        const letters = ['A','B','C','D','E','F','G','H'];

        let optionsHTML = '';
        if (q.QuestionType === 'MCQ' && q.Options) {
            const opts = Array.isArray(q.Options) ? q.Options : JSON.parse(q.Options);
            optionsHTML = `<div class="options-list">` +
                opts.map((opt, oi) => `
                    <div class="option-item ${answers[q.QuestionID] === opt ? 'selected' : ''}"
                         onclick="selectOption(${q.QuestionID}, '${escapeStr(opt)}', this)"
                         data-value="${escapeStr(opt)}">
                        <div class="option-letter">${letters[oi] ?? (oi+1)}</div>
                        <div class="option-text">${opt}</div>
                        <svg class="option-check" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3">
                            <polyline points="20 6 9 17 4 12"/>
                        </svg>
                    </div>
                `).join('') +
                `</div>`;
        } else {
            optionsHTML = `
                <textarea class="open-textarea"
                          placeholder="Type your answer here..."
                          onchange="saveOpenAnswer(${q.QuestionID}, this.value)"
                          oninput="saveOpenAnswer(${q.QuestionID}, this.value)"
                >${answers[q.QuestionID] ?? ''}</textarea>
            `;
        }

        const isAnswered = answers[q.QuestionID] !== undefined && answers[q.QuestionID] !== '';

        card.innerHTML = `
            <div class="q-card-header">
                <span class="q-number">Question ${idx + 1} of ${questions.length}</span>
                <span class="q-marks-badge">${q.Marks} Mark${q.Marks != 1 ? 's' : ''}</span>
            </div>
            <div class="q-card-body">
                <div class="q-text">${q.QuestionText}</div>
                ${optionsHTML}
                <div class="q-answered-tag ${isAnswered ? 'show' : ''}" id="answered_${q.QuestionID}">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="20 6 9 17 4 12"/>
                    </svg>
                    Answer saved
                </div>
            </div>
        `;

        return card;
    }

    function escapeStr(str) {
        return String(str).replace(/'/g, "\\'").replace(/"/g, '&quot;');
    }

    // ── ANSWER HANDLING ─────────────────────────────────────────
    function selectOption(questionID, value, el) {
        if (submitted) return;

        // Deselect all options in this question
        const card = document.getElementById('qcard_' + questionID);
        card.querySelectorAll('.option-item').forEach(o => o.classList.remove('selected'));

        // Select clicked
        el.classList.add('selected');
        answers[questionID] = value;

        // Show answered tag
        const tag = document.getElementById('answered_' + questionID);
        if (tag) tag.classList.add('show');

        updateProgress();
        updateNavDots();
    }

    function saveOpenAnswer(questionID, value) {
        if (submitted) return;
        if (value.trim()) {
            answers[questionID] = value.trim();
        } else {
            delete answers[questionID];
        }
        const tag = document.getElementById('answered_' + questionID);
        if (tag) tag.classList.toggle('show', !!value.trim());
        updateProgress();
        updateNavDots();
    }

    // ── PROGRESS ────────────────────────────────────────────────
    function updateProgress() {
        const answered = Object.keys(answers).length;
        const total    = questions.length;
        const pct      = total > 0 ? (answered / total) * 100 : 0;

        document.getElementById('answeredCount').textContent = answered;
        document.getElementById('totalCount').textContent    = total;
        document.getElementById('progressText').textContent  = answered + ' / ' + total + ' answered';
        document.getElementById('answeredFill').style.width  = pct + '%';
        document.getElementById('progressBarFill').style.width = pct + '%';
    }

    // ── SUBMIT MODAL ────────────────────────────────────────────
    function showSubmitModal() {
        const answered   = Object.keys(answers).length;
        const unanswered = questions.length - answered;
        document.getElementById('modalAnswered').textContent   = answered;
        document.getElementById('modalUnanswered').textContent = unanswered;
        document.getElementById('submitModal').classList.add('show');
    }

    function hideSubmitModal() {
        document.getElementById('submitModal').classList.remove('show');
    }

    // ── SUBMIT ──────────────────────────────────────────────────
    async function submitQuiz(isAuto) {
        if (submitted) return;
        submitted = true;
        clearInterval(timerInterval);

        hideSubmitModal();

        if (isAuto) {
            document.getElementById('autoSubmitOverlay').classList.add('show');
        }

        const answersArray = Object.entries(answers).map(([qID, response]) => ({
            QuestionID:   parseInt(qID),
            ResponseText: response,
        }));

        const endpoint = isAuto ? '/web/quiz/auto-submit' : '/web/quiz/submit';

        try {
            const res  = await fetch(endpoint, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept':       'application/json',
                    'X-CSRF-TOKEN': CSRF,
                },
                body: JSON.stringify({
                    QuizID:  QUIZ_ID,
                    Answers: answersArray,
                }),
            });

            const data = await res.json();

            document.getElementById('autoSubmitOverlay').classList.remove('show');
            document.getElementById('submitModal').classList.remove('show');

            if (res.ok) {
                document.getElementById('scoreDisplay').textContent =
                    (data.Score ?? 0) + ' / ' + totalMarks;
                document.getElementById('successMsg').textContent = isAuto
                    ? 'Time expired. Your answers were saved automatically.'
                    : 'Your answers have been saved successfully.';
                document.getElementById('successModal').classList.add('show');
            } else {
                alert(data.error ?? data.message ?? 'Submission failed.');
                submitted = false;
            }
        } catch (err) {
            document.getElementById('autoSubmitOverlay').classList.remove('show');
            alert('Could not submit. Please check your connection.');
            submitted = false;
        }
    }

    function autoSubmit() {
        submitQuiz(true);
    }

    // ── ERROR STATE ─────────────────────────────────────────────
    function showError(msg) {
        document.getElementById('questionsBlock').innerHTML = `
            <div style="text-align:center;padding:60px 20px;">
                <div style="font-size:40px;margin-bottom:16px;">⚠️</div>
                <h2 style="color:var(--red);margin-bottom:8px;">Cannot Load Quiz</h2>
                <p style="color:var(--text-mid);font-size:14px;">${msg}</p>
                <a href="/dashboard" style="display:inline-block;margin-top:20px;color:var(--blue);font-weight:600;">
                    ← Back to Dashboard
                </a>
            </div>
        `;
    }
</script>
</body>
</html>