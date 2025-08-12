<?php
// Health check endpoint
header('Content-Type: application/json');

// Check if required directories are writable
$downloads_writable = is_writable(__DIR__ . '/downloads');
$cache_writable = is_writable(__DIR__ . '/cache');

// Check if required PHP extensions are loaded
$extensions = ['session', 'json', 'curl', 'fileinfo'];
$missing_extensions = [];
foreach ($extensions as $ext) {
    if (!extension_loaded($ext)) {
        $missing_extensions[] = $ext;
    }
}

// Check PHP version
$php_version_ok = version_compare(PHP_VERSION, '7.4.0', '>=');

$healthy = $downloads_writable && $cache_writable && empty($missing_extensions) && $php_version_ok;

http_response_code($healthy ? 200 : 503);

echo json_encode([
    'status' => $healthy ? 'healthy' : 'unhealthy',
    'timestamp' => date('c'),
    'checks' => [
        'php_version' => [
            'status' => $php_version_ok ? 'ok' : 'fail',
            'message' => $php_version_ok ? 'PHP version is sufficient' : 'PHP version is too old'
        ],
        'downloads_directory' => [
            'status' => $downloads_writable ? 'ok' : 'fail',
            'message' => $downloads_writable ? 'Downloads directory is writable' : 'Downloads directory is not writable'
        ],
        'cache_directory' => [
            'status' => $cache_writable ? 'ok' : 'fail',
            'message' => $cache_writable ? 'Cache directory is writable' : 'Cache directory is not writable'
        ],
        'required_extensions' => [
            'status' => empty($missing_extensions) ? 'ok' : 'fail',
            'message' => empty($missing_extensions) ? 'All required extensions are loaded' : 'Missing extensions: ' . implode(', ', $missing_extensions)
        ]
    ]
]);
?>