<?php
require_once __DIR__ . '/config.php';

$message = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $dsn = 'mysql:host=' . DB_HOST . ';charset=' . DB_CHARSET;
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);

        $pdo->exec("CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $pdo->exec("USE `" . DB_NAME . "`");

        $schema = file_get_contents(__DIR__ . '/schema.sql');
        $pdo->exec($schema);

        $hash = password_hash(DEFAULT_ADMIN_PASS, PASSWORD_BCRYPT);
        $stmt = $pdo->prepare("INSERT IGNORE INTO admins (username, password_hash, full_name) VALUES (?, ?, ?)");
        $stmt->execute([DEFAULT_ADMIN_USER, $hash, 'Administrator']);

        $settings = [
            ['block_shortcuts', '1'],
            ['block_right_click', '1'],
            ['block_copy_paste', '1'],
            ['block_devtools', '1'],
            ['blur_on_blur', '1'],
            ['fullscreen_required', '0'],
            ['single_tab', '1'],
            ['autosubmit_on_switch', '0'],
        ];
        $stmt = $pdo->prepare("INSERT IGNORE INTO settings (k, v) VALUES (?, ?)");
        foreach ($settings as $s) {
            $stmt->execute($s);
        }

        $message = 'Installation complete! Default admin: ' . DEFAULT_ADMIN_USER . ' / ' . DEFAULT_ADMIN_PASS;
        $success = true;
    } catch (PDOException $e) {
        $message = 'Installation error: ' . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Install - <?= APP_NAME ?></title>
    <link rel="stylesheet" href="<?= ASSETS_URL ?>/css/style.css">
</head>
<body>
<div class="install-container">
    <h1><?= APP_NAME ?> Installation</h1>
    <p>Click the button below to create the database and default admin account.</p>
    <?php if ($message): ?>
        <div class="alert <?= $success ? 'alert-success' : 'alert-danger' ?>"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
        <a href="index.php" class="btn btn-primary">Go to Login</a>
        <a href="admin/login.php" class="btn btn-secondary">Admin Login</a>
    <?php else: ?>
        <form method="POST">
            <button type="submit" class="btn btn-primary">Install Now</button>
        </form>
    <?php endif; ?>
</div>
</body>
</html>
