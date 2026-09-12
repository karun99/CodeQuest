<?php
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/layout.php';
require_admin();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$cs = ['id' => 0, 'title' => '', 'scenario' => '', 'context_code' => '', 'language' => ''];

if ($id) {
    $stmt = db()->prepare("SELECT * FROM case_studies WHERE id = ?");
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if ($row) $cs = $row;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $data = [
        'title' => sanitize_input($_POST['title'] ?? ''),
        'scenario' => sanitize_input($_POST['scenario'] ?? ''),
        'context_code' => trim($_POST['context_code'] ?? ''),
        'language' => sanitize_input($_POST['language'] ?? ''),
    ];
    if ($data['title'] === '' || $data['scenario'] === '') {
        set_flash('error', 'Title and scenario are required.');
    } else {
        try {
            if ($id) {
                db()->prepare("UPDATE case_studies SET title=?, scenario=?, context_code=?, language=? WHERE id=?")
                    ->execute([$data['title'], $data['scenario'], $data['context_code'] ?: null, $data['language'] ?: null, $id]);
            } else {
                db()->prepare("INSERT INTO case_studies (title, scenario, context_code, language) VALUES (?,?,?,?)")
                    ->execute([$data['title'], $data['scenario'], $data['context_code'] ?: null, $data['language'] ?: null]);
            }
            set_flash('success', 'Case study saved.');
            redirect('case_studies.php');
        } catch (PDOException $e) {
            set_flash('error', 'Save failed: ' . $e->getMessage());
        }
    }
}

admin_header($id ? 'Edit Case Study #' . $id : 'New Case Study');
?>
<form method="POST">
    <?= csrf_field() ?>
    <div class="form-grid-2">
        <div class="form-group">
            <label for="title">Title *</label>
            <input type="text" id="title" name="title" value="<?= e($cs['title']) ?>" required>
        </div>
        <div class="form-group">
            <label for="language">Language</label>
            <input type="text" id="language" name="language" value="<?= e($cs['language']) ?>" placeholder="e.g. python">
        </div>
    </div>
    <div class="form-group">
        <label for="scenario">Scenario *</label>
        <textarea id="scenario" name="scenario" rows="6" required><?= e($cs['scenario']) ?></textarea>
    </div>
    <div class="form-group">
        <label for="context_code">Context Code</label>
        <textarea id="context_code" name="context_code" rows="8" class="mono-textarea"><?= e($cs['context_code']) ?></textarea>
    </div>
    <button type="submit" class="btn btn-primary">Save Case Study</button>
</form>
<?php admin_footer(); ?>