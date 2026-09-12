<?php
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/layout.php';
require_candidate();

$attempt_id = isset($_GET['attempt_id']) ? (int)$_GET['attempt_id'] : 0;
$cid = $_SESSION['candidate_id'];

$att = db()->prepare("SELECT a.*, e.title AS exam_title, e.passing_percent FROM attempts a JOIN exams e ON a.exam_id = e.id WHERE a.id = ? AND a.candidate_id = ?");
$att->execute([$attempt_id, $cid]);
$attempt = $att->fetch();

if (!$attempt) {
    candidate_header('Result');
    echo '<div class="alert alert-danger">Attempt not found.</div>';
    candidate_footer();
    exit;
}

if ($attempt['status'] === 'in_progress') {
    redirect('quiz.php');
}

$level_scores = json_decode($attempt['level_scores'], true) ?? [];
$percent = $attempt['max_score'] > 0 ? round(($attempt['score'] / $attempt['max_score']) * 100, 2) : 0;
$result_pass = $percent >= $attempt['passing_percent'];

$violations = db()->prepare("SELECT kind, created_at FROM attempt_violations WHERE attempt_id = ? ORDER BY created_at");
$violations->execute([$attempt_id]);
$violations = $violations->fetchAll();

candidate_header('Result');
?>
<div class="result-container">
    <div class="result-card <?= $result_pass ? 'pass' : 'fail' ?>">
        <h2>Exam Result</h2>
        <div class="result-exam"><?= e($attempt['exam_title']) ?></div>

        <div class="result-score">
            <div class="score-big <?= $result_pass ? 'pass' : 'fail' ?>">
                <?= $attempt['score'] ?> / <?= $attempt['max_score'] ?>
            </div>
            <div class="score-percent"><?= $percent ?>%</div>
            <div class="result-status <?= $result_pass ? 'pass' : 'fail' ?>">
                <?= $result_pass ? 'PASS' : 'FAIL' ?>
            </div>
        </div>

        <table class="result-table">
            <thead>
                <tr><th>Level</th><th>Score</th><th>Max</th><th>%</th></tr>
            </thead>
            <tbody>
                <?php foreach (['aptitude', 'programming', 'debugging'] as $lvl):
                    $s = $level_scores[$lvl] ?? [0, 0];
                    $lp = $s[1] > 0 ? round(($s[0] / $s[1]) * 100, 2) : 0;
                ?>
                <tr>
                    <td><span class="badge badge-<?= $lvl ?>"><?= ucfirst($lvl) ?></span></td>
                    <td><?= $s[0] ?></td>
                    <td><?= $s[1] ?></td>
                    <td><?= $lp ?>%</td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="result-meta">
            <p>Started: <?= e($attempt['started_at']) ?></p>
            <p>Completed: <?= e($attempt['finished_at']) ?></p>
        </div>

        <?php if ($violations): ?>
        <div class="violations-box">
            <h3>Integrity Violations</h3>
            <ul>
                <?php foreach ($violations as $v): ?>
                <li><?= e($v['kind']) ?> — <?= e($v['created_at']) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>
    </div>

    <a href="quiz.php" class="btn btn-primary">Back to Exam</a>
</div>
<?php candidate_footer(); ?>
