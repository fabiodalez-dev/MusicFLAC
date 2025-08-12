<?php
// Configuration file for MusicFLAC

// Directory for temporary downloads
define('DOWNLOAD_DIR', __DIR__ . '/../downloads/');

// Directory for cached files
define('CACHE_DIR', __DIR__ . '/../cache/');

// Maximum time to keep downloaded files (in seconds)
define('DOWNLOAD_EXPIRY_TIME', 3600); // 10 minutes

// Supported services
define('SUPPORTED_SERVICES', [
    'tidal' => 'Tidal',
    'qobuz' => 'Qobuz',
    // 'deezer' => 'Deezer', // disabled for now
    'amazon' => 'Amazon Music'
]);

// Service endpoints
define('TIDAL_API_URL', 'https://api.tidal.com/v1');
define('QOBUZ_API_URL', 'https://www.qobuz.com/api.json/0.2');
// define('DEEZER_API_URL', 'https://api.deezer.com'); // disabled for now
define('AMAZON_API_URL', 'https://music.amazon.com');

// User agent for requests
define('USER_AGENT', 'MusicFLAC/1.0 (https://musicflac.example.com)');

// Debug log file (in php folder)
define('DEBUG_LOG_FILE', __DIR__ . '/../debug.log');

if (!function_exists('debug_log')) {
    function debug_log($message, array $context = []): void {
        static $enabled = null;
        if ($enabled === null) {
            try {
                // Only write when backend debug is enabled in settings
                if (function_exists('app_get_setting')) {
                    $enabled = ((int)app_get_setting('backend_debug', 0) === 1);
                } else {
                    $enabled = false;
                }
            } catch (Throwable $e) {
                $enabled = false;
            }
        }
        if (!$enabled) return;

        $line = [
            'ts' => date('c'),
            'script' => basename($_SERVER['SCRIPT_NAME'] ?? ''),
            'message' => $message,
            'context' => $context,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? ''
        ];
        $json = json_encode($line, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        @file_put_contents(DEBUG_LOG_FILE, $json . PHP_EOL, FILE_APPEND);
    }
}

// Create directories if they don't exist
if (!is_dir(DOWNLOAD_DIR)) {
    mkdir(DOWNLOAD_DIR, 0755, true);
}

if (!is_dir(CACHE_DIR)) {
    mkdir(CACHE_DIR, 0755, true);
}

// Clean up old downloads
function cleanup_old_downloads() {
    $download_dir = DOWNLOAD_DIR;
    if (is_dir($download_dir)) {
        $files = glob($download_dir . '*');
        foreach ($files as $file) {
            if (is_file($file) && (time() - filemtime($file)) > DOWNLOAD_EXPIRY_TIME) {
                unlink($file);
            }
        }
    }
    
    // Clean up cache directory as well
    $cache_dir = CACHE_DIR;
    if (is_dir($cache_dir)) {
        // For demo purposes, we'll clean cache files older than 1 hour
        $files = glob($cache_dir . '*');
        foreach ($files as $file) {
            if (is_file($file) && (time() - filemtime($file)) > 3600) {
                unlink($file);
            }
        }
    }
}

// Run cleanup
cleanup_old_downloads();
// Intentionally no closing PHP tag to avoid accidental output
