<?php
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/layout.php';
require_admin();

$counts = [
    'questions' => db()->query("SELECT COUNT(*) FROM questions")->fetchColumn(),
    'published' => db()->query("SELECT COUNT(*) FROM questions WHERE status='published'")->fetchColumn(),
    'candidates' => db()->query("SELECT COUNT(*) FROM candidates")->fetchColumn(),
    'exams' => db()->query("SELECT COUNT(*) FROM exams")->fetchColumn(),
    'active_exam' => db()->query("SELECT COUNT(*) FROM exams WHERE is_active=1")->fetchColumn(),
    'attempts' => db()->query("SELECT COUNT(*) FROM attempts WHERE status='completed'")->fetchColumn(),
    'in_progress' => db()->query("SELECT COUNT(*) FROM attempts WHERE status='in_progress'")->fetchColumn(),
    'violations' => db()->query("SELECT COUNT(*) FROM attempt_violations")->fetchColumn(),
];

$recent = db()->query("
    SELECT a.id, c.name, c.roll_no, e.title AS exam_title, a.score, a.max_score, a.status, a.finished_at
    FROM attempts a
    JOIN candidates c ON a.candidate_id = c.id
    JOIN exams e ON a.exam_id = e.id
    WHERE a.status = 'completed'
    ORDER BY a.finished_at DESC LIMIT 10
")->fetchAll();

admin_header('Dashboard');
?>
<div class="dashboard">
    <div class="stat-grid">
        <div class="stat-card"><div class="stat-num"><?= $counts['questions'] ?></div><div class="stat-label">Total Questions</div></div>
        <div class="stat-card"><div class="stat-num"><?= $counts['published'] ?></div><div class="stat-label">Published</div></div>
        <div class="stat-card"><div class="stat-num"><?= $counts['candidates'] ?></div><div class="stat-label">Candidates</div></div>
        <div class="stat-card"><div class="stat-num"><?= $counts['exams'] ?></div><div class="stat-label">Exams</div></div>
        <div class="stat-card"><div class="stat-num"><?= $counts['active_exam'] ?></div><div class="stat-label">Active Exam</div></div>
        <div class="stat-card"><div class="stat-num"><?= $counts['attempts'] ?></div><div class="stat-label">Completed</div></div>
        <div class="stat-card"><div class="stat-num"><?= $counts['in_progress'] ?></div><div class="stat-label">In Progress</div></div>
        <div class="stat-card"><div class="stat-num"><?= $counts['violations'] ?></div><div class="stat-label">Violations</div></div>
    </div>

    <h2>Recent Attempts</h2>
    <?php if ($recent): ?>
    <table class="data-table">
        <thead>
            <tr><th>ID</th><th>Candidate</th><th>Roll</th><th>Exam</th><th>Score</th><th>%</th><th>Completed</th></tr>
        </thead>
        <tbody>
            <?php foreach ($recent as $r):
                $pct = $r['max_score'] > 0 ? round(($r['score'] / $r['max_score']) * 100, 1) : 0;
            ?>
            <tr>
                <td><?= $r['id'] ?></td>
                <td><?= e($r['name']) ?></td>
                <td><?= e($r['roll_no']) ?></td>
                <td><?= e($r['exam_title']) ?></td>
                <td><?= $r['score'] ?>/<?= $r['max_score'] ?></td>
                <td><?= $pct ?>%</td>
                <td><?= e($r['finished_at']) ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php else: ?>
    <p class="text-muted">No completed attempts yet.</p>
    <?php endif; ?>
</div>
<?php admin_footer(); ?>
