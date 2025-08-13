<?php
// Central bootstrap for session, core includes, and security defaults

// Harden session configuration before starting session
@ini_set('session.use_only_cookies', '1');
@ini_set('session.use_strict_mode', '1');
@ini_set('session.use_trans_sid', '0');
@ini_set('session.cookie_httponly', '1');

// Ensure session started once
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Core app and auth (app also loads config + version and reissues secure cookie)
require_once __DIR__ . '/app.php';
require_once __DIR__ . '/auth.php';

// Essential functions and services
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/spotify.php';

// Apply secure configuration after app functions are available
require_once __DIR__ . '/secure_config.php';
require_once __DIR__ . '/security.php';

// If app is not installed, redirect all pages (except installer) to installer
try {
    if (!app_is_installed()) {
        $script = realpath($_SERVER['SCRIPT_FILENAME'] ?? '');
        $installerDir = realpath(__DIR__ . '/../installer');
        $isInstaller = ($script && $installerDir && strpos($script, $installerDir) === 0);
        if (!$isInstaller) {
            $dest = base_url('installer/install.php');
            if (!headers_sent()) header('Location: ' . $dest);
            exit;
        }
    }
} catch (Throwable $e) {
    // On any failure, allow flow; individual pages may handle redirects
}

// Optionally, set default timezone if not set elsewhere
if (!ini_get('date.timezone')) {
    @date_default_timezone_set('UTC');
}

// Prevent caching of authenticated pages by proxies/browsers
if (!headers_sent()) {
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: 0');
}

// Global security headers (applies to all HTML responses)
if (!headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('X-XSS-Protection: 1; mode=block');
    header('Referrer-Policy: no-referrer');
    header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload');
    header('X-Robots-Tag: noindex, nofollow, nosnippet, noarchive');
    // Conservative CSP compatible with current external resources
    // Allows: self + jsdelivr/cdnjs scripts, Google Fonts styles, fonts.gstatic fonts, data: images
    $csp = [];
    $csp[] = "default-src 'self'";
    $csp[] = "script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com https://cdn.tailwindcss.com";
    $csp[] = "style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://fonts.googleapis.com https://cdnjs.cloudflare.com";
    $csp[] = "img-src 'self' data: blob: https:";
    $csp[] = "font-src 'self' https://fonts.gstatic.com https://cdnjs.cloudflare.com https://cdn.jsdelivr.net data:";
    $csp[] = "connect-src 'self'";
    $csp[] = "object-src 'none'";
    $csp[] = "base-uri 'self'";
    $csp[] = "form-action 'self'";
    $csp[] = "frame-ancestors 'none'";
    $csp[] = "upgrade-insecure-requests";
    $csp[] = "block-all-mixed-content";
    header('Content-Security-Policy: ' . implode('; ', $csp));
    header('Permissions-Policy: microphone=(), camera=(), geolocation=(), payment=(), usb=(), bluetooth=(), magnetometer=(), gyroscope=(), accelerometer=()');
}

// Production mode: block installer access when installed
try {
    if (app_is_installed()) {
        $prod = (int)app_get_setting('production_mode', 0) === 1;
        if ($prod) {
            $script = realpath($_SERVER['SCRIPT_FILENAME'] ?? '') ?: '';
            $installerDir = realpath(__DIR__ . '/../installer') ?: '';
            if ($script && $installerDir && strpos($script, $installerDir) === 0) {
                // Log access attempt
                $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
                $ua = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
                error_log("[SECURITY] Blocked installer access from IP: {$ip}, UA: {$ua}");
                if (!headers_sent()) {
                    header('HTTP/1.1 403 Forbidden');
                    header('Cache-Control: no-store');
                }
                exit('Installer disabled (production mode)');
            }
        }
    }
} catch (Throwable $e) { /* ignore */ }

// Additional security checks
if (isset($_SERVER['REQUEST_URI'])) {
    $uri = $_SERVER['REQUEST_URI'];
    // Block common exploit patterns
    $patterns = ['../', '..\\', 'php://', 'data://', 'expect://', 'zip://', 'phar://', '/proc/', '/etc/'];
    foreach ($patterns as $pattern) {
        if (stripos($uri, $pattern) !== false) {
            $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
            error_log("[SECURITY] Blocked malicious URI from IP: {$ip}, URI: {$uri}");
            http_response_code(403);
            exit('Access denied');
        }
    }
}

// No closing tag to avoid output
