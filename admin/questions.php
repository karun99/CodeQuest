<?php
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/layout.php';
require_admin();

$level = sanitize_input($_GET['level'] ?? '');
$status = sanitize_input($_GET['status'] ?? '');
$qtype = sanitize_input($_GET['qtype'] ?? '');
$search = sanitize_input($_GET['q'] ?? '');

$where = [];
$params = [];
if ($level !== '') {
    $where[] = "q.level = ?";
    $params[] = $level;
}
if ($status !== '') {
    $where[] = "q.status = ?";
    $params[] = $status;
}
if ($qtype !== '') {
    $where[] = "q.qtype = ?";
    $params[] = $qtype;
}
if ($search !== '') {
    $where[] = "(q.question_text LIKE ? OR q.code_snippet LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
$sql = "SELECT q.*, cs.title AS cs_title FROM questions q LEFT JOIN case_studies cs ON q.case_study_id = cs.id";
if ($where) {
    $sql .= " WHERE " . implode(" AND ", $where);
}
$sql .= " ORDER BY q.id DESC";
$stmt = db()->prepare($sql);
$stmt->execute($params);
$questions = $stmt->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    require_csrf();
    db()->prepare("DELETE FROM questions WHERE id = ?")->execute([(int)$_POST['delete_id']]);
    set_flash('success', 'Question deleted.');
    redirect('questions.php');
}

admin_header('Questions');
?>
<form method="GET" class="filter-bar">
    <select name="level" onchange="this.form.submit()">
        <option value="">All Levels</option>
        <option value="aptitude" <?= $level === 'aptitude' ? 'selected' : '' ?>>Aptitude</option>
        <option value="programming" <?= $level === 'programming' ? 'selected' : '' ?>>Programming</option>
        <option value="debugging" <?= $level === 'debugging' ? 'selected' : '' ?>>Debugging</option>
    </select>
    <select name="qtype" onchange="this.form.submit()">
        <option value="">All Types</option>
        <option value="mcq" <?= $qtype === 'mcq' ? 'selected' : '' ?>>MCQ</option>
        <option value="fill" <?= $qtype === 'fill' ? 'selected' : '' ?>>Fill-in-the-blank</option>
        <option value="code_choice" <?= $qtype === 'code_choice' ? 'selected' : '' ?>>Code Choice</option>
    </select>
    <select name="status" onchange="this.form.submit()">
        <option value="">All Status</option>
        <option value="draft" <?= $status === 'draft' ? 'selected' : '' ?>>Draft</option>
        <option value="published" <?= $status === 'published' ? 'selected' : '' ?>>Published</option>
    </select>
    <input type="text" name="q" placeholder="Search questions..." value="<?= e($search) ?>">
    <button type="submit" class="btn btn-secondary btn-sm">Search</button>
    <a href="question_edit.php" class="btn btn-primary btn-sm">New Question</a>
</form>

<table class="data-table">
    <thead>
        <tr>
            <th>ID</th><th>Level</th><th>Type</th><th>Question</th><th>Case Study</th><th>Marks</th><th>Status</th><th>Actions</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($questions as $q): ?>
        <tr>
            <td><?= $q['id'] ?></td>
            <td><span class="badge badge-<?= $q['level'] ?>"><?= e($q['level']) ?></span></td>
            <td><?= e($q['qtype']) ?></td>
            <td class="q-cell"><?= e(mb_substr($q['question_text'], 0, 80)) ?><?= mb_strlen($q['question_text']) > 80 ? '…' : '' ?></td>
            <td><?= e($q['cs_title'] ?? '—') ?></td>
            <td><?= $q['marks'] ?></td>
            <td><span class="badge badge-<?= $q['status'] ?>"><?= e($q['status']) ?></span></td>
            <td class="actions">
                <a href="question_edit.php?id=<?= $q['id'] ?>" class="btn btn-secondary btn-sm">Edit</a>
                <form method="POST" class="inline-form" onsubmit="return confirm('Delete this question?');">
                    <?= csrf_field() ?>
                    <input type="hidden" name="delete_id" value="<?= $q['id'] ?>">
                    <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                </form>
            </td>
        </tr>
        <?php endforeach; ?>
        <?php if (!$questions): ?>
        <tr><td colspan="8" class="text-muted">No questions found.</td></tr>
        <?php endif; ?>
    </tbody>
</table>
<?php admin_footer(); ?>