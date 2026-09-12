<?php
session_start();
require_once dirname(__DIR__) . '/config.php';

function e(string $str): string {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}

function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string {
    return '<input type="hidden" name="csrf_token" value="' . csrf_token() . '">';
}

function verify_csrf(): bool {
    $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    return hash_equals(csrf_token(), $token);
}

function require_csrf(): void {
    if (!verify_csrf()) {
        http_response_code(403);
        die('CSRF token invalid.');
    }
}

function redirect(string $url): void {
    header('Location: ' . $url);
    exit;
}

function set_flash(string $type, string $msg): void {
    $_SESSION['flash'] = ['type' => $type, 'msg' => $msg];
}

function get_flash(): ?array {
    if (isset($_SESSION['flash'])) {
        $f = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $f;
    }
    return null;
}

function is_admin_logged(): bool {
    return !empty($_SESSION['admin_id']);
}

function is_candidate_logged(): bool {
    return !empty($_SESSION['candidate_id']);
}

function require_admin(): void {
    if (!is_admin_logged()) {
        redirect('login.php');
    }
}

function require_candidate(): void {
    if (!is_candidate_logged()) {
        redirect('index.php');
    }
}

function get_setting(string $key, string $default = ''): string {
    static $cache = [];
    if (empty($cache)) {
        $stmt = db()->query("SELECT k, v FROM settings");
        while ($row = $stmt->fetch()) {
            $cache[$row['k']] = $row['v'];
        }
    }
    return $cache[$key] ?? $default;
}

function set_setting(string $key, string $val): void {
    $stmt = db()->prepare("INSERT INTO settings (k, v) VALUES (?, ?) ON DUPLICATE KEY UPDATE v = ?");
    $stmt->execute([$key, $val, $val]);
}

function flash_html(): string {
    $f = get_flash();
    if (!$f) return '';
    $cls = $f['type'] === 'error' ? 'alert-danger' : 'alert-success';
    return '<div class="alert ' . $cls . '">' . e($f['msg']) . '</div>';
}

function sanitize_input(string $val): string {
    return trim($val);
}

function json_response(array $data, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}
