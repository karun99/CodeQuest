<?php
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/layout.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    require_csrf();
    db()->prepare("DELETE FROM exams WHERE id = ?")->execute([(int)$_POST['delete_id']]);
    set_flash('success', 'Exam deleted.');
    redirect('exams.php');
}

$exams = db()->query("
    SELECT e.*, COUNT(eq.question_id) AS q_count, SUM(q.marks) AS total_marks
    FROM exams e
    LEFT JOIN exam_questions eq ON e.id = eq.exam_id
    LEFT JOIN questions q ON eq.question_id = q.id
    GROUP BY e.id
    ORDER BY e.id DESC
")->fetchAll();

admin_header('Exams');
?>
<a href="exam_edit.php" class="btn btn-primary">New Exam</a>
<table class="data-table">
    <thead>
        <tr><th>ID</th><th>Title</th><th>Duration (min)</th><th>Pass %</th><th>Questions</th><th>Total Marks</th><th>Active</th><th>Actions</th></tr>
    </thead>
    <tbody>
        <?php foreach ($exams as $ex): ?>
        <tr>
            <td><?= $ex['id'] ?></td>
            <td><?= e($ex['title']) ?></td>
            <td><?= $ex['duration_minutes'] ?></td>
            <td><?= $ex['passing_percent'] ?>%</td>
            <td><?= $ex['q_count'] ?></td>
            <td><?= $ex['total_marks'] ?? 0 ?></td>
            <td><span class="badge badge-<?= $ex['is_active'] ? 'published' : 'draft' ?>"><?= $ex['is_active'] ? 'Active' : 'Inactive' ?></span></td>
            <td class="actions">
                <a href="exam_edit.php?id=<?= $ex['id'] ?>" class="btn btn-secondary btn-sm">Edit</a>
                <form method="POST" class="inline-form" onsubmit="return confirm('Delete this exam?');">
                    <?= csrf_field() ?>
                    <input type="hidden" name="delete_id" value="<?= $ex['id'] ?>">
                    <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                </form>
            </td>
        </tr>
        <?php endforeach; ?>
        <?php if (!$exams): ?>
        <tr><td colspan="8" class="text-muted">No exams yet.</td></tr>
        <?php endif; ?>
    </tbody>
</table>
<?php admin_footer(); ?>