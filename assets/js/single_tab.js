(function () {
    'use strict';

    const quiz = document.querySelector('.quiz-container');
    if (!quiz) return;
    const attemptId = quiz.dataset.attemptId;
    if (!attemptId) return;

    const KEY = 'codequest_tab_' + attemptId;
    const STALE_MS = 3000;
    const BEAT_MS = 1000;

    let isFounder = true;
    try {
        const last = parseInt(localStorage.getItem(KEY) || '0', 10);
        if (Date.now() - last < STALE_MS) {
            isFounder = false; // another exam tab is actively alive
        }
    } catch (e) { /* fall through: act as founder */ }

    let bc = null;
    try { bc = new BroadcastChannel(KEY); } catch (e) { bc = null; }

    function showReplaced() {
        document.body.innerHTML =
            '<div style="position:fixed;top:0;left:0;right:0;bottom:0;background:#1a1a2e;color:#fff;display:flex;align-items:center;justify-content:center;z-index:99999;font-family:sans-serif;">' +
            '<div style="text-align:center;padding:2rem;">' +
            '<h2 style="margin-bottom:0.75rem;">Exam tab closed</h2>' +
            '<p style="margin-bottom:1.25rem;">This attempt is already open in another tab. Only one exam tab is allowed per attempt.</p>' +
            '<a href="index.php" style="color:#00d1ff;font-weight:700;">Go to Login</a>' +
            '</div></div>';
    }

    if (!isFounder) {
        if (bc) bc.postMessage({ type: 'claim', t: Date.now() });
        showReplaced();
        return;
    }

    try { localStorage.setItem(KEY, Date.now().toString()); } catch (e) {}

    function heartbeat() {
        try { localStorage.setItem(KEY, Date.now().toString()); } catch (e) {}
        if (bc) bc.postMessage({ type: 'claim', t: Date.now() });
    }

    const hb = setInterval(heartbeat, BEAT_MS);

    if (bc) {
        bc.onmessage = (ev) => {
            if (ev.data && ev.data.type === 'claim') {
                // Another tab claims to be active. If ours is still the freshest
                // heartbeat, keep running; otherwise we lose leadership.
                try {
                    const last = parseInt(localStorage.getItem(KEY) || '0', 10);
                    const ours = Date.now() - last < BEAT_MS * 2;
                    if (!ours) {
                        clearInterval(hb);
                        showReplaced();
                    }
                } catch (e) {}
            }
        };
        bc.postMessage({ type: 'claim', t: Date.now() });
    }

    window.addEventListener('beforeunload', () => clearInterval(hb));
})();