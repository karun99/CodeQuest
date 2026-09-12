<?php
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/layout.php';
require_candidate();

$cid = $_SESSION['candidate_id'];

$active = db()->query("SELECT id, title, duration_minutes, passing_percent FROM exams WHERE is_active = 1 LIMIT 1")->fetch();
if (!$active) {
    candidate_header('Exam');
    echo '<div class="alert alert-info">No active exam at the moment. Please check back later.</div>';
    candidate_footer();
    exit;
}

$exam_id = $active['id'];

$attempt = db()->prepare("SELECT id, status, started_at FROM attempts WHERE candidate_id = ? AND exam_id = ? ORDER BY id DESC LIMIT 1");
$attempt->execute([$cid, $exam_id]);
$att = $attempt->fetch();

if ($att && $att['status'] === 'completed') {
    redirect('result.php?attempt_id=' . $att['id']);
}

if ($att && $att['status'] === 'in_progress') {
    $started = strtotime($att['started_at']);
    $elapsed = time() - $started;
    $remaining = ($active['duration_minutes'] * 60) - $elapsed;
    if ($remaining <= 0) {
        require_once __DIR__ . '/auto_submit.php';
        auto_submit($att['id']);
        redirect('result.php?attempt_id=' . $att['id']);
    }
    $attempt_id = $att['id'];
} else {
    $stmt = db()->prepare("INSERT INTO attempts (candidate_id, exam_id, status) VALUES (?, ?, 'in_progress')");
    $stmt->execute([$cid, $exam_id]);
    $attempt_id = db()->lastInsertId();
    $started = time();
    $remaining = $active['duration_minutes'] * 60;
}

$qids = [];
$eq = db()->prepare("SELECT question_id FROM exam_questions WHERE exam_id = ? ORDER BY sort_order");
$eq->execute([$exam_id]);
while ($r = $eq->fetch()) {
    $qids[] = $r['question_id'];
}

$total_q = count($qids);
if ($total_q === 0) {
    candidate_header('Exam');
    echo '<div class="alert alert-warning">No questions configured for this exam.</div>';
    candidate_footer();
    exit;
}

$current = isset($_GET['q']) ? (int)$_GET['q'] : 0;
$current = max(0, min($current, $total_q - 1));

$qid = $qids[$current];
$ques = db()->prepare("SELECT q.*, cs.title AS cs_title, cs.scenario, cs.context_code FROM questions q LEFT JOIN case_studies cs ON q.case_study_id = cs.id WHERE q.id = ?");
$ques->execute([$qid]);
$question = $ques->fetch();

$options = $question['options'] ? json_decode($question['options'], true) : [];

$ans_stmt = db()->prepare("SELECT selected_answer FROM attempt_answers WHERE attempt_id = ? AND question_id = ?");
$ans_stmt->execute([$attempt_id, $qid]);
$existing = $ans_stmt->fetch();
$selected = $existing ? $existing['selected_answer'] : '';

$answered_map = [];
$am = db()->prepare("SELECT question_id, selected_answer FROM attempt_answers WHERE attempt_id = ?");
$am->execute([$attempt_id]);
while ($ar = $am->fetch()) {
    $answered_map[$ar['question_id']] = $ar['selected_answer'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['action'])) {
    require_csrf();
    $action = $_POST['action'];
    $answer = $_POST['answer'] ?? null;

    if ($answer !== null) {
        $ins = db()->prepare("INSERT INTO attempt_answers (attempt_id, question_id, selected_answer) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE selected_answer = VALUES(selected_answer), answered_at = CURRENT_TIMESTAMP");
        $ins->execute([$attempt_id, $qid, $answer]);
    }

    if ($action === 'submit') {
        require_once __DIR__ . '/auto_submit.php';
        auto_submit($attempt_id);
        redirect('result.php?attempt_id=' . $attempt_id);
    } elseif ($action === 'next') {
        redirect('quiz.php?q=' . ($current + 1) . '&attempt_id=' . $attempt_id);
    } elseif ($action === 'prev') {
        redirect('quiz.php?q=' . ($current - 1) . '&attempt_id=' . $attempt_id);
    }
}

$end_time = $started + ($active['duration_minutes'] * 60);
$remaining_secs = max(0, $end_time - time());

$settings = [];
$sr = db()->query("SELECT k, v FROM settings");
while ($s = $sr->fetch()) {
    $settings[$s['k']] = $s['v'];
}

candidate_header('Exam - ' . e($active['title']));
?>
<link rel="stylesheet" href="<?= ASSETS_URL ?>/css/quiz.css">
<div class="quiz-container" data-end-time="<?= $end_time ?>" data-attempt-id="<?= $attempt_id ?>">
    <div class="quiz-topbar">
        <div class="quiz-info">
            <span class="exam-title"><?= e($active['title']) ?></span>
            <span class="timer" id="timer"></span>
        </div>
        <div class="quiz-progress">
            <span>Question <?= ($current + 1) ?> of <?= $total_q ?></span>
            <div class="progress-bar">
                <div class="progress-fill" style="width: <?= (($current + 1) / $total_q * 100) ?>%"></div>
            </div>
        </div>
    </div>

    <form method="POST" id="quizForm">
        <?= csrf_field() ?>
        <?php if ($question['cs_title']): ?>
        <div class="case-study-box">
            <h3>Case Study: <?= e($question['cs_title']) ?></h3>
            <div class="scenario"><?= nl2br(e($question['scenario'])) ?></div>
            <?php if ($question['context_code']): ?>
            <pre class="code-block"><code><?= e($question['context_code']) ?></code></pre>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <div class="question-box">
            <div class="question-meta">
                <span class="badge badge-<?= $question['level'] ?>"><?= e($question['level']) ?></span>
                <span class="badge badge-diff"><?= e($question['difficulty']) ?></span>
                <span class="marks"><?= $question['marks'] ?> mark<?= $question['marks'] > 1 ? 's' : '' ?></span>
            </div>
            <div class="question-text"><?= nl2br(e($question['question_text'])) ?></div>
            <?php if ($question['code_snippet']): ?>
            <pre class="code-block"><code><?= e($question['code_snippet']) ?></code></pre>
            <?php endif; ?>

            <?php if ($question['qtype'] === 'mcq' || $question['qtype'] === 'code_choice'): ?>
            <div class="options">
                <?php foreach ($options as $i => $opt): ?>
                <label class="option <?= $selected == $i ? 'selected' : '' ?>">
                    <input type="radio" name="answer" value="<?= $i ?>" <?= $selected == $i ? 'checked' : '' ?>>
                    <span class="option-text"><?= e($opt) ?></span>
                </label>
                <?php endforeach; ?>
            </div>
            <?php elseif ($question['qtype'] === 'fill'): ?>
            <div class="fill-input">
                <label for="answer">Your Answer:</label>
                <input type="text" id="answer" name="answer" value="<?= e($selected) ?>" autocomplete="off" spellcheck="false">
            </div>
            <?php endif; ?>
        </div>

        <div class="quiz-nav">
            <?php if ($current > 0): ?>
            <button type="submit" name="action" value="prev" class="btn btn-secondary">Previous</button>
            <?php endif; ?>
            <div class="quiz-nav-spacer"></div>
            <?php if ($current < $total_q - 1): ?>
            <button type="submit" name="action" value="next" class="btn btn-primary">Next</button>
            <?php endif; ?>
            <?php if ($current === $total_q - 1): ?>
            <button type="submit" name="action" value="submit" class="btn btn-success" id="submitBtn">Submit Exam</button>
            <?php endif; ?>
        </div>
    </form>

    <div class="question-map">
        <h4>Questions</h4>
        <div class="qmap-grid">
            <?php for ($i = 0; $i < $total_q; $i++): ?>
            <a href="?q=<?= $i ?>" class="qmap-item <?= $i === $current ? 'current' : '' ?> <?= isset($answered_map[$qids[$i]]) ? 'answered' : '' ?>"><?= ($i + 1) ?></a>
            <?php endfor; ?>
        </div>
    </div>
</div>

<script>
    window.CodeQuestSettings = <?= json_encode($settings) ?>;
</script>
<script src="<?= ASSETS_URL ?>/js/timer.js"></script>
<?php if (($settings['single_tab'] ?? '1') === '1'): ?>
<script src="<?= ASSETS_URL ?>/js/single_tab.js"></script>
<?php endif; ?>
<script src="<?= ASSETS_URL ?>/js/anti_cheat.js"></script>
<?php candidate_footer(); ?>
