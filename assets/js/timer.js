(function () {
    'use strict';

    const quiz = document.querySelector('.quiz-container');
    if (!quiz) return;
    const attemptId = quiz.dataset.attemptId;
    const endTime = parseInt(quiz.dataset.endTime || '0', 10);
    if (!attemptId || !endTime) return;

    const timerEl = document.getElementById('timer');

    function formatTime(sec) {
        const safe = Math.max(0, sec);
        const m = Math.floor(safe / 60);
        const s = safe % 60;
        return String(m).padStart(2, '0') + ':' + String(s).padStart(2, '0');
    }

    function submitExam() {
        const form = document.getElementById('quizForm');
        if (form) {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'action';
            input.value = 'submit';
            form.appendChild(input);
            form.submit();
        } else {
            window.location.href = 'quiz.php';
        }
    }

    function tick() {
        const remaining = Math.max(0, endTime - Math.floor(Date.now() / 1000));
        if (timerEl) {
            timerEl.textContent = formatTime(remaining);
            if (remaining <= 60) {
                timerEl.classList.add('timer-danger');
            }
            if (remaining <= 0) {
                clearInterval(timerInterval);
                submitExam();
            }
        }
    }

    const timerInterval = setInterval(tick, 1000);
    tick();
})();