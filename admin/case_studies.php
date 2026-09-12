<?php
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/layout.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    require_csrf();
    db()->prepare("DELETE FROM case_studies WHERE id = ?")->execute([(int)$_POST['delete_id']]);
    set_flash('success', 'Case study deleted; question refs nullified.');
    redirect('case_studies.php');
}

$case_list = db()->query("SELECT cs.*, COUNT(q.id) AS refs FROM case_studies cs LEFT JOIN questions q ON cs.id = q.case_study_id GROUP BY cs.id ORDER BY cs.id DESC")->fetchAll();

admin_header('Case Studies');
?>
<a href="case_study_edit.php" class="btn btn-primary">New Case Study</a>
<table class="data-table">
    <thead>
        <tr><th>ID</th><th>Title</th><th>Language</th><th>Linked Questions</th><th>Created</th><th>Actions</th></tr>
    </thead>
    <tbody>
        <?php foreach ($case_list as $cs): ?>
        <tr>
            <td><?= $cs['id'] ?></td>
            <td><?= e(mb_substr($cs['title'], 0, 60)) ?></td>
            <td><?= e($cs['language'] ?? '—') ?></td>
            <td><?= $cs['refs'] ?></td>
            <td><?= e($cs['created_at']) ?></td>
            <td class="actions">
                <a href="case_study_edit.php?id=<?= $cs['id'] ?>" class="btn btn-secondary btn-sm">Edit</a>
                <form method="POST" class="inline-form" onsubmit="return confirm('Delete this case study? Linked questions keep their refs null.');">
                    <?= csrf_field() ?>
                    <input type="hidden" name="delete_id" value="<?= $cs['id'] ?>">
                    <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                </form>
            </td>
        </tr>
        <?php endforeach; ?>
        <?php if (!$case_list): ?>
        <tr><td colspan="6" class="text-muted">No case studies yet.</td></tr>
        <?php endif; ?>
    </tbody>
</table>
<?php admin_footer(); ?>