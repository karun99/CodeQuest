<?php
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/layout.php';

if (is_candidate_logged()) {
    redirect('quiz.php');
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $roll = sanitize_input($_POST['roll_no'] ?? '');
    $pass = $_POST['password'] ?? '';
    if ($roll === '' || $pass === '') {
        $error = 'Please fill in all fields.';
    } else {
        $stmt = db()->prepare("SELECT id, roll_no, name, password_hash FROM candidates WHERE roll_no = ?");
        $stmt->execute([$roll]);
        $cand = $stmt->fetch();
        if ($cand && password_verify($pass, $cand['password_hash'])) {
            session_regenerate_id(true);
            $_SESSION['candidate_id'] = $cand['id'];
            $_SESSION['candidate_roll'] = $cand['roll_no'];
            $_SESSION['candidate_name'] = $cand['name'];
            redirect('quiz.php');
        } else {
            $error = 'Invalid roll number or password.';
        }
    }
}

candidate_header('Login');
?>
<div class="auth-container">
    <div class="auth-card">
        <h2>Candidate Login</h2>
        <?php if ($error): ?>
            <div class="alert alert-danger"><?= e($error) ?></div>
        <?php endif; ?>
        <form method="POST" action="">
            <div class="form-group">
                <label for="roll_no">Roll Number</label>
                <input type="text" id="roll_no" name="roll_no" required autofocus value="<?= e($_POST['roll_no'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required>
            </div>
            <button type="submit" class="btn btn-primary btn-block">Login</button>
        </form>
        <p class="auth-link">Don't have an account? <a href="register.php">Register here</a></p>
    </div>
</div>
<?php candidate_footer(); ?>
