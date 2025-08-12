<?php
@ini_set('display_errors', '0');
error_reporting(E_ALL);
require_once __DIR__ . '/includes/bootstrap.php';
debug_log('serve_request', ['f' => $_GET['f'] ?? '']);

// Require frontend auth
require_frontend_auth();

$f = $_GET['f'] ?? '';
if (!$f) {
    http_response_code(400);
    echo 'Missing file';
    exit;
}

// Prevent directory traversal
$name = basename($f);
$path = realpath(DOWNLOAD_DIR . $name);
if (!$path || strpos($path, realpath(DOWNLOAD_DIR)) !== 0 || !is_file($path)) {
    http_response_code(404);
    debug_log('serve_not_found', ['path' => $path]);
    echo 'File not found';
    exit;
}

$ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
$ctype = 'application/octet-stream';
if ($ext === 'zip') $ctype = 'application/zip';
if ($ext === 'flac') $ctype = 'audio/flac';

header('Content-Description: File Transfer');
header('Content-Type: ' . $ctype);
header('Content-Disposition: attachment; filename="' . $name . '"');
header('Expires: 0');
header('Cache-Control: must-revalidate');
header('Pragma: public');
header('Content-Length: ' . filesize($path));
readfile($path);

// Delete the file after sending
@unlink($path);
debug_log('serve_done', ['name' => $name]);
?>
