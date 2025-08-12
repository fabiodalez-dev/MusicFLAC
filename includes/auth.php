<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/app.php';

function app_csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['csrf_token'];
}

function app_verify_csrf(string $token): bool {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function require_frontend_auth(): void {
    // Check if user is logged in with new system
    if (user_is_logged_in()) return;
    
    // Fallback: Check old session-based auth
    if (!empty($_SESSION['frontend_auth'])) return;
    
    // Redirect to login page
    $current_url = urlencode($_SERVER['REQUEST_URI'] ?? '/');
    header('Location: login.php?redirect=' . $current_url);
    exit;
}

function require_admin_auth(): void {
    // If application is not installed, redirect to installer
    if (function_exists('app_is_installed') && !app_is_installed()) {
        $installUrl = function_exists('base_url') ? base_url('installer/install.php') : '../installer/install.php';
        header('Location: ' . $installUrl);
        exit;
    }
    // Check if user is logged in and is admin
    if (user_is_logged_in() && user_is_admin()) return;
    
    // Fallback: Check old session-based auth
    if (!empty($_SESSION['admin_auth'])) return;
    
    // Redirect to login page
    $current_url = urlencode($_SERVER['REQUEST_URI'] ?? '/admin/index.php');
    header('Location: ../login.php?redirect=' . $current_url);
    exit;
}

// Intentionally no closing PHP tag to avoid accidental output
