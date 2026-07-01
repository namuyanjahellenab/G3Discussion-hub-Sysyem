<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Configure Quiz | Discussion Hub</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Segoe UI', Arial, sans-serif; }

        :root {
            --blue:        #0052CC;
            --blue-dark:   #003D99;
            --blue-light:  #E8F0FF;
            --blue-mid:    #DEEBFF;
            --sidebar-w:   220px;
            --text:        #172B4D;
            --text-mid:    #5E6C84;
            --text-light:  #8993A4;
            --border:      #DFE1E6;
            --bg:          #F4F5F7;
            --white:       #FFFFFF;
            --red:         #DE350B;
            --green:       #36B37E;
            --green-bg:    #E3FCEF;
            --yellow:      #FFAB00;
            --info-bg:     #DEEBFF;
            --radius:      6px;
            --shadow:      0 1px 3px rgba(0,0,0,0.10);
        }

        body { background: var(--bg); color: var(--text); display: flex; min-height: 100vh; }

        /* ── SIDEBAR ── */
        #sidebar {
            width: var(--sidebar-w);
            background: var(--white);
            border-right: 1px solid var(--border);
            display: flex;
            flex-direction: column;
            position: fixed;
            top: 0; left: 0; bottom: 0;
            z-index: 200;
            transition: transform 0.25s ease;
        }
        #sidebar.hidden { transform: translateX(-100%); }

        .sidebar-logo {
            display: flex; align-items: center; gap: 10px;
            padding: 18px 20px 16px;
            border-bottom: 1px solid var(--border);
        }
        .logo-icon {
            width: 32px; height: 32px; background: var(--blue);
            border-radius: 6px; display: flex; align-items: center;
            justify-content: center; flex-shrink: 0;
        }
        .logo-icon svg { width: 18px; height: 18px; fill: white; }
        .logo-text { font-size: 13px; font-weight: 700; color: var(--blue); letter-spacing: 0.3px; }

        .sidebar-nav { flex: 1; padding: 12px 0; overflow-y: auto; }
        .nav-item {
            display: flex; align-items: center; gap: 10px;
            padding: 9px 20px; font-size: 13.5px; color: var(--text-mid);
            cursor: pointer; text-decoration: none;
            transition: background 0.15s, color 0.15s;
            border-left: 3px solid transparent;
        }
        .nav-item:hover { background: var(--bg); color: var(--text); }
        .nav-item.active {
            background: var(--blue-light); color: var(--blue);
            border-left-color: var(--blue); font-weight: 600;
        }
        .nav-item svg { width: 16px; height: 16px; flex-shrink: 0; }

        .sidebar-footer {
            padding: 14px 20px;
            border-top: 1px solid var(--border);
            display: flex; align-items: center; gap: 10px;
        }
        .avatar {
            width: 32px; height: 32px; border-radius: 50%;
            background: var(--blue); color: white;
            display: flex; align-items: center; justify-content: center;
            font-size: 12px; font-weight: 700; flex-shrink: 0;
        }
        .avatar-info { min-width: 0; }
        .avatar-name  { font-size: 12.5px; font-weight: 600; color: var(--text); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .avatar-role  { font-size: 11px; color: var(--text-light); }

        /* ── TOPBAR ── */
        #topbar {
            position: fixed; top: 0; left: var(--sidebar-w); right: 0;
            height: 52px; background: var(--white);
            border-bottom: 1px solid var(--border);
            display: flex; align-items: center; padding: 0 20px;
            gap: 12px; z-index: 100;
            transition: left 0.25s ease;
        }
        #topbar.full { left: 0; }

        #toggle-btn {
            background: none; border: none; cursor: pointer;
            padding: 6px; border-radius: var(--radius);
            display: flex; align-items: center; color: var(--text-mid);
        }
        #toggle-btn:hover { background: var(--bg); }
        #toggle-btn svg { width: 18px; height: 18px; }

        .search-bar {
            flex: 1; max-width: 360px;
            display: flex; align-items: center; gap: 8px;
            background: var(--bg); border: 1px solid var(--border);
            border-radius: var(--radius); padding: 6px 12px;
        }
        .search-bar svg { width: 14px; height: 14px; color: var(--text-light); flex-shrink: 0; }
        .search-bar input {
            border: none; background: none; font-size: 13px;
            color: var(--text); outline: none; width: 100%;
        }
        .search-bar input::placeholder { color: var(--text-light); }

        .topbar-right { margin-left: auto; display: flex; align-items: center; gap: 10px; }
        .icon-btn {
            background: none; border: none; cursor: pointer;
            padding: 6px; border-radius: var(--radius);
            display: flex; align-items: center; color: var(--text-mid);
        }
        .icon-btn:hover { background: var(--bg); }
        .icon-btn svg { width: 18px; height: 18px; }

        .mode-badge {
            background: var(--blue); color: white;
            font-size: 11px; font-weight: 700; padding: 4px 10px;
            border-radius: 20px; letter-spacing: 0.5px;
            display: flex; align-items: center; gap: 5px;
        }
        .mode-badge svg { width: 12px; height: 12px; }

        /* ── MAIN CONTENT ── */
        #main {
            margin-left: var(--sidebar-w);
            margin-top: 52px;
            padding: 28px 32px;
            flex: 1;
            transition: margin-left 0.25s ease;
            min-width: 0;
        }
        #main.full { margin-left: 0; }

        /* breadcrumb */
        .breadcrumb { font-size: 12px; color: var(--text-light); margin-bottom: 8px; }
        .breadcrumb a { color: var(--blue); text-decoration: none; }
        .breadcrumb a:hover { text-decoration: underline; }

        .page-header {
            display: flex; align-items: center;
            justify-content: space-between; margin-bottom: 24px; flex-wrap: wrap; gap: 12px;
        }
        .page-title { font-size: 22px; font-weight: 700; color: var(--text); letter-spacing: -0.3px; }

        .header-actions { display: flex; gap: 10px; }
        .btn {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 8px 16px; border-radius: var(--radius);
            font-size: 13px; font-weight: 600; cursor: pointer;
            border: none; transition: background 0.15s, box-shadow 0.15s;
        }
        .btn svg { width: 14px; height: 14px; }
        .btn-outline {
            background: var(--white); color: var(--blue);
            border: 1.5px solid var(--blue);
        }
        .btn-outline:hover { background: var(--blue-light); }
        .btn-primary { background: var(--blue); color: white; }
        .btn-primary:hover { background: var(--blue-dark); }
        .btn-danger  { background: none; color: var(--red);
                       border: none; font-size: 12px; font-weight: 600;
                       cursor: pointer; padding: 4px 0;
                       display: flex; align-items: center; gap: 4px; }
        .btn-danger svg { width: 13px; height: 13px; }

        /* ── GRID LAYOUT ── */
        .layout { display: grid; grid-template-columns: 1fr 280px; gap: 20px; align-items: start; }
        .layout { display: grid; grid-template-columns: 1fr; gap: 20px; align-items: start; }
        @media (max-width: 900px) { .layout { grid-template-columns: 1fr; } }

        /* ── CARDS ── */
        .card {
            background: var(--white); border: 1px solid var(--border);
            border-radius: var(--radius); padding: 20px;
            box-shadow: var(--shadow); margin-bottom: 16px;
        }

        /* ── FORM ELEMENTS ── */
        .field-label {
            font-size: 10.5px; font-weight: 700; color: var(--text-light);
            letter-spacing: 0.8px; text-transform: uppercase; margin-bottom: 6px;
            display: block;
        }
        .field-input {
            width: 100%; padding: 8px 12px;
            border: 1.5px solid var(--border); border-radius: var(--radius);
            font-size: 14px; color: var(--text); background: var(--white);
            transition: border-color 0.15s;
            outline: none;
        }
        .field-input:focus { border-color: var(--blue); box-shadow: 0 0 0 2px rgba(0,82,204,0.12); }

        .field-row { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-bottom: 14px; }
        .field-row-3 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 14px; margin-bottom: 14px; }
        .field-group { margin-bottom: 14px; }

        .input-icon-wrap { position: relative; }
        .input-icon-wrap .icon {
            position: absolute; left: 10px; top: 50%; transform: translateY(-50%);
            color: var(--text-light); pointer-events: none;
        }
        .input-icon-wrap .icon svg { width: 13px; height: 13px; }
        .input-icon-wrap .field-input { padding-left: 30px; }

        /* ── QUESTIONS ── */
        .questions-header {
            display: flex; align-items: center;
            justify-content: space-between; margin-bottom: 16px;
        }
        .questions-count {
            display: flex; align-items: center; gap: 10px;
        }
        .q-count-badge {
            width: 24px; height: 24px; background: var(--blue);
            color: white; border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-size: 12px; font-weight: 700;
        }
        .questions-title { font-size: 14px; font-weight: 700; color: var(--text); letter-spacing: 0.3px; text-transform: uppercase; }

        .btn-add {
            display: flex; align-items: center; gap: 6px;
            background: var(--blue); color: white;
            border: none; border-radius: var(--radius);
            padding: 7px 14px; font-size: 12.5px; font-weight: 600;
            cursor: pointer; transition: background 0.15s;
        }
        .btn-add:hover { background: var(--blue-dark); }
        .btn-add svg { width: 13px; height: 13px; }
        .btn-add.floating {
            position: fixed;
            bottom: 24px;
            right: 24px;
            z-index: 999;
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
        }

        /* Question card */
        .q-card {
            border: 1.5px solid var(--border); border-radius: var(--radius);
            margin-bottom: 12px; overflow: hidden;
            transition: border-color 0.15s;
        }
        .q-card:focus-within { border-color: var(--blue); }

        .q-card-header {
            display: flex; align-items: center; justify-content: space-between;
            padding: 10px 14px; background: var(--bg);
            border-bottom: 1px solid var(--border);
        }
        .q-card-title { font-size: 13px; font-weight: 700; color: var(--text); }
        .q-type-badge {
            font-size: 10px; font-weight: 700; padding: 2px 7px;
            border-radius: 3px; letter-spacing: 0.5px;
        }
        .badge-mcq  { background: #E3F2FD; color: #1565C0; }
        .badge-text { background: #F3E5F5; color: #6A1B9A; }

        .q-card-body { padding: 14px; }

        .q-row { display: grid; grid-template-columns: 1fr auto; gap: 12px; margin-bottom: 12px; align-items: end; }
        .q-row-type { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 12px; }

        /* Options grid */
        .options-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; margin-top: 8px; }
        .option-row {
            display: flex; align-items: center; gap: 8px;
            background: var(--bg); border: 1.5px solid var(--border);
            border-radius: var(--radius); padding: 6px 10px;
            transition: border-color 0.15s;
        }
        .option-row:focus-within { border-color: var(--blue); }
        .option-row.correct-option { border-color: var(--green); background: var(--green-bg); }

        .option-radio { accent-color: var(--blue); width: 14px; height: 14px; cursor: pointer; flex-shrink: 0; }
        .option-input {
            border: none; background: none; font-size: 13px;
            color: var(--text); outline: none; width: 100%;
        }
        .correct-label {
            font-size: 10px; font-weight: 700; color: var(--green);
            white-space: nowrap; display: none;
        }
        .option-row.correct-option .correct-label { display: block; }

        .options-actions {
            display: flex; align-items: center; gap: 10px;
            margin-top: 8px;
        }
        .btn-add-option {
            background: none; border: 1.5px dashed var(--border);
            color: var(--text-mid); border-radius: var(--radius);
            padding: 5px 12px; font-size: 12px; cursor: pointer;
            transition: border-color 0.15s, color 0.15s;
        }
        .btn-add-option:hover { border-color: var(--blue); color: var(--blue); }

        .correct-hint {
            font-size: 11px; color: var(--text-light);
            display: flex; align-items: center; gap: 4px;
        }
        .correct-hint svg { width: 12px; height: 12px; color: var(--green); }

        /* Open text area */
        .open-text-area {
            width: 100%; padding: 8px 12px; border: 1.5px solid var(--border);
            border-radius: var(--radius); font-size: 13px; color: var(--text-light);
            background: var(--bg); resize: none; outline: none; font-family: inherit;
        }

        /* ── RIGHT PANEL ── */
        .panel-title {
            font-size: 11px; font-weight: 700; color: var(--blue);
            letter-spacing: 0.8px; text-transform: uppercase;
            display: flex; align-items: center; gap: 6px; margin-bottom: 14px;
        }
        .panel-title svg { width: 14px; height: 14px; }

        .panel-desc { font-size: 12.5px; color: var(--text-mid); line-height: 1.5; margin-bottom: 16px; }

        .toggle-row {
            display: flex; align-items: center; justify-content: space-between;
            padding: 10px 0; border-bottom: 1px solid var(--border);
        }
        .toggle-row:last-child { border-bottom: none; }
        .toggle-label { font-size: 13px; color: var(--text); font-weight: 500; }

        .toggle-switch {
            width: 40px; height: 22px; background: var(--border);
            border-radius: 11px; position: relative; cursor: pointer;
            transition: background 0.2s; border: none; flex-shrink: 0;
        }
        .toggle-switch.on { background: var(--blue); }
        .toggle-switch::after {
            content: ''; position: absolute;
            width: 16px; height: 16px; background: white;
            border-radius: 50%; top: 3px; left: 3px;
            transition: left 0.2s; box-shadow: 0 1px 2px rgba(0,0,0,0.2);
        }
        .toggle-switch.on::after { left: 21px; }

        /* auto-submit note */
        .auto-note {
            background: var(--info-bg); border: 1px solid #B3D0FF;
            border-left: 4px solid var(--blue);
            border-radius: var(--radius); padding: 12px 14px;
            margin-top: 16px;
        }
        .auto-note-title {
            font-size: 11px; font-weight: 700; color: var(--blue);
            letter-spacing: 0.5px; text-transform: uppercase;
            display: flex; align-items: center; gap: 5px; margin-bottom: 6px;
        }
        .auto-note-title svg { width: 13px; height: 13px; }
        .auto-note-text { font-size: 12px; color: var(--text-mid); line-height: 1.5; }

        /* ── ALERTS ── */
        .alert {
            padding: 10px 14px; border-radius: var(--radius);
            font-size: 13px; margin-bottom: 16px; display: none;
            align-items: center; gap: 8px;
        }
        .alert.show { display: flex; }
        .alert-success { background: var(--green-bg); color: #1B6B3A; border: 1px solid #ABE5C8; }
        .alert-error   { background: #FFEBE6; color: var(--red); border: 1px solid #FFBDAD; }
        .alert svg { width: 15px; height: 15px; flex-shrink: 0; }

        /* ── SIDEBAR OVERLAY (mobile) ── */
        #overlay {
            display: none; position: fixed; inset: 0;
            background: rgba(0,0,0,0.35); z-index: 150;
        }
        #overlay.show { display: block; }

        /* ── RESPONSIVE ── */
        @media (max-width: 768px) {
            #sidebar { transform: translateX(-100%); }
            #sidebar.show-mobile { transform: translateX(0); }
            #topbar { left: 0 !important; }
            #main   { margin-left: 0 !important; }
            .field-row, .field-row-3, .options-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

<!-- Sidebar overlay for mobile -->
<div id="overlay" onclick="closeSidebar()"></div>

<!-- ── SIDEBAR ── -->
<aside id="sidebar">
    <div class="sidebar-logo">
        <div class="logo-icon">
            <svg viewBox="0 0 24 24"><path d="M20 2H4c-1.1 0-2 .9-2 2v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2z"/></svg>
        </div>
        <span class="logo-text">DISCUSSION HUB</span>
    </div>

    <nav class="sidebar-nav">
        <a href="/dashboard" class="nav-item">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg>
            Dashboard
        </a>
        <a href="/forum" class="nav-item">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/></svg>
            Forum
        </a>
        <a href="#" class="nav-item">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20h9M16.5 3.5a2.121 2.121 0 013 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>
            Marks
        </a>
        <a href="/quiz/schedule" class="nav-item active">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
            Quizzes
        </a>
        <a href="#" class="nav-item">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.07 4.93a10 10 0 010 14.14M4.93 4.93a10 10 0 000 14.14"/></svg>
            Settings
        </a>
    </nav>

    <div class="sidebar-footer">
        <div class="avatar">L</div>
        <div class="avatar-info">
            <div class="avatar-name">{{ auth()->user()->UserName ?? 'Lecturer' }}</div>
            <div class="avatar-role">{{ auth()->user()->Role ?? 'Lecturer' }}</div>
        </div>
    </div>
</aside>

<!-- ── TOPBAR ── -->
<header id="topbar">
    <button id="toggle-btn" onclick="toggleSidebar()" title="Toggle sidebar">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <line x1="3" y1="6"  x2="21" y2="6"/>
            <line x1="3" y1="12" x2="21" y2="12"/>
            <line x1="3" y1="18" x2="21" y2="18"/>
        </svg>
    </button>

    <div class="search-bar">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
        </svg>
        <input type="text" placeholder="Search forum or quizzes...">
    </div>

    <div class="topbar-right">
        <button class="icon-btn" title="Notifications">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M18 8A6 6 0 006 8c0 7-3 9-3 9h18s-3-2-3-9M13.73 21a2 2 0 01-3.46 0"/>
            </svg>
        </button>
        <button class="icon-btn" title="Help">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="10"/>
                <path d="M9.09 9a3 3 0 015.83 1c0 2-3 3-3 3M12 17h.01"/>
            </svg>
        </button>
        <div class="mode-badge">
            <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-1 14H9V8h2v8zm4 0h-2V8h2v8z"/></svg>
            LECTURER MODE
        </div>
    </div>
</header>

<!-- ── MAIN ── -->
<main id="main">
    <div class="breadcrumb"><a href="#">Quizzes</a> › Configure Quiz</div>

    <div class="page-header">
        <h1 class="page-title">SCHEDULE QUIZ</h1>
        <div class="header-actions">
            <button class="btn btn-outline" onclick="saveDraft()">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 21H5a2 2 0 01-2-2V5a2 2 0 012-2h11l5 5v11a2 2 0 01-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
                SAVE DRAFT
            </button>
            <button class="btn btn-primary" onclick="publishQuiz()">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
                PUBLISH QUIZ
            </button>
        </div>
    </div>

    <!-- Alerts -->
    <div class="alert alert-success" id="successMsg">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
        <span id="successText"></span>
    </div>
    <div class="alert alert-error" id="errorMsg">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
        <span id="errorText"></span>
    </div>

    <div class="layout">
        <!-- LEFT COLUMN -->
        <div>
            <!-- Basic Info -->
            <div class="card">
                <div class="field-group">
                    <label class="field-label">Quiz Title Input</label>
                    <input type="text" id="Title" class="field-input" placeholder="e.g. Week 5 - Python Basics">
                </div>
                <div class="field-row">
                    <div>
                        <label class="field-label">Group Selector</label>
                        <select id="GroupSelector" class="field-input">
                            <option value="">-- Select Group --</option>
                            <option>Group A</option>
                            <option>Group B</option>
                            <option>Group C</option>
                        </select>
                    </div>
                    <div>
                        <label class="field-label">Subject Category Selector</label>
                        <select id="TargetCategory" class="field-input">
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
                        <label class="field-label">
                            <span style="display:flex;align-items:center;gap:4px;">
                                <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                                Date Input
                            </span>
                        </label>
                        <input type="date" id="DateInput" class="field-input">
                    </div>
                    <div>
                        <label class="field-label">
                            <span style="display:flex;align-items:center;gap:4px;">
                                <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                                Start Time Input
                            </span>
                        </label>
                        <input type="time" id="TimeInput" class="field-input">
                    </div>
                    <div>
                        <label class="field-label">
                            <span style="display:flex;align-items:center;gap:4px;">
                                <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                                Duration (Min)
                            </span>
                        </label>
                        <input type="number" id="Duration" class="field-input" placeholder="30" min="1">
                    </div>
                </div>
            </div>

            <!-- Questions -->
            <div class="card">
                <div class="questions-header">
                    <div class="questions-count">
                        <div class="q-count-badge" id="qCountBadge">0</div>
                        <span class="questions-title">Questions</span>
                    </div>
                    <button class="btn-add floating" onclick="addQuestion()">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                        ADD QUESTION
                    </button>
                </div>

                <div id="questionsList"></div>

                <div id="emptyState" style="text-align:center;padding:30px 0;color:var(--text-light);font-size:13px;">
                    No questions yet. Click <strong>ADD QUESTION</strong> to get started.
                </div>
            </div>

            <!-- Auto-submit note -->
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

        <!-- RIGHT COLUMN -->
       
    
</main>

<script>
    // ── SIDEBAR TOGGLE ──────────────────────────────────────────
    let sidebarOpen = true;

    function toggleSidebar() {
        const sidebar  = document.getElementById('sidebar');
        const topbar   = document.getElementById('topbar');
        const main     = document.getElementById('main');
        const overlay  = document.getElementById('overlay');
        const isMobile = window.innerWidth <= 768;

        if (isMobile) {
            sidebar.classList.toggle('show-mobile');
            overlay.classList.toggle('show', sidebar.classList.contains('show-mobile'));
        } else {
            sidebarOpen = !sidebarOpen;
            sidebar.classList.toggle('hidden', !sidebarOpen);
            topbar.classList.toggle('full', !sidebarOpen);
            main.classList.toggle('full', !sidebarOpen);
        }
    }

    function closeSidebar() {
        document.getElementById('sidebar').classList.remove('show-mobile');
        document.getElementById('overlay').classList.remove('show');
    }

    window.addEventListener('resize', () => {
        if (window.innerWidth > 768) {
            document.getElementById('overlay').classList.remove('show');
            document.getElementById('sidebar').classList.remove('show-mobile');
        }
    });

    // ── QUESTION MANAGEMENT ──────────────────────────────────────
    let questionCount  = 0;
    let optionCounts   = {};

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
                <button class="btn-danger" onclick="removeQuestion(${n})">
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
                        <select class="field-input" id="qtype_${n}" onchange="handleTypeChange(${n})">
                            <option value="MCQ">MCQ</option>
                            <option value="Open">Open Text</option>
                        </select>
                    </div>
                    <div>
                        <label class="field-label">Marks</label>
                        <input type="number" class="field-input" id="qmarks_${n}" min="0" step="0.5" placeholder="5">
                    </div>
                </div>
                <div class="field-group">
                    <label class="field-label">Question Text</label>
                    <input type="text" class="field-input" id="qtext_${n}" placeholder="Type your question here...">
                </div>

                <!-- MCQ Options -->
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
                            <input type="radio" name="correct_${n}" value="0"
                                   class="option-radio"
                                   onchange="markCorrect(${n}, 0)">
                            <input type="text" class="option-input"
                                   id="opt_${n}_0" placeholder="Option A">
                            <span class="correct-label">✓ Correct</span>
                        </div>
                        <div class="option-row" id="optrow_${n}_1">
                            <input type="radio" name="correct_${n}" value="1"
                                   class="option-radio"
                                   onchange="markCorrect(${n}, 1)">
                            <input type="text" class="option-input"
                                   id="opt_${n}_1" placeholder="Option B">
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

                <!-- Open text -->
                <div id="openSection_${n}" style="display:none;">
                    <label class="field-label">Student Answer Area (preview)</label>
                    <textarea class="open-text-area" rows="3"
                              placeholder="Students will type their answer here..."
                              readonly></textarea>
                    <p style="font-size:11px;color:var(--text-light);margin-top:4px;">
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
        // Remove correct styling from all options in this question
        const allRows = document.querySelectorAll(`#options_${n} .option-row`);
        allRows.forEach(r => r.classList.remove('correct-option'));
        // Add to selected
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
            <input type="radio" name="correct_${n}" value="${idx}"
                   class="option-radio" onchange="markCorrect(${n}, ${idx})">
            <input type="text" class="option-input"
                   id="opt_${n}_${idx}" placeholder="Option ${label}">
            <span class="correct-label">✓ Correct</span>
        `;
        document.getElementById('options_' + n).appendChild(row);
    }

    // ── COLLECT DATA ────────────────────────────────────────────
    function collectQuestions() {
        const questions = [];
        document.querySelectorAll('.q-card').forEach(card => {
            const n    = card.id.replace('qcard_', '');
            const type = document.getElementById('qtype_' + n).value;
            const text = (document.getElementById('qtext_' + n).value || '').trim();
            const marks = parseFloat(document.getElementById('qmarks_' + n).value) || 0;

            if (!text) return;

            let options       = null;
            let correctAnswer = null;

            if (type === 'MCQ') {
                options = [];
                document.querySelectorAll(`#options_${n} .option-input`)
                    .forEach(i => { if (i.value.trim()) options.push(i.value.trim()); });

                const selected = document.querySelector(`input[name="correct_${n}"]:checked`);
                if (selected !== null) {
                    const idx = parseInt(selected.value);
                    if (options[idx]) correctAnswer = options[idx];
                }
            }

            questions.push({
                QuestionText:  text,
                QuestionType:  type,
                Options:       options,
                CorrectAnswer: correctAnswer,
                Marks:         marks,
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

    // ── PUBLISH ─────────────────────────────────────────────────
    async function publishQuiz() {
        hideAlerts();

        const title     = document.getElementById('Title').value.trim();
        const startTime = getStartTime();
        const duration  = parseInt(document.getElementById('Duration').value);
        const category  = document.getElementById('TargetCategory').value;
        const questions = collectQuestions();

        if (!title)     return showError('Please enter a quiz title.');
        if (!startTime) return showError('Please select a date and start time.');
        if (!duration)  return showError('Please enter a duration in minutes.');
        if (!category)  return showError('Please select a target category.');
        if (questions.length === 0) return showError('Please add at least one question.');

        try {
            const response = await fetch('/quiz/schedule-submit', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept':       'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')
                                            .getAttribute('content'),
                },
                body: JSON.stringify({
                    Title:          title,
                    StartTime:      startTime,
                    Duration:       duration,
                    TargetCategory: category,
                    Questions:      questions,
                }),
            });

            const data = await response.json();

            if (response.ok) {
                showSuccess(
                    'Quiz published! ' +
                    (data.students_notified ?? 0) +
                    ' students notified. Quiz ID: ' + (data.QuizID ?? '—')
                );
                resetForm();
            } else {
                showError(data.message ?? data.error ?? 'Something went wrong.');
            }
        } catch (err) {
            showError('Could not connect to the server. Is Laravel running?');
        }
    }

    function saveDraft() {
        showSuccess('Draft saved locally. Click Publish Quiz when ready to send.');
    }

    function resetForm() {
        document.getElementById('Title').value = '';
        document.getElementById('DateInput').value = '';
        document.getElementById('TimeInput').value = '';
        document.getElementById('Duration').value = '';
        document.getElementById('TargetCategory').value = '';
        document.getElementById('questionsList').innerHTML = '';
        questionCount = 0;
        optionCounts  = {};
        updateCountBadge();
    }

    // ── ALERTS ──────────────────────────────────────────────────
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
</script>
</body>
</html>