<?php
// Check if MusiFLAC is installed (using app helper)
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: application/json');

define('DATA_DIR', __DIR__ . '/../data');
require_once __DIR__ . '/../includes/app.php';

$dbFile = DATA_DIR . '/app.sqlite';
$installed = app_is_installed();

if ($installed) {
    try {
        $db = new PDO('sqlite:' . $dbFile);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $adminCount = (int)$db->query("SELECT COUNT(*) FROM users WHERE is_admin = 1")->fetchColumn();
    } catch (Throwable $e) {
        $adminCount = 0;
    }
    echo json_encode(['installed' => true, 'admin_users' => $adminCount]);
    exit;
}

// Not installed: provide a concise reason for troubleshooting
if (!file_exists($dbFile)) {
    echo json_encode(['installed' => false, 'reason' => 'Database file not found']);
    exit;
}

if (filesize($dbFile) === 0) {
    echo json_encode(['installed' => false, 'reason' => 'Database file is empty']);
    exit;
}

try {
    $db = new PDO('sqlite:' . $dbFile);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $hasUsers = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='users'")->fetchColumn();
    if (!$hasUsers) {
        echo json_encode(['installed' => false, 'reason' => 'Users table not found']);
        exit;
    }
    $admins = (int)$db->query("SELECT COUNT(*) FROM users WHERE is_admin = 1")->fetchColumn();
    if ($admins <= 0) {
        echo json_encode(['installed' => false, 'reason' => 'No admin users found']);
        exit;
    }
    // Fallback, but app_is_installed would have been true in this case
    echo json_encode(['installed' => false, 'reason' => 'Unknown state']);
} catch (Throwable $e) {
    echo json_encode(['installed' => false, 'reason' => 'Database error: ' . $e->getMessage()]);
}
