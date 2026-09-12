<?php
define('DB_HOST', 'localhost');
define('DB_NAME', 'codequest');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

define('APP_NAME', 'CodeQuest');
define('APP_VERSION', '1.0.0');
define('APP_ROOT', dirname(__FILE__));
define('ASSETS_URL', 'assets');
define('UPLOAD_DIR', APP_ROOT . '/uploads');

define('SESSION_LIFETIME', 5400);
define('MAX_FILE_SIZE', 5 * 1024 * 1024);
define('MAX_IMPORT_ROWS', 5000);
define('DEFAULT_PASS_PERCENT', 40);
define('DEFAULT_ADMIN_USER', 'admin');
define('DEFAULT_ADMIN_PASS', 'admin123');

date_default_timezone_set('Asia/Kolkata');

function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    }
    return $pdo;
}
