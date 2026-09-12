<?php
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/layout.php';
require_admin();

$exam_id = sanitize_input($_GET['exam_id'] ?? '');
$from = sanitize_input($_GET['from'] ?? '');
$to = sanitize_input($_GET['to'] ?? '');
$status = sanitize_input($_GET['status'] ?? '');
$attempt_id = isset($_GET['attempt_id']) ? (int)$_GET['attempt_id'] : 0;

$exams = db()->query("SELECT id, title FROM exams ORDER BY title")->fetchAll();

$where = ["a.status = 'completed'"];
$params = [];
if ($exam_id !== '') {
    $where[] = "a.exam_id = ?";
    $params[] = (int)$exam_id;
}
if ($from !== '') {
    $where[] = "a.finished_at >= ?";
    $params[] = $from . ' 00:00:00';
}
if ($to !== '') {
    $where[] = "a.finished_at <= ?";
    $params[] = $to . ' 23:59:59';
}
if ($status !== '' && in_array($status, ['PASS', 'FAIL'])) {
    $where[] = "((a.score * 100.0) / CASE WHEN a.max_score = 0 THEN 1 ELSE a.max_score END) " . ($status === 'PASS' ? '>=' : '<') . " e.passing_percent";
}

$sql = "SELECT a.*, c.name, c.roll_no, e.title AS exam_title, e.passing_percent, a.level_scores,
        (SELECT COUNT(*) FROM attempt_violations v WHERE v.attempt_id = a.id) AS violation_count
        FROM attempts a
        JOIN candidates c ON a.candidate_id = c.id
        JOIN exams e ON a.exam_id = e.id
        WHERE " . implode(' AND ', $where) . "
        ORDER BY a.finished_at DESC";
$stmt = db()->prepare($sql);
$stmt->execute($params);
$attempts = $stmt->fetchAll();

$detail = null;
if ($attempt_id) {
    $dq = db()->prepare("SELECT a.*, c.name, c.roll_no, e.title AS exam_title, e.passing_percent FROM attempts a JOIN candidates c ON a.candidate_id=c.id JOIN exams e ON a.exam_id=e.id WHERE a.id=?");
    $dq->execute([$attempt_id]);
    $detail = $dq->fetch();
    if ($detail) {
        $answers = db()->prepare("SELECT aa.selected_answer, aa.is_correct, q.question_text, q.qtype, q.correct_answer, q.marks, q.level, q.code_snippet FROM attempt_answers aa JOIN questions q ON aa.question_id = q.id WHERE aa.attempt_id = ? ORDER BY q.id");
        $answers->execute([$attempt_id]);
        $detail['answers'] = $answers->fetchAll();
        $vq = db()->prepare("SELECT kind, created_at FROM attempt_violations WHERE attempt_id = ?");
        $vq->execute([$attempt_id]);
        $detail['violations'] = $vq->fetchAll();
    }
}

admin_header('Reports');
?>
<form method="GET" class="filter-bar">
    <select name="exam_id">
        <option value="">All Exams</option>
        <?php foreach ($exams as $ex): ?>
        <option value="<?= $ex['id'] ?>" <?= $exam_id == $ex['id'] ? 'selected' : '' ?>><?= e($ex['title']) ?></option>
        <?php endforeach; ?>
    </select>
    <select name="status">
        <option value="">All Results</option>
        <option value="PASS" <?= $status === 'PASS' ? 'selected' : '' ?>>PASS</option>
        <option value="FAIL" <?= $status === 'FAIL' ? 'selected' : '' ?>>FAIL</option>
    </select>
    <input type="date" name="from" value="<?= e($from) ?>">
    <input type="date" name="to" value="<?= e($to) ?>">
    <button type="submit" class="btn btn-secondary btn-sm">Filter</button>
    <a href="reports.php" class="btn btn-secondary btn-sm">Reset</a>
</form>

<?php if ($detail): ?>
<div class="card attempt-detail">
    <h2>Attempt #<?= $detail['id'] ?> — <?= e($detail['name']) ?> (<?= e($detail['roll_no']) ?>)</h2>
    <p>Exam: <?= e($detail['exam_title']) ?> &middot; Score: <?= $detail['score'] ?>/<?= $detail['max_score'] ?> &middot; Pass %: <?= $detail['passing_percent'] ?></p>
    <table class="data-table">
        <thead><tr><th>#</th><th>Level</th><th>Question</th><th>Answer</th><th>Correct?</th><th>Marks</th></tr></thead>
        <tbody>
            <?php $n = 1; foreach ($detail['answers'] as $a): ?>
            <tr>
                <td><?= $n++ ?></td>
                <td><span class="badge badge-<?= $a['level'] ?>"><?= e($a['level']) ?></span></td>
                <td class="q-cell"><?= e(mb_substr($a['question_text'], 0, 70)) ?></td>
                <td><?= e($a['selected_answer'] !== '' ? $a['selected_answer'] : '—') ?></td>
                <td><span class="badge badge-<?= $a['is_correct'] ? 'published' : 'fail' ?>"><?= $a['is_correct'] ? 'Yes' : 'No' ?></span></td>
                <td><?= $a['marks'] ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php if ($detail['violations']): ?>
    <h3>Violations</h3>
    <ul>
        <?php foreach ($detail['violations'] as $v): ?>
        <li><?= e($v['kind']) ?> — <?= e($v['created_at']) ?></li>
        <?php endforeach; ?>
    </ul>
    <?php endif; ?>
</div>
<?php endif; ?>

<table class="data-table">
    <thead>
        <tr><th>ID</th><th>Candidate</th><th>Roll</th><th>Exam</th><th>Score</th><th>%</th><th>Result</th><th>Levels</th><th>Viol.</th><th>Completed</th><th></th></tr>
    </thead>
    <tbody>
        <?php foreach ($attempts as $a):
            $pct = $a['max_score'] > 0 ? round(($a['score'] / $a['max_score']) * 100, 1) : 0;
            $pass = $pct >= $a['passing_percent'];
            $ls = json_decode($a['level_scores'], true) ?? [];
        ?>
        <tr>
            <td><?= $a['id'] ?></td>
            <td><?= e($a['name']) ?></td>
            <td><?= e($a['roll_no']) ?></td>
            <td><?= e($a['exam_title']) ?></td>
            <td><?= $a['score'] ?>/<?= $a['max_score'] ?></td>
            <td><?= $pct ?>%</td>
            <td><span class="badge badge-<?= $pass ? 'published' : 'fail' ?>"><?= $pass ? 'PASS' : 'FAIL' ?></span></td>
            <td class="level-cell">
                <?php foreach (['aptitude', 'programming', 'debugging'] as $lvl):
                    $s = $ls[$lvl] ?? [0, 0]; ?>
                <span class="level-tag" title="<?= ucfirst($lvl) ?>"><?= $lvl[0] . $s[0] . '/' . $s[1] ?></span>
                <?php endforeach; ?>
            </td>
            <td><?= $a['violation_count'] ?></td>
            <td><?= e($a['finished_at']) ?></td>
            <td><a href="?attempt_id=<?= $a['id'] ?><?= $exam_id !== '' ? '&exam_id=' . urlencode($exam_id) : '' ?>" class="btn btn-secondary btn-sm">Detail</a></td>
        </tr>
        <?php endforeach; ?>
        <?php if (!$attempts): ?>
        <tr><td colspan="11" class="text-muted">No completed attempts match filters.</td></tr>
        <?php endif; ?>
    </tbody>
</table>
<?php admin_footer(); ?>