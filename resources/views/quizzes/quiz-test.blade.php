<!DOCTYPE html>
<html>
<head>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Quiz Engine Test</title>
    <style>
        body { font-family: Arial; padding: 30px; background: #f4f4f4; }
        .card { background: white; padding: 20px; border-radius: 8px;
                margin-bottom: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        h2 { color: #0052CC; }
        button { background: #0052CC; color: white; border: none;
                 padding: 10px 20px; border-radius: 6px; cursor: pointer;
                 font-size: 14px; margin-top: 10px; }
        button:hover { background: #003D99; }
        pre { background: #f4f4f4; padding: 14px; border-radius: 6px;
              font-size: 13px; white-space: pre-wrap; margin-top: 10px;
              border-left: 4px solid #0052CC; }
        input { padding: 8px 12px; border: 1px solid #ccc;
                border-radius: 6px; font-size: 14px; margin-right: 8px; }
        .pass { color: #1A6B3A; font-weight: bold; }
        .fail { color: #C0392B; font-weight: bold; }
    </style>
</head>
<body>

<h1 style="color:#172B4D;">Quiz Engine — Week 2 Tests</h1>
<p style="color:#5E6C84;">Logged in as: <strong>{{ auth()->user()->UserName }}</strong></p>

<!-- TEST 1 -->
<div class="card">
    <h2>Test 1 — Join Quiz On Time</h2>
    <p>Enter a Quiz ID that has StartTime = NOW and Duration = 10</p>
    <input type="number" id="joinQuizID" value="3" placeholder="Quiz ID">
    <button onclick="testJoin()">Run Test</button>
    <pre id="joinResult">Result will appear here...</pre>
</div>

<!-- TEST 2 -->
<div class="card">
    <h2>Test 2 — Manual Submit</h2>
    <p>Submit answers for a quiz. Uses Quiz ID and Question ID from Test 1.</p>
    <input type="number" id="submitQuizID" value="3" placeholder="Quiz ID">
    <input type="number" id="submitQID"    value="1" placeholder="Question ID">
    <input type="text"   id="submitAnswer" value="Paris" placeholder="Your Answer">
    <button onclick="testSubmit()">Run Test</button>
    <pre id="submitResult">Result will appear here...</pre>
</div>

<!-- TEST 3 -->
<div class="card">
    <h2>Test 3 — Auto Submit (Timer Hit Zero)</h2>
    <input type="number" id="autoQuizID" value="3" placeholder="Quiz ID">
    <input type="number" id="autoQID"    value="1" placeholder="Question ID">
    <input type="text"   id="autoAnswer" value="Paris" placeholder="Answer">
    <button onclick="testAutoSubmit()">Run Test</button>
    <pre id="autoResult">Result will appear here...</pre>
</div>

<!-- TEST 4 -->
<div class="card">
    <h2>Test 4 — Join After Quiz Closed</h2>
    <p>First update StartTime to 20 minutes ago in phpMyAdmin, then run this.</p>
    <input type="number" id="closedQuizID" value="3" placeholder="Quiz ID">
    <button onclick="testClosed()">Run Test</button>
    <pre id="closedResult">Result will appear here...</pre>
</div>

<script>
    const token = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

    async function callAPI(url, method, body) {
        const res = await fetch(url, {
            method,
            headers: {
                'Content-Type': 'application/json',
                'Accept':       'application/json',
                'X-CSRF-TOKEN': token,
            },
            body: body ? JSON.stringify(body) : undefined,
        });
        const data = await res.json();
        return { status: res.status, data };
    }

    async function testJoin() {
        const el = document.getElementById('joinResult');
        el.textContent = 'Running...';
        try {
            const quizID = parseInt(document.getElementById('joinQuizID').value);
            const r = await callAPI('/api/quiz/join', 'POST', { QuizID: quizID });
            const pass = r.status === 200 && r.data.AllocatedSeconds;
            el.innerHTML = (pass ? '<span class="pass">✅ PASS</span>' : '<span class="fail">❌ FAIL</span>') +
                '\nStatus: ' + r.status +
                '\n\n' + JSON.stringify(r.data, null, 2);
        } catch(e) { el.textContent = '❌ Error: ' + e.message; }
    }

    async function testSubmit() {
        const el = document.getElementById('submitResult');
        el.textContent = 'Running...';
        try {
            const quizID  = parseInt(document.getElementById('submitQuizID').value);
            const qID     = parseInt(document.getElementById('submitQID').value);
            const answer  = document.getElementById('submitAnswer').value;
            const r = await callAPI('/api/quiz/submit', 'POST', {
                QuizID:  quizID,
                Answers: [{ QuestionID: qID, ResponseText: answer }]
            });
            const pass = r.status === 200 && r.data.Score !== undefined;
            el.innerHTML = (pass ? '<span class="pass">✅ PASS</span>' : '<span class="fail">❌ FAIL</span>') +
                '\nStatus: ' + r.status +
                '\n\n' + JSON.stringify(r.data, null, 2);
        } catch(e) { el.textContent = '❌ Error: ' + e.message; }
    }

    async function testAutoSubmit() {
        const el = document.getElementById('autoResult');
        el.textContent = 'Running...';
        try {
            const quizID = parseInt(document.getElementById('autoQuizID').value);
            const qID    = parseInt(document.getElementById('autoQID').value);
            const answer = document.getElementById('autoAnswer').value;
            const r = await callAPI('/api/quiz/auto-submit', 'POST', {
                QuizID:  quizID,
                Answers: [{ QuestionID: qID, ResponseText: answer }]
            });
            const pass = r.status === 200 && r.data.IsAutoSubmit === true;
            el.innerHTML = (pass ? '<span class="pass">✅ PASS</span>' : '<span class="fail">❌ FAIL</span>') +
                '\nStatus: ' + r.status +
                '\n\n' + JSON.stringify(r.data, null, 2);
        } catch(e) { el.textContent = '❌ Error: ' + e.message; }
    }

    async function testClosed() {
        const el = document.getElementById('closedResult');
        el.textContent = 'Running...';
        try {
            const quizID = parseInt(document.getElementById('closedQuizID').value);
            const r = await callAPI('/api/quiz/join', 'POST', { QuizID: quizID });
            const pass = r.status === 403;
            el.innerHTML = (pass ? '<span class="pass">✅ PASS</span>' : '<span class="fail">❌ FAIL</span>') +
                '\nStatus: ' + r.status + ' (expected 403)' +
                '\n\n' + JSON.stringify(r.data, null, 2);
        } catch(e) { el.textContent = '❌ Error: ' + e.message; }
    }
</script>
</body>
</html>