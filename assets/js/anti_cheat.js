(function () {
    'use strict';

    const attemptId = document.querySelector('.quiz-container')?.dataset.attemptId;
    const endTime = parseInt(document.querySelector('.quiz-container')?.dataset.endTime || '0', 10);
    if (!attemptId || !endTime) return;

    const timerEl = document.getElementById('timer');
    const quizForm = document.getElementById('quizForm');

    function beacon(kind) {
        if (navigator.sendBeacon) {
            const data = new FormData();
            data.append('attempt_id', attemptId);
            data.append('kind', kind);
            navigator.sendBeacon('beacon.php', data);
        } else {
            fetch('beacon.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({ attempt_id: attemptId, kind }).toString(),
                keepalive: true
            });
        }
    }

    function autoSubmit() {
        if (quizForm) {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'action';
            input.value = 'submit';
            quizForm.appendChild(input);
            quizForm.submit();
        } else {
            window.location.href = 'quiz.php';
        }
    }

    function formatTime(sec) {
        const m = Math.floor(sec / 60);
        const s = sec % 60;
        return String(m).padStart(2, '0') + ':' + String(s).padStart(2, '0');
    }

    function tick() {
        const remaining = Math.max(0, endTime - Math.floor(Date.now() / 1000));
        if (timerEl) {
            timerEl.textContent = formatTime(remaining);
            if (remaining <= 60) timerEl.classList.add('timer-danger');
        }
        if (remaining <= 0) {
            clearInterval(timerInterval);
            autoSubmit();
        }
    }

    const timerInterval = setInterval(tick, 1000);
    tick();

    /* Blur overlay */
    const settings = window.CodeQuestSettings || {};

    if (settings.blur_on_blur === '1') {
        let blurred = false;
        window.addEventListener('blur', () => {
            if (document.hidden) return;
            blurred = true;
            beacon('WindowBlur');
            showOverlay('Exam paused — click to resume', settings.autosubmit_on_switch === '1');
        });
        document.addEventListener('visibilitychange', () => {
            if (document.hidden) {
                if (settings.autosubmit_on_switch === '1') {
                    beacon('TabHidden');
                    autoSubmit();
                } else if (!blurred) {
                    beacon('TabHidden');
                    showOverlay('Exam paused — click to resume', false);
                }
            }
        });
    }

    function showOverlay(msg, autosubmitting) {
        let ov = document.getElementById('pauseOverlay');
        if (!ov) {
            ov = document.createElement('div');
            ov.id = 'pauseOverlay';
            ov.className = 'pause-overlay';
            ov.innerHTML = '<div class="pause-box"><h2>' + msg + '</h2>' + (autosubmitting ? '<p>Submitting automatically…</p>' : '<button class="btn btn-primary" id="resumeBtn">Resume</button>') + '</div>';
            document.body.appendChild(ov);
        }
        ov.style.display = 'flex';
        const btn = document.getElementById('resumeBtn');
        if (btn) btn.addEventListener('click', () => { ov.style.display = 'none'; });
    }

    /* Keyboard shortcut blocking */
    if (settings.block_shortcuts === '1') {
        document.addEventListener('keydown', function (e) {
            if (!quizForm) return;
            const k = e.key.toLowerCase();
            const ctrl = e.ctrlKey || e.metaKey;

            if (ctrl && /[cvxasypu]/.test(k)) { e.preventDefault(); e.stopPropagation(); }
            if (ctrl && e.shiftKey && /[ijkc]/.test(k)) { e.preventDefault(); beacon('DevToolsShortcut'); }
            if (e.key === 'F12') { e.preventDefault(); beacon('F12'); }
            if (e.key === 'F5' || (ctrl && k === 'r')) { e.preventDefault(); beacon('RefreshAttempt'); }
            if (ctrl && ['t', 'w', 'n'].includes(k)) { e.preventDefault(); beacon('TabShortcut'); }
            if (e.altKey && ['ArrowLeft', 'ArrowRight'].includes(e.key)) { e.preventDefault(); beacon('AltNav'); }
            if (e.key === 'ContextMenu') { e.preventDefault(); }

            const isTyping = /^(input|textarea)$/i.test(document.activeElement.tagName);
            if (isTyping && e.key.length === 1) return;
            if (e.key === 'Tab' && isTyping) return;
        });

        document.addEventListener('keyup', function (e) {
            if (e.key === 'F12') { e.preventDefault(); }
        });
    }

    /* Right-click blocking */
    if (settings.block_right_click === '1') {
        document.addEventListener('contextmenu', e => { e.preventDefault(); beacon('RightClick'); });
    }

    /* Copy/paste blocking and selection */
    if (settings.block_copy_paste === '1') {
        ['copy', 'cut', 'paste'].forEach(ev => {
            document.addEventListener(ev, e => {
                const t = e.target;
                const inInput = t && (t.tagName === 'INPUT' || t.tagName === 'TEXTAREA');
                const inCode = t && t.closest && t.closest('pre');
                if (!inInput && !inCode) { e.preventDefault(); beacon('CopyPaste_' + ev); }
            });
        });
    }

    /* PrintScreen detection */
    if (settings.block_shortcuts === '1') {
        document.addEventListener('keyup', e => {
            if (e.key === 'PrintScreen') {
                beacon('PrintScreen');
                if (navigator.clipboard) {
                    navigator.clipboard.writeText('').catch(() => {});
                }
                showOverlay('PrintScreen detected. This violation has been logged.', false);
            }
        });
        document.addEventListener('keydown', e => {
            if (e.key === 'PrintScreen') e.preventDefault();
        });
    }

    /* DevTools heuristic */
    if (settings.block_devtools === '1') {
        let devtoolsOpen = false;
        function detectDevTools() {
            const threshold = 160;
            const w = window.outerWidth - window.innerWidth > threshold;
            const h = window.outerHeight - window.innerHeight > threshold;
            if ((w || h) && !devtoolsOpen) {
                devtoolsOpen = true;
                beacon('DevTools');
                showOverlay('Developer tools detected. This violation has been logged.', false);
            } else if (!w && !h) {
                devtoolsOpen = false;
            }
        }
        detectDevTools();
        const devInterval = setInterval(detectDevTools, 2000);
        window.addEventListener('beforeunload', () => clearInterval(devInterval));
    }

    /* Fullscreen requirement */
    if (settings.fullscreen_required === '1') {
        document.addEventListener('click', function fsHandler() {
            if (!document.fullscreenElement && document.documentElement.requestFullscreen) {
                document.documentElement.requestFullscreen().catch(() => {});
            }
        });
        document.addEventListener('fullscreenchange', () => {
            if (!document.fullscreenElement) beacon('FullscreenExit');
        });
    }

    /* Alert on page unload during exam */
    window.addEventListener('beforeunload', (e) => {
        if (document.getElementById('quizForm')) {
            e.preventDefault();
            e.returnValue = '';
        }
    });

    window.CodeQuest = { beacon, autoSubmit };
})();