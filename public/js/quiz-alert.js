(function () {
    // Don't run on the quiz-take page itself
    if (window.location.pathname.includes('/take')) return;

    let popupShown = false;
    let blurDiv = null;
    let overlay = null;
    let popupCloseTimer = null;

    async function checkForActiveQuiz() {
        try {
            const res  = await fetch('/quiz/active-now', {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            const data = await res.json();

            if (data.quiz && !popupShown) {
                popupShown = true;
                showQuizPopup(data.quiz);
            } else if (!data.quiz && popupShown) {
                // The quiz's time window (StartTime + Duration) has passed -
                // the server itself has already stopped considering it
                // active, so the popup must vanish immediately instead of
                // sitting there blocking the page for a quiz that's no
                // longer joinable.
                closeQuizPopup();
            }
        } catch (e) {}
    }

    function closeQuizPopup() {
        if (popupCloseTimer) { clearTimeout(popupCloseTimer); popupCloseTimer = null; }
        if (overlay) { overlay.remove(); overlay = null; }
        if (blurDiv) { blurDiv.remove(); blurDiv = null; }
        popupShown = false;
    }

    function showQuizPopup(quiz) {
        // Blur layer behind popup
        blurDiv = document.createElement('div');
        blurDiv.id = 'quizBlurLayer';
        blurDiv.style.cssText = `
            position: fixed; inset: 0; z-index: 99998;
            backdrop-filter: blur(6px);
            background: rgba(15, 23, 42, 0.4);
        `;
        document.body.appendChild(blurDiv);

        // The blur layer and overlay below are both fixed, full-viewport,
        // higher z-index than everything else, so they already intercept
        // clicks to the page behind them just by being on top - no need to
        // (and must not) disable pointer-events on the whole body, since
        // nothing here ever resets that back to 'auto' afterward, which
        // would otherwise leave the entire page permanently unclickable.

        // Popup overlay
        overlay = document.createElement('div');
        overlay.id = 'quizOverlay';
        overlay.style.cssText = `
            position: fixed; inset: 0; z-index: 99999;
            display: flex; align-items: center; justify-content: center;
            font-family: 'Inter', sans-serif;
        `;

        const mins = Math.ceil(quiz.Duration);

        overlay.innerHTML = `
            <div style="
                background: #fff; border-radius: 16px;
                padding: 40px 36px; max-width: 420px; width: 90%;
                text-align: center; box-shadow: 0 20px 60px rgba(0,0,0,0.3);
                pointer-events: all;
            ">
                <div style="
                    width: 64px; height: 64px; border-radius: 50%;
                    background: #A7EBF2; display: flex; align-items: center;
                    justify-content: center; margin: 0 auto 20px;
                ">
                    <svg width="28" height="28" viewBox="0 0 24 24" fill="none"
                         stroke="#023859" stroke-width="2">
                        <circle cx="12" cy="12" r="10"/>
                        <polyline points="12 6 12 12 16 14"/>
                    </svg>
                </div>
                <h2 style="font-size:20px;font-weight:700;color:#011C40;margin-bottom:8px;">
                    Quiz Starting Now!
                </h2>
                <p style="font-size:14px;color:#6B8094;margin-bottom:6px;">
                    <strong style="color:#011C40;">${quiz.Title}</strong>
                </p>
                <p style="font-size:13px;color:#6B8094;margin-bottom:28px;">
                    ${mins} minutes &middot; Please focus on your quiz
                </p>
                <a href="/quiz/${quiz.QuizID}/take" style="
                    display: block; background: #26658C; color: #fff;
                    text-decoration: none; padding: 13px 24px;
                    border-radius: 8px; font-size:15px; font-weight:600;
                " onmouseover="this.style.background='#023859'"
                   onmouseout="this.style.background='#26658C'">
                    Start Quiz Now
                </a>
                <p style="font-size:11px;color:#6B8094;margin-top:16px;">
                    This quiz will auto-submit when time runs out
                </p>
            </div>
        `;

        document.body.appendChild(overlay);

        // Close precisely when the quiz's own time window ends, rather than
        // waiting for the next 15s poll to merely notice via /quiz/active-now.
        // The poll keeps running regardless, as a fallback (e.g. if a
        // lecturer edits the quiz's timing after this popup already opened).
        const expiryMs = new Date(quiz.StartTime).getTime() + quiz.Duration * 60000;
        const remainingMs = Math.max(expiryMs - Date.now(), 0);
        if (popupCloseTimer) clearTimeout(popupCloseTimer);
        popupCloseTimer = setTimeout(closeQuizPopup, remainingMs);
    }

    // Poll every 15 seconds
    checkForActiveQuiz();
    setInterval(checkForActiveQuiz, 15000);
})();
