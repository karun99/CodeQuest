<?php
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/layout.php';
require_admin();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$exam = ['id' => 0, 'title' => '', 'duration_minutes' => 60, 'passing_percent' => 40, 'is_active' => 0];
$assigned_ids = [];

if ($id) {
    $stmt = db()->prepare("SELECT * FROM exams WHERE id = ?");
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if ($row) $exam = $row;
    $eq = db()->prepare("SELECT question_id FROM exam_questions WHERE exam_id = ? ORDER BY sort_order");
    $eq->execute([$id]);
    while ($r = $eq->fetch()) $assigned_ids[] = $r['question_id'];
}

$published = db()->query("SELECT id, level, qtype, question_text, marks, difficulty FROM questions WHERE status = 'published' ORDER BY id DESC")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $data = [
        'title' => sanitize_input($_POST['title'] ?? ''),
        'duration_minutes' => max(1, (int)($_POST['duration_minutes'] ?? 60)),
        'passing_percent' => max(0, min(100, (int)($_POST['passing_percent'] ?? 40))),
    ];
    $selected = $_POST['question_ids'] ?? [];
    $selected = array_map('intval', (array)$selected);

    if ($data['title'] === '') {
        set_flash('error', 'Title is required.');
    } else {
        try {
            if ($id) {
                db()->prepare("UPDATE exams SET title=?, duration_minutes=?, passing_percent=? WHERE id=?")
                    ->execute([$data['title'], $data['duration_minutes'], $data['passing_percent'], $id]);
                db()->prepare("DELETE FROM exam_questions WHERE exam_id = ?")->execute([$id]);
            } else {
                db()->prepare("INSERT INTO exams (title, duration_minutes, passing_percent) VALUES (?,?,?)")
                    ->execute([$data['title'], $data['duration_minutes'], $data['passing_percent']]);
                $id = (int)db()->lastInsertId();
            }
            $ins = db()->prepare("INSERT INTO exam_questions (exam_id, question_id, sort_order) VALUES (?,?,?)");
            foreach ($selected as $i => $qid) {
                $ins->execute([$id, $qid, $i]);
            }
            if (isset($_POST['activate'])) {
                db()->prepare("UPDATE exams SET is_active = (id = ?)")->execute([$id]);
            } elseif (isset($_POST['deactivate'])) {
                db()->prepare("UPDATE exams SET is_active = 0 WHERE id = ?")->execute([$id]);
            }
            set_flash('success', 'Exam saved.');
            redirect('exams.php');
        } catch (PDOException $e) {
            set_flash('error', 'Save failed: ' . $e->getMessage());
        }
    }
}

admin_header($id ? 'Edit Exam #' . $id : 'New Exam');
?>
<form method="POST">
    <?= csrf_field() ?>
    <div class="form-grid-2">
        <div class="form-group">
            <label for="title">Title *</label>
            <input type="text" id="title" name="title" value="<?= e($exam['title']) ?>" required>
        </div>
        <div class="form-group">
            <label for="duration_minutes">Duration (minutes)</label>
            <input type="number" id="duration_minutes" name="duration_minutes" value="<?= (int)$exam['duration_minutes'] ?>" min="1">
        </div>
        <div class="form-group">
            <label for="passing_percent">Passing Percent</label>
            <input type="number" id="passing_percent" name="passing_percent" value="<?= (int)$exam['passing_percent'] ?>" min="0" max="100">
        </div>
    </div>

    <h3>Questions</h3>
    <div id="examQuestionList">
        <?php foreach ($published as $q): ?>
        <label class="exam-q-item">
            <input type="checkbox" name="question_ids[]" value="<?= $q['id'] ?>" <?= in_array($q['id'], $assigned_ids) ? 'checked' : '' ?>>
            <span class="badge badge-<?= $q['level'] ?>"><?= e($q['level']) ?></span>
            <span class="q-desc"><?= e(mb_substr($q['question_text'], 0, 100)) ?></span>
            <span class="q-marks"><?= $q['marks'] ?>m</span>
        </label>
        <?php endforeach; ?>
        <?php if (!$published): ?>
        <p class="text-muted">No published questions available. Publish questions first.</p>
        <?php endif; ?>
    </div>

    <div class="btn-group">
        <button type="submit" class="btn btn-primary">Save Exam</button>
        <button type="submit" name="activate" value="1" class="btn btn-success">Save & Activate</button>
        <?php if ($id): ?>
        <button type="submit" name="deactivate" value="1" class="btn btn-secondary">Deactivate</button>
        <?php endif; ?>
    </div>
</form>
<?php admin_footer(); ?>