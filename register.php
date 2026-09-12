<?php
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/layout.php';

if (is_candidate_logged()) {
    redirect('quiz.php');
}

$error = '';
$success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $roll = sanitize_input($_POST['roll_no'] ?? '');
    $name = sanitize_input($_POST['name'] ?? '');
    $email = sanitize_input($_POST['email'] ?? '');
    $pass = $_POST['password'] ?? '';
    $pass2 = $_POST['password_confirm'] ?? '';

    if ($roll === '' || $name === '' || $pass === '') {
        $error = 'Roll number, name, and password are required.';
    } elseif ($pass !== $pass2) {
        $error = 'Passwords do not match.';
    } elseif (strlen($pass) < 6) {
        $error = 'Password must be at least 6 characters.';
    } else {
        $hash = password_hash($pass, PASSWORD_BCRYPT);
        $stmt = db()->prepare("INSERT INTO candidates (roll_no, name, email, password_hash) VALUES (?, ?, ?, ?)");
        try {
            $stmt->execute([$roll, $name, $email ?: null, $hash]);
            $success = 'Registration successful! You can now log in.';
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) {
                $error = 'Roll number already exists.';
            } else {
                $error = 'Registration failed: ' . $e->getMessage();
            }
        }
    }
}

candidate_header('Register');
?>
<div class="auth-container">
    <div class="auth-card">
        <h2>Candidate Registration</h2>
        <?php if ($error): ?>
            <div class="alert alert-danger"><?= e($error) ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success"><?= e($success) ?></div>
        <?php endif; ?>
        <form method="POST" action="">
            <div class="form-group">
                <label for="roll_no">Roll Number *</label>
                <input type="text" id="roll_no" name="roll_no" required value="<?= e($_POST['roll_no'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label for="name">Full Name *</label>
                <input type="text" id="name" name="name" required value="<?= e($_POST['name'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label for="email">Email (optional)</label>
                <input type="email" id="email" name="email" value="<?= e($_POST['email'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label for="password">Password *</label>
                <input type="password" id="password" name="password" required minlength="6">
            </div>
            <div class="form-group">
                <label for="password_confirm">Confirm Password *</label>
                <input type="password" id="password_confirm" name="password_confirm" required>
            </div>
            <button type="submit" class="btn btn-primary btn-block">Register</button>
        </form>
        <p class="auth-link">Already have an account? <a href="index.php">Login here</a></p>
    </div>
</div>
<?php candidate_footer(); ?>
