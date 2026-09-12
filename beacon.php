<?php
require_once __DIR__ . '/includes/helpers.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $attempt_id = (int)($_POST['attempt_id'] ?? 0);
    $kind = sanitize_input($_POST['kind'] ?? '');

    $allowed = [
        'WindowBlur', 'TabHidden', 'DevToolsShortcut', 'F12', 'RefreshAttempt',
        'TabShortcut', 'AltNav', 'RightClick', 'CopyPaste_copy', 'CopyPaste_cut',
        'CopyPaste_paste', 'PrintScreen', 'DevTools', 'FullscreenExit',
    ];

    if ($attempt_id && $kind && in_array($kind, $allowed, true) && !empty($_SESSION['candidate_id'])) {
        $cid = $_SESSION['candidate_id'];
        $check = db()->prepare("SELECT id FROM attempts WHERE id = ? AND candidate_id = ?");
        $check->execute([$attempt_id, $cid]);
        if ($check->fetch()) {
            db()->prepare("INSERT INTO attempt_violations (attempt_id, kind) VALUES (?, ?)")
                ->execute([$attempt_id, $kind]);
        }
    }
}

http_response_code(200);
echo json_encode(['ok' => true]);
