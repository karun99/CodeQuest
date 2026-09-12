<?php
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/layout.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $keys = ['block_shortcuts', 'block_right_click', 'block_copy_paste', 'block_devtools', 'blur_on_blur', 'fullscreen_required', 'single_tab', 'autosubmit_on_switch'];
    foreach ($keys as $k) {
        set_setting($k, isset($_POST[$k]) ? '1' : '0');
    }
    set_flash('success', 'Settings saved.');
    redirect('settings.php');
}

$settings = [];
$sr = db()->query("SELECT k, v FROM settings");
while ($s = $sr->fetch()) $settings[$s['k']] = $s['v'];

$toggles = [
    'block_shortcuts' => ['Block Keyboard Shortcuts', 'Prevents Ctrl+C/V/X/A/S/P/U, F12, F5, Ctrl+Tab, etc.'],
    'block_right_click' => ['Disable Right-Click', 'Prevents the context menu.'],
    'block_copy_paste' => ['Block Copy/Paste', 'Prevents copy/cut/paste events and text selection.'],
    'block_devtools' => ['Detect DevTools', 'Warns when DevTools is suspected open.'],
    'blur_on_blur' => ['Blur Overlay', 'Shows a full-screen pause overlay when window loses focus.'],
    'fullscreen_required' => ['Require Fullscreen', 'Requests fullscreen on first click during exam.'],
    'single_tab' => ['Single-Tab Lock', 'Enforces one exam tab per attempt using BroadcastChannel + heartbeat.'],
    'autosubmit_on_switch' => ['Auto-Submit on Tab Switch', 'Auto-submits the attempt when the tab is hidden.'],
];

admin_header('Settings');
?>
<form method="POST">
    <?= csrf_field() ?>
    <table class="data-table settings-table">
        <thead><tr><th>Control</th><th>Description</th><th>Enabled</th></tr></thead>
        <tbody>
        <?php foreach ($toggles as $key => [$label, $desc]): ?>
            <tr>
                <td><strong><?= e($label) ?></strong></td>
                <td class="text-muted"><?= e($desc) ?></td>
                <td class="center"><input type="checkbox" name="<?= $key ?>" value="1" <?= ($settings[$key] ?? '0') === '1' ? 'checked' : '' ?>></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <button type="submit" class="btn btn-primary">Save Settings</button>
</form>
<?php admin_footer(); ?>