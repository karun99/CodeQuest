<?php
require_once __DIR__ . '/includes/helpers.php';
$_SESSION = [];
session_destroy();
header('Location: index.php');
exit;
