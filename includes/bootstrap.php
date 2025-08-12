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

// No closing tag to avoid output
