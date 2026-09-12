<?php
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/layout.php';
require_admin();

$search = sanitize_input($_GET['q'] ?? '');
$sql = "SELECT c.*, (SELECT COUNT(*) FROM attempts a WHERE a.candidate_id = c.id) AS attempt_count FROM candidates c";
$params = [];
if ($search !== '') {
    $sql .= " WHERE c.roll_no LIKE ? OR c.name LIKE ? OR c.email LIKE ?";
    $params = ["%$search%", "%$search%", "%$search%"];
}
$sql .= " ORDER BY c.id DESC";
$stmt = db()->prepare($sql);
$stmt->execute($params);
$candidates = $stmt->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    require_csrf();
    $action = $_POST['action'];
    $cid = (int)($_POST['candidate_id'] ?? 0);

    if ($action === 'add') {
        $roll = sanitize_input($_POST['roll_no'] ?? '');
        $name = sanitize_input($_POST['name'] ?? '');
        $email = sanitize_input($_POST['email'] ?? '');
        $pass = $_POST['password'] ?? '';
        if ($roll === '' || $name === '') {
            set_flash('error', 'Roll number and name required.');
        } else {
            $hash = password_hash($pass !== '' ? $pass : bin2hex(random_bytes(8)), PASSWORD_BCRYPT);
            try {
                db()->prepare("INSERT INTO candidates (roll_no, name, email, password_hash) VALUES (?,?,?,?)")
                    ->execute([$roll, $name, $email ?: null, $hash]);
                set_flash('success', 'Candidate added. Password: ' . ($pass !== '' ? 'custom set.' : 'random generated (shown once below).'));
            } catch (PDOException $e) {
                set_flash('error', $e->getCode() == 23000 ? 'Roll number already exists.' : 'Add failed.');
            }
        }
    } elseif ($action === 'delete') {
        db()->prepare("DELETE FROM candidates WHERE id = ?")->execute([$cid]);
        set_flash('success', 'Candidate deleted.');
    } elseif ($action === 'reset') {
        $newpass = bin2hex(random_bytes(4));
        $hash = password_hash($newpass, PASSWORD_BCRYPT);
        db()->prepare("UPDATE candidates SET password_hash = ? WHERE id = ?")->execute([$hash, $cid]);
        set_flash('success', 'Password reset; new password: ' . $newpass . ' (note it down)');
    }
    redirect('candidates.php');
}

admin_header('Candidates');
?>
<div class="card">
    <h3>Add Candidate</h3>
    <form method="POST" class="inline-form">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="add">
        <div class="form-row">
            <input type="text" name="roll_no" placeholder="Roll No" required>
            <input type="text" name="name" placeholder="Full Name" required>
            <input type="email" name="email" placeholder="Email (optional)">
            <input type="text" name="password" placeholder="Password (optional, auto otherwise)">
            <button type="submit" class="btn btn-primary">Add</button>
        </div>
    </form>
</div>

<form method="GET" class="filter-bar">
    <input type="text" name="q" placeholder="Search candidates..." value="<?= e($search) ?>">
    <button type="submit" class="btn btn-secondary btn-sm">Search</button>
</form>

<table class="data-table">
    <thead>
        <tr><th>ID</th><th>Roll No</th><th>Name</th><th>Email</th><th>Attempts</th><th>Created</th><th>Actions</th></tr>
    </thead>
    <tbody>
        <?php foreach ($candidates as $c): ?>
        <tr>
            <td><?= $c['id'] ?></td>
            <td><?= e($c['roll_no']) ?></td>
            <td><?= e($c['name']) ?></td>
            <td><?= e($c['email'] ?? '—') ?></td>
            <td><?= $c['attempt_count'] ?></td>
            <td><?= e($c['created_at']) ?></td>
            <td class="actions">
                <form method="POST" class="inline-form">
                    <?= csrf_field() ?>
                    <input type="hidden" name="candidate_id" value="<?= $c['id'] ?>">
                    <button type="submit" name="action" value="reset" class="btn btn-secondary btn-sm">Reset Password</button>
                    <button type="submit" name="action" value="delete" class="btn btn-danger btn-sm" onclick="return confirm('Delete candidate?');">Delete</button>
                </form>
            </td>
        </tr>
        <?php endforeach; ?>
        <?php if (!$candidates): ?>
        <tr><td colspan="7" class="text-muted">No candidates found.</td></tr>
        <?php endif; ?>
    </tbody>
</table>
<?php admin_footer(); ?>