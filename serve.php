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
// Rate limiting check (max 10 downloads per minute per IP)
$ip = $_SERVER['REMOTE_ADDR'] ?? '';
if ($ip) {
    $rate_key = 'download_rate_' . md5($ip);
    $rate_file = sys_get_temp_dir() . '/' . $rate_key;
    $current_time = time();
    $requests = [];
    if (file_exists($rate_file)) {
        $requests = json_decode(file_get_contents($rate_file), true) ?: [];
    }
    // Remove requests older than 1 minute
    $requests = array_filter($requests, function($time) use ($current_time) {
        return ($current_time - $time) < 60;
    });
    if (count($requests) >= 10) {
        http_response_code(429);
        echo 'Too many requests';
        exit;
    }
    $requests[] = $current_time;
    file_put_contents($rate_file, json_encode($requests));
}

// Enhanced path traversal protection
$name = basename($f);
// Additional sanitization to prevent bypass attempts
$name = str_replace(['..', '/', '\\', ':', '*', '?', '"', '<', '>', '|'], '', $name);
if (empty($name) || strlen($name) > 255) {
    http_response_code(400);
    echo 'Invalid filename';
    exit;
}
$path = realpath(DOWNLOAD_DIR . $name);
if (!$path || strpos($path, realpath(DOWNLOAD_DIR)) !== 0 || !is_file($path)) {
    http_response_code(404);
    debug_log('serve_not_found', ['path' => $path, 'name' => $name]);
    echo 'File not found';
    exit;
}
// Additional security check: ensure file is within expected directory
if (dirname($path) !== realpath(DOWNLOAD_DIR)) {
    http_response_code(403);
    debug_log('serve_forbidden_dir', ['path' => $path]);
    echo 'Forbidden';
    exit;
}

// Allowlist extensions to prevent arbitrary file download
$ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
$allowed = ['zip' => 'application/zip', 'flac' => 'audio/flac'];
if (!isset($allowed[$ext])) {
    http_response_code(403);
    debug_log('serve_forbidden_ext', ['ext' => $ext, 'name' => $name]);
    echo 'Forbidden';
    exit;
}
// Choose content type
$ctype = $allowed[$ext];

// Security headers
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: no-referrer');
header('Content-Security-Policy: default-src \"none\"');
header('X-XSS-Protection: 1; mode=block');
header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload');
// Download headers
header('Content-Description: File Transfer');
header('Content-Type: ' . $ctype);
// Sanitize filename to prevent header injection
$safe_name = preg_replace('/[^\\w\\s\\-\\.]/u', '', $name);
header('Content-Disposition: attachment; filename="' . $safe_name . '"');
header('Expires: 0');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
// Verify file size limit (100MB max)
if (filesize($path) > 104857600) {
    http_response_code(413);
    echo 'File too large';
    exit;
}
header('Content-Length: ' . filesize($path));
readfile($path);

// Delete the file after sending
@unlink($path);
debug_log('serve_done', ['name' => $name]);
?>
