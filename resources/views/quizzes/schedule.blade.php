@extends('layouts.app')

@section('content')
<style>
    .content { padding: 28px; max-width: 1280px; padding-bottom: 110px; }

    .breadcrumb {
        font-size: 12px;
        color: var(--text-muted);
        margin-bottom: 8px;
        font-family: var(--font-body);
    }

    .breadcrumb a { color: var(--luna-mid); text-decoration: none; }
    .breadcrumb a:hover { text-decoration: underline; }

    .page-header {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        margin-bottom: 24px;
        flex-wrap: wrap;
        gap: 12px;
    }

    .page-title {
        font-size: 24px;
        font-weight: 800;
        color: var(--text-heading);
        letter-spacing: -0.3px;
        margin: 0;
        font-family: var(--font-display);
    }

    .header-actions { display: flex; gap: 10px; flex-wrap: wrap; }

    .btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        padding: 0.55rem 1rem;
        border-radius: var(--radius-md);
        font-family: var(--font-body);
        font-size: 13px;
        font-weight: 600;
        line-height: 1.2;
        cursor: pointer;
        border: 1px solid transparent;
        box-shadow: var(--shadow-soft);
        transition: transform 0.12s ease, box-shadow 0.12s ease, background-color 0.15s ease, border-color 0.15s ease;
    }

    .btn:active { transform: scale(0.98); }
    .btn svg { width: 14px; height: 14px; }

    .btn-outline-secondary {
        background: var(--surface-card);
        color: var(--text-body);
        border-color: var(--surface-border);
    }

    .btn-outline-secondary:hover {
        background: var(--surface-bg);
        color: var(--text-heading);
        border-color: var(--surface-border);
    }

    .btn-primary {
        background: var(--luna-mid);
        color: #fff;
        border-color: var(--luna-mid);
    }

    .btn-primary:hover {
        background: var(--luna-dark);
        border-color: var(--luna-dark);
    }

    .card {
        background: var(--surface-card);
        border: 1px solid var(--surface-border);
        border-top: 3px solid var(--luna-mid);
        border-radius: var(--radius-lg);
        box-shadow: var(--shadow-soft);
        margin-bottom: 16px;
    }

    .card-body { padding: 20px; }

    .field-label {
        font-size: 11px;
        font-weight: 700;
        color: var(--text-muted);
        letter-spacing: 0.08em;
        text-transform: uppercase;
        margin-bottom: 6px;
        display: block;
        font-family: var(--font-body);
    }

    .form-control, .form-select {
        width: 100%;
        padding: 0.5rem 0.8rem;
        border: 1px solid var(--surface-border);
        border-radius: var(--radius-md);
        font-size: 14px;
        color: var(--text-heading);
        background: #fff;
        font-family: var(--font-body);
        box-shadow: none;
        outline: none;
    }

    .form-control:focus, .form-select:focus {
        border-color: var(--luna-light);
        box-shadow: 0 0 0 0.2rem rgba(84, 172, 191, 0.2);
    }

    .field-row {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 14px;
        margin-bottom: 14px;
    }

    .field-row-3 {
        display: grid;
        grid-template-columns: 1fr 1fr 1fr;
        gap: 14px;
        margin-bottom: 14px;
    }

    .field-group { margin-bottom: 14px; }

    .questions-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        margin-bottom: 16px;
        flex-wrap: wrap;
    }

    .questions-count { display: flex; align-items: center; gap: 10px; }

    .q-count-badge {
        min-width: 48px;
        height: 30px;
        padding: 0 12px;
        border-radius: 999px;
        background: var(--luna-mid);
        color: #fff;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 12px;
        font-weight: 800;
        letter-spacing: 0.04em;
    }

    .questions-title {
        font-family: var(--font-display);
        font-size: 12px;
        font-weight: 800;
        color: var(--text-muted);
        letter-spacing: 0.12em;
        text-transform: uppercase;
    }

    .btn-add {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 0.55rem 1rem;
        border-radius: var(--radius-md);
        font-family: var(--font-body);
        font-size: 13px;
        font-weight: 600;
        border: 1px solid transparent;
        box-shadow: var(--shadow-medium);
        position: fixed;
        right: 24px;
        bottom: 24px;
        z-index: 999;
    }

    .btn-add svg { width: 13px; height: 13px; }

    .q-card {
        border: 1px solid var(--surface-border);
        border-radius: var(--radius-lg);
        margin-bottom: 12px;
        overflow: hidden;
        transition: border-color 0.15s ease, box-shadow 0.15s ease;
        background: var(--surface-card);
    }

    .q-card:focus-within {
        border-color: var(--luna-light);
        box-shadow: 0 0 0 0.2rem rgba(84, 172, 191, 0.12);
    }

    .q-card-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        padding: 12px 16px;
        background: var(--surface-bg);
        border-bottom: 1px solid var(--surface-border);
    }

    .q-card-title { font-size: 13px; font-weight: 700; color: var(--text-heading); }

    .q-type-badge {
        font-size: 10px;
        font-weight: 800;
        padding: 2px 7px;
        border-radius: 999px;
        letter-spacing: 0.5px;
    }

    .badge-mcq { background: rgba(84, 172, 191, 0.14); color: var(--luna-mid); }
    .badge-text { background: rgba(23, 91, 128, 0.12); color: var(--luna-dark); }

    .q-card-body { padding: 16px; }
    .q-row { display: grid; grid-template-columns: 1fr auto; gap: 12px; margin-bottom: 12px; align-items: end; }
    .q-row-type { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 12px; }

    .options-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; margin-top: 8px; }

    .option-row {
        display: flex;
        align-items: center;
        gap: 8px;
        background: var(--surface-bg);
        border: 1px solid var(--surface-border);
        border-radius: var(--radius-md);
        padding: 6px 10px;
        transition: border-color 0.15s ease, box-shadow 0.15s ease;
    }

    .option-row:focus-within {
        border-color: var(--luna-light);
        box-shadow: 0 0 0 0.2rem rgba(84, 172, 191, 0.12);
    }

    .option-row.correct-option {
        border-color: var(--accent-success);
        background: var(--accent-success-bg);
    }

    .option-radio {
        accent-color: var(--luna-mid);
        width: 14px;
        height: 14px;
        cursor: pointer;
        flex-shrink: 0;
    }

    .option-input {
        border: none;
        background: none;
        font-size: 13px;
        color: var(--text-heading);
        outline: none;
        width: 100%;
        font-family: var(--font-body);
    }

    .correct-label {
        font-size: 10px;
        font-weight: 700;
        color: var(--accent-success);
        white-space: nowrap;
        display: none;
        font-family: var(--font-body);
    }

    .option-row.correct-option .correct-label { display: block; }

    .q-card-header .btn-danger {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 4px 10px;
        border-radius: 999px;
        border: 1px solid rgba(217, 72, 61, 0.28);
        background: var(--surface-card);
        color: var(--accent-danger);
        font-family: var(--font-body);
        font-size: 11px;
        font-weight: 700;
        line-height: 1;
        box-shadow: none;
    }

    .q-card-header .btn-danger:hover {
        background: var(--accent-danger-bg);
    }

    .q-card-header .btn-danger svg {
        width: 12px;
        height: 12px;
    }

    .options-actions {
        display: flex;
        align-items: center;
        gap: 10px;
        margin-top: 8px;
        flex-wrap: wrap;
    }

    .btn-add-option {
        background: var(--surface-card);
        border: 1px dashed var(--surface-border);
        color: var(--text-muted);
        border-radius: var(--radius-md);
        padding: 5px 12px;
        font-size: 12px;
        font-family: var(--font-body);
        cursor: pointer;
        transition: border-color 0.15s ease, color 0.15s ease, background 0.15s ease;
    }

    .btn-add-option:hover {
        border-color: var(--luna-mid);
        color: var(--luna-mid);
        background: var(--surface-bg);
    }

    .correct-hint {
        font-size: 11px;
        color: var(--text-muted);
        display: inline-flex;
        align-items: center;
        gap: 4px;
        font-family: var(--font-body);
    }

    .correct-hint svg { width: 12px; height: 12px; color: var(--accent-success); }

    .open-text-area {
        width: 100%;
        padding: 8px 12px;
        border: 1px solid var(--surface-border);
        border-radius: var(--radius-md);
        font-size: 13px;
        color: var(--text-body);
        background: var(--surface-bg);
        resize: none;
        outline: none;
        font-family: var(--font-body);
    }

    .auto-note {
        background: var(--surface-card);
        border: 1px solid var(--surface-border);
        border-left: 4px solid var(--luna-mid);
        border-radius: var(--radius-lg);
        padding: 12px 14px;
        margin-top: 16px;
        box-shadow: var(--shadow-soft);
    }

    .auto-note-title {
        font-size: 11px;
        font-weight: 700;
        color: var(--luna-mid);
        letter-spacing: 0.5px;
        text-transform: uppercase;
        display: flex;
        align-items: center;
        gap: 5px;
        margin-bottom: 6px;
        font-family: var(--font-body);
    }

    .auto-note-title svg { width: 13px; height: 13px; }
    .auto-note-text { font-size: 12px; color: var(--text-body); line-height: 1.5; font-family: var(--font-body); }

    .alert {
        padding: 10px 14px;
        border-radius: var(--radius-md);
        font-size: 13px;
        margin-bottom: 16px;
        display: none;
        align-items: center;
        gap: 8px;
        box-shadow: var(--shadow-soft);
        font-family: var(--font-body);
    }

    .alert.show { display: flex; }
    .alert-success { background: var(--accent-success-bg); color: #1B6B3A; border: 1px solid #ABE5C8; }
    .alert-error { background: var(--accent-danger-bg); color: var(--accent-danger); border: 1px solid #FFBDAD; }
    .alert svg { width: 15px; height: 15px; flex-shrink: 0; }

    .empty-state {
        text-align: center;
        padding: 28px 20px;
        border: 1px dashed var(--surface-border);
        border-radius: var(--radius-lg);
        background: var(--surface-bg);
        color: var(--text-muted);
        font-size: 13px;
        font-family: var(--font-body);
    }
    .empty-state .btn-inline-add {
        background: none;
        border: none;
        padding: 0;
        margin: 0;
        color: inherit;
        font: inherit;
        font-weight: 700;
        cursor: pointer;
    }
    .empty-state .btn-inline-add:hover { text-decoration: underline; }

    @media (max-width: 768px) {
        .content { padding: 20px 16px 24px; }
        .field-row, .field-row-3, .options-grid, .q-row, .q-row-type { grid-template-columns: 1fr; }
        .header-actions { width: 100%; }
        .header-actions .btn { flex: 1 1 0; }
    }
</style>

<div class="content">
    @php($isEditOfLiveQuiz = $draft && $draft->Status !== 'draft')
    <div class="breadcrumb"><a href="{{ route('quiz.latest-results') }}">Quizzes</a> › {{ $isEditOfLiveQuiz ? 'Edit Quiz' : 'Configure Quiz' }}</div>

    <div class="page-header">
        <h1 class="page-title">{{ $isEditOfLiveQuiz ? 'EDIT QUIZ' : 'SCHEDULE QUIZ' }}</h1>
        <div class="header-actions">
            @unless($isEditOfLiveQuiz)
            <a href="{{ route('quiz.drafts') }}" class="btn btn-outline-secondary">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                MY DRAFTS
            </a>
            <button class="btn btn-outline-secondary" onclick="saveDraft()">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 21H5a2 2 0 01-2-2V5a2 2 0 012-2h11l5 5v11a2 2 0 01-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
                SAVE DRAFT
            </button>
            @endunless
            <button class="btn btn-primary" onclick="publishQuiz()">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
                {{ $isEditOfLiveQuiz ? 'SAVE CHANGES' : 'PUBLISH QUIZ' }}
            </button>
        </div>
    </div>

    <div class="alert alert-success" id="successMsg">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
        <span id="successText"></span>
    </div>
    <div class="alert alert-error" id="errorMsg">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
        <span id="errorText"></span>
    </div>

    <div class="card">
        <div class="card-body">
            <div class="field-group">
                <label class="field-label" for="Title">Quiz Title</label>
                <input type="text" id="Title" class="form-control" placeholder="e.g. Week 5 - Python Basics">
            </div>

            <div class="field-row">
                <div>
                    <label class="field-label" for="GroupSelector">Group Selector</label>
                    <select id="GroupSelector" class="form-select">
                        <option value="">-- Select Group --</option>
                        @foreach($groups as $group)
                            <option value="{{ $group->GroupID }}">{{ $group->GroupName }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="field-label" for="TargetCategory">Subject Category Selector</label>
                    <select id="TargetCategory" class="form-select">
                        <option value="">-- Select Category --</option>
                        <option value="Science">Science</option>
                        <option value="Math">Math</option>
                        <option value="English">English</option>
                        <option value="History">History</option>
                        <option value="All Students">All Students</option>
                    </select>
                </div>
            </div>

            <div class="field-row-3">
                <div>
                    <label class="field-label" for="DateInput">
                        <span style="display:flex;align-items:center;gap:4px;">
                            <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                            Date Input
                        </span>
                    </label>
                    <input type="date" id="DateInput" class="form-control">
                </div>
                <div>
                    <label class="field-label" for="TimeInput">
                        <span style="display:flex;align-items:center;gap:4px;">
                            <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                            Start Time Input
                        </span>
                    </label>
                    <input type="time" id="TimeInput" class="form-control">
                </div>
                <div>
                    <label class="field-label" for="Duration">
                        <span style="display:flex;align-items:center;gap:4px;">
                            <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                            Duration (Min)
                        </span>
                    </label>
                    <input type="number" id="Duration" class="form-control" placeholder="30" min="1">
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <div class="questions-header">
                <div class="questions-count">
                    <div class="q-count-badge" id="qCountBadge">0</div>
                    <span class="questions-title">Questions</span>
                </div>
                <button class="btn btn-primary btn-add" onclick="addQuestion()">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                    ADD QUESTION
                </button>
            </div>

            <div id="questionsList"></div>

            <div id="emptyState" class="empty-state">
                No questions yet. Click <button type="button" class="btn-inline-add" onclick="addQuestion()">ADD QUESTION</button> to get started.
            </div>
        </div>
    </div>

    <div class="auto-note">
        <div class="auto-note-title">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
            [SYSTEM] Auto-Submit Behaviour Note
        </div>
        <div class="auto-note-text">
            Quiz auto-submits when countdown reaches 0:00. Late joiners receive remaining time only. Ensure all instructions are clear before publishing.
        </div>
    </div>
</div>

<script>
    // ── QUESTION MANAGEMENT ──────────────────────────────────────
    let questionCount  = 0;
    let optionCounts   = {};
    let currentDraftId = @json($draft->QuizID ?? null);

    function updateCountBadge() {
        const cards = document.querySelectorAll('.q-card');
        document.getElementById('qCountBadge').textContent = cards.length;
        document.getElementById('emptyState').style.display = cards.length ? 'none' : 'block';
    }

    function addQuestion() {
        questionCount++;
        const n    = questionCount;
        const card = document.createElement('div');
        card.className = 'q-card';
        card.id        = 'qcard_' + n;

        card.innerHTML = `
            <div class="q-card-header">
                <div style="display:flex;align-items:center;gap:8px;">
                    <span class="q-card-title">Q${n}.</span>
                    <span class="q-type-badge badge-mcq" id="qtypebadge_${n}">MCQ</span>
                </div>
                <button class="btn-danger" type="button" title="Delete question" onclick="removeQuestion(${n})">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="3 6 5 6 21 6"/>
                        <path d="M19 6l-1 14H6L5 6"/>
                        <path d="M10 11v6M14 11v6"/>
                    </svg>
                    DELETE
                </button>
            </div>
            <div class="q-card-body">
                <div class="q-row-type">
                    <div>
                        <label class="field-label">Type</label>
                        <select class="form-select" id="qtype_${n}" onchange="handleTypeChange(${n})">
                            <option value="MCQ">MCQ</option>
                            <option value="Open">Open Text</option>
                        </select>
                    </div>
                    <div>
                        <label class="field-label">Marks</label>
                        <input type="number" class="form-control" id="qmarks_${n}" min="0" step="0.5" placeholder="5">
                    </div>
                </div>
                <div class="field-group">
                    <label class="field-label">Question Text</label>
                    <input type="text" class="form-control" id="qtext_${n}" placeholder="Type your question here...">
                </div>

                <div id="mcqSection_${n}">
                    <label class="field-label" style="margin-bottom:6px;">
                        Options
                        <span class="correct-hint" style="display:inline-flex;margin-left:8px;">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
                            Select radio to mark correct answer
                        </span>
                    </label>
                    <div class="options-grid" id="options_${n}">
                        <div class="option-row" id="optrow_${n}_0">
                            <input type="radio" name="correct_${n}" value="0" class="option-radio" onchange="markCorrect(${n}, 0)">
                            <input type="text" class="option-input" id="opt_${n}_0" placeholder="Option A">
                            <span class="correct-label">✓ Correct</span>
                        </div>
                        <div class="option-row" id="optrow_${n}_1">
                            <input type="radio" name="correct_${n}" value="1" class="option-radio" onchange="markCorrect(${n}, 1)">
                            <input type="text" class="option-input" id="opt_${n}_1" placeholder="Option B">
                            <span class="correct-label">✓ Correct</span>
                        </div>
                    </div>
                    <div class="options-actions">
                        <button class="btn-add-option" onclick="addOption(${n})">+ Add Option</button>
                        <span class="correct-hint">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
                            Click radio button next to the correct answer
                        </span>
                    </div>
                </div>

                <div id="openSection_${n}" style="display:none;">
                    <label class="field-label">Student Answer Area (preview)</label>
                    <textarea class="open-text-area" rows="3" placeholder="Students will type their answer here..." readonly></textarea>
                    <p style="font-size:11px;color:var(--text-muted);margin-top:4px;font-family:var(--font-body);">
                        Open text questions are not auto-graded.
                    </p>
                </div>
            </div>
        `;

        document.getElementById('questionsList').appendChild(card);
        optionCounts[n] = 2;
        updateCountBadge();
    }

    function removeQuestion(n) {
        const card = document.getElementById('qcard_' + n);
        if (card) card.remove();
        updateCountBadge();
    }

    function handleTypeChange(n) {
        const type  = document.getElementById('qtype_' + n).value;
        const badge = document.getElementById('qtypebadge_' + n);
        document.getElementById('mcqSection_' + n).style.display = type === 'MCQ' ? 'block' : 'none';
        document.getElementById('openSection_' + n).style.display = type === 'Open' ? 'block' : 'none';
        badge.textContent  = type === 'MCQ' ? 'MCQ' : 'TEXT';
        badge.className    = 'q-type-badge ' + (type === 'MCQ' ? 'badge-mcq' : 'badge-text');
    }

    function markCorrect(n, idx) {
        const allRows = document.querySelectorAll(`#options_${n} .option-row`);
        allRows.forEach(r => r.classList.remove('correct-option'));
        const row = document.getElementById(`optrow_${n}_${idx}`);
        if (row) row.classList.add('correct-option');
    }

    function addOption(n) {
        const idx = optionCounts[n]++;
        const letters = ['A','B','C','D','E','F'];
        const label   = letters[idx] || (idx + 1);

        const row = document.createElement('div');
        row.className = 'option-row';
        row.id        = `optrow_${n}_${idx}`;
        row.innerHTML = `
            <input type="radio" name="correct_${n}" value="${idx}" class="option-radio" onchange="markCorrect(${n}, ${idx})">
            <input type="text" class="option-input" id="opt_${n}_${idx}" placeholder="Option ${label}">
            <span class="correct-label">✓ Correct</span>
        `;
        document.getElementById('options_' + n).appendChild(row);
    }

    function populateDraft(draft) {
        document.getElementById('Title').value = draft.Title === 'Untitled draft' ? '' : (draft.Title || '');
        if (draft.GroupID) document.getElementById('GroupSelector').value = draft.GroupID;
        if (draft.TargetCategory) document.getElementById('TargetCategory').value = draft.TargetCategory;
        if (draft.StartTime) {
            const dt = draft.StartTime.replace(' ', 'T');
            const [datePart, timePart] = dt.split('T');
            document.getElementById('DateInput').value = datePart || '';
            document.getElementById('TimeInput').value = (timePart || '').slice(0, 5);
        }
        if (draft.Duration) document.getElementById('Duration').value = draft.Duration;

        (draft.questions || []).forEach(q => {
            addQuestion();
            const n = questionCount;
            document.getElementById('qtype_' + n).value = q.QuestionType || 'MCQ';
            handleTypeChange(n);
            document.getElementById('qtext_' + n).value = q.QuestionText || '';
            document.getElementById('qmarks_' + n).value = q.Marks || '';

            if (q.QuestionType === 'MCQ' && Array.isArray(q.Options)) {
                q.Options.forEach((opt, idx) => {
                    if (idx >= 2) addOption(n);
                    const input = document.getElementById('opt_' + n + '_' + idx);
                    if (input) input.value = opt;
                    if (opt === q.CorrectAnswer) markCorrect(n, idx);
                    const radio = document.querySelector(`input[name="correct_${n}"][value="${idx}"]`);
                    if (radio && opt === q.CorrectAnswer) radio.checked = true;
                });
            }
        });

        currentDraftId = draft.QuizID;
    }

    function collectQuestions() {
        const questions = [];
        document.querySelectorAll('.q-card').forEach(card => {
            const n    = card.id.replace('qcard_', '');
            const type = document.getElementById('qtype_' + n).value;
            const text = (document.getElementById('qtext_' + n).value || '').trim();
            const marks = parseFloat(document.getElementById('qmarks_' + n).value) || 0;

            if (!text) return;

            let options = null;
            let correctAnswer = null;

            if (type === 'MCQ') {
                options = [];
                document.querySelectorAll(`#options_${n} .option-input`).forEach(i => {
                    if (i.value.trim()) options.push(i.value.trim());
                });

                const selected = document.querySelector(`input[name="correct_${n}"]:checked`);
                if (selected !== null) {
                    const idx = parseInt(selected.value);
                    if (options[idx]) correctAnswer = options[idx];
                }
            }

            questions.push({
                QuestionText: text,
                QuestionType: type,
                Options: options,
                CorrectAnswer: correctAnswer,
                Marks: marks,
            });
        });
        return questions;
    }

    function getStartTime() {
        const date = document.getElementById('DateInput').value;
        const time = document.getElementById('TimeInput').value;
        if (!date || !time) return null;
        return date + ' ' + time + ':00';
    }

    async function publishQuiz() {
        hideAlerts();

        const title     = document.getElementById('Title').value.trim();
        const startTime = getStartTime();
        const duration  = parseInt(document.getElementById('Duration').value);
        const category  = document.getElementById('TargetCategory').value;
const groupId    = document.getElementById('GroupSelector').value;
const questions = collectQuestions();

if (!title)     return showError('Please enter a quiz title.');
if (!startTime) return showError('Please select a date and start time.');
if (!duration)  return showError('Please enter a duration in minutes.');
if (!category)  return showError('Please select a target category.');
if (!groupId)   return showError('Please select a group.');
        if (questions.length === 0) return showError('Please add at least one question.');

        try {
            const response = await fetch('/quiz/schedule-submit', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                },
                body: JSON.stringify({
    QuizID: currentDraftId,
    Title: title,
    StartTime: startTime,
    Duration: duration,
    TargetCategory: category,
    GroupID: groupId,
    Questions: questions,
}),
            });

            const data = await response.json();

            if (response.ok) {
                showSuccess('Quiz published! ' + (data.students_notified ?? 0) + ' students notified. Quiz ID: ' + (data.QuizID ?? '—'));
                currentDraftId = null;
                resetForm();
            } else {
                showError(data.message ?? data.error ?? 'Something went wrong.');
            }
        } catch (err) {
            showError('Could not connect to the server. Is Laravel running?');
        }
    }

    async function saveDraft() {
        hideAlerts();

        try {
            const response = await fetch('{{ route("quiz.draft.save") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                },
                body: JSON.stringify({
                    QuizID: currentDraftId,
                    Title: document.getElementById('Title').value.trim(),
                    StartTime: getStartTime(),
                    Duration: parseInt(document.getElementById('Duration').value) || null,
                    TargetCategory: document.getElementById('TargetCategory').value || null,
                    GroupID: document.getElementById('GroupSelector').value || null,
                    Questions: collectQuestions(),
                }),
            });

            const data = await response.json();

            if (response.ok) {
                currentDraftId = data.QuizID;
                showSuccess('Draft saved — find it any time under "My Drafts".');
            } else {
                showError(data.message ?? 'Could not save draft.');
            }
        } catch (err) {
            showError('Could not connect to the server. Is Laravel running?');
        }
    }

    function resetForm() {
        document.getElementById('Title').value = '';
        document.getElementById('DateInput').value = '';
        document.getElementById('TimeInput').value = '';
        document.getElementById('Duration').value = '';
        document.getElementById('TargetCategory').value = '';
        document.getElementById('GroupSelector').value = '';
        document.getElementById('questionsList').innerHTML = '';
        questionCount = 0;
        optionCounts  = {};
        updateCountBadge();
    }

    function showSuccess(msg) {
        const el = document.getElementById('successMsg');
        document.getElementById('successText').textContent = msg;
        el.classList.add('show');
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    function showError(msg) {
        const el = document.getElementById('errorMsg');
        document.getElementById('errorText').textContent = msg;
        el.classList.add('show');
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    function hideAlerts() {
        document.getElementById('successMsg').classList.remove('show');
        document.getElementById('errorMsg').classList.remove('show');
    }

    @if($draft)
        populateDraft(@json($draft));
    @endif
</script>
@endsection
