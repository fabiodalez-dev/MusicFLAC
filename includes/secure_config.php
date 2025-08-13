<?php
// Secure PHP configuration settings
// Include this file early in bootstrap to apply security settings

// Check for critical dangerous functions only (not those needed by the app)
$critical_dangerous_functions = [
    'eval', 'create_function'  // Only truly dangerous ones
];

// Check if any critical dangerous functions are available and log warning
foreach ($critical_dangerous_functions as $func) {
    if (function_exists($func)) {
        error_log("[SECURITY CRITICAL] Dangerous function '$func' is available and should be disabled");
    }
}

// Check assert separately (less critical)
if (function_exists('assert')) {
    error_log("[SECURITY INFO] 'assert' function available (consider disabling in production)");
}

// Log other potentially risky functions as info (not warnings)
$risky_functions = ['exec', 'system', 'shell_exec', 'passthru', 'proc_open', 'popen'];
$risky_available = [];
foreach ($risky_functions as $func) {
    if (function_exists($func)) {
        $risky_available[] = $func;
    }
}
if (!empty($risky_available)) {
    error_log("[SECURITY INFO] Command execution functions available: " . implode(', ', $risky_available) . " (protected by app security layers)");
}

// Security-focused PHP settings
@ini_set('display_errors', '0');
@ini_set('display_startup_errors', '0');
@ini_set('log_errors', '1');
@ini_set('error_reporting', E_ALL & ~E_NOTICE & ~E_DEPRECATED);

// Session security  
@ini_set('session.cookie_httponly', '1');
// Determine HTTPS status safely
$is_https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
            (stripos($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '', 'https') !== false) ||
            (strtolower($_SERVER['REQUEST_SCHEME'] ?? '') === 'https');
@ini_set('session.cookie_secure', $is_https ? '1' : '0');
@ini_set('session.cookie_samesite', 'Lax');
@ini_set('session.use_strict_mode', '1');
@ini_set('session.use_only_cookies', '1');
@ini_set('session.use_trans_sid', '0');
@ini_set('session.entropy_length', '32');
@ini_set('session.hash_function', 'sha256');
@ini_set('session.name', 'PHPSESSID_SECURE');

// File upload restrictions
@ini_set('file_uploads', '0'); // Disable file uploads entirely
@ini_set('upload_max_filesize', '1M');
@ini_set('post_max_size', '2M');
@ini_set('max_file_uploads', '1');

// Script execution limits
@ini_set('max_execution_time', '30');
@ini_set('max_input_time', '60');
@ini_set('memory_limit', '64M');

// URL and remote file access
@ini_set('allow_url_fopen', '1'); // We need this for HTTP requests but will validate URLs
@ini_set('allow_url_include', '0');
@ini_set('auto_prepend_file', '');
@ini_set('auto_append_file', '');

// Disable potentially dangerous features
@ini_set('enable_dl', '0');
@ini_set('expose_php', '0');
@ini_set('register_globals', '0');
@ini_set('magic_quotes_gpc', '0');

// SQL injection protection
@ini_set('sql.safe_mode', '1');

// Open basedir restriction (if possible) - Temporarily disabled due to hosting restrictions
// Note: Some hosting providers already set open_basedir restrictions
/*
if (function_exists('ini_set')) {
    $basedir = dirname(__DIR__) . PATH_SEPARATOR . sys_get_temp_dir();
    @ini_set('open_basedir', $basedir);
}
*/

// Custom error handler to prevent information disclosure
function secure_error_handler($errno, $errstr, $errfile, $errline) {
    // Log the error securely
    $error_msg = "[ERROR] Type: $errno, Message: $errstr, File: " . basename($errfile) . ", Line: $errline";
    error_log($error_msg);
    
    // Don't display errors to users in production
    if ((int)app_get_setting('frontend_debug', 0) !== 1) {
        return true; // Don't display error
    }
    
    return false; // Use default error handling in debug mode
}

// Custom exception handler
function secure_exception_handler($exception) {
    $error_msg = "[EXCEPTION] " . $exception->getMessage() . " in " . basename($exception->getFile()) . ":" . $exception->getLine();
    error_log($error_msg);
    
    // Show generic error message to user
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/html; charset=UTF-8');
    }
    
    echo '<!DOCTYPE html><html><head><title>Error</title></head><body>';
    echo '<h1>An error occurred</h1>';
    echo '<p>The application encountered an unexpected error. Please try again later.</p>';
    echo '</body></html>';
    exit;
}

// Custom fatal error handler
function secure_fatal_error_handler() {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE])) {
        $error_msg = "[FATAL] Type: " . $error['type'] . ", Message: " . $error['message'] . ", File: " . basename($error['file']) . ", Line: " . $error['line'];
        error_log($error_msg);
        
        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: text/html; charset=UTF-8');
        }
        
        echo '<!DOCTYPE html><html><head><title>Fatal Error</title></head><body>';
        echo '<h1>Application Error</h1>';
        echo '<p>A critical error occurred. Please contact the administrator.</p>';
        echo '</body></html>';
    }
}

// Set error handlers (check if app functions are available)
if (function_exists('app_get_setting')) {
    $debug_mode = false;
    try {
        $debug_mode = (int)app_get_setting('frontend_debug', 0) === 1;
    } catch (Exception $e) {
        $debug_mode = false;
    }
    
    if (!$debug_mode) {
        set_error_handler('secure_error_handler');
        set_exception_handler('secure_exception_handler');
        register_shutdown_function('secure_fatal_error_handler');
    }
}

// Additional security measures
if (function_exists('header_remove')) {
    header_remove('X-Powered-By');
    header_remove('Server');
}

// Set secure default timezone
if (!ini_get('date.timezone')) {
    date_default_timezone_set('UTC');
}

// Intentionally no closing PHP tag to avoid accidental output