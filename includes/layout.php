<?php
require_once dirname(__DIR__) . '/includes/helpers.php';

function admin_header(string $title = ''): void {
    $title = $title ? $title . ' - ' . APP_NAME : APP_NAME . ' Admin';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($title) ?></title>
    <link rel="stylesheet" href="../<?= ASSETS_URL ?>/css/style.css">
</head>
<body>
<div class="admin-layout">
    <nav class="sidebar">
        <div class="sidebar-brand"><?= e(APP_NAME) ?></div>
        <ul>
            <li><a href="index.php">Dashboard</a></li>
            <li><a href="questions.php">Questions</a></li>
            <li><a href="case_studies.php">Case Studies</a></li>
            <li><a href="exams.php">Exams</a></li>
            <li><a href="candidates.php">Candidates</a></li>
            <li><a href="reports.php">Reports</a></li>
            <li><a href="settings.php">Settings</a></li>
            <li><a href="export.php">Export</a></li>
            <li><a href="import.php">Import</a></li>
            <li><a href="logout.php">Logout</a></li>
        </ul>
    </nav>
    <main class="content">
        <h1><?= e($title) ?></h1>
        <?= flash_html() ?>
<?php
}

function admin_footer(): void {
?>
    </main>
</div>
</body>
</html>
<?php
}

function candidate_header(string $title = ''): void {
    $title = $title ? $title . ' - ' . APP_NAME : APP_NAME;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($title) ?></title>
    <link rel="stylesheet" href="<?= ASSETS_URL ?>/css/style.css">
</head>
<body>
<div class="candidate-layout">
    <nav class="topbar">
        <span class="brand"><?= e(APP_NAME) ?></span>
        <?php if (is_candidate_logged()): ?>
        <span class="user-info"><?= e($_SESSION['candidate_name'] ?? '') ?></span>
        <?php endif; ?>
    </nav>
    <main class="content">
        <?= flash_html() ?>
<?php
}

function candidate_footer(): void {
?>
    </main>
</div>
</body>
</html>
<?php
}
