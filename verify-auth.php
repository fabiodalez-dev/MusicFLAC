<?php
// Authentication system verification script
@ini_set('display_errors', '1');
error_reporting(E_ALL);

require_once 'includes/bootstrap.php';

echo "<h2>MusicFLAC Authentication System Verification</h2>\n";

try {
    $db = app_db();
    echo "<p>✅ Database connection: OK</p>\n";
    
    // Check tables exist
    $tables = ['users', 'downloads', 'settings', 'services'];
    foreach ($tables as $table) {
        $result = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='$table'");
        if ($result->fetch()) {
            echo "<p>✅ Table '$table': EXISTS</p>\n";
        } else {
            echo "<p>❌ Table '$table': MISSING</p>\n";
        }
    }
    
    // Check admin users
    $stmt = $db->prepare('SELECT id, username, email, is_active, is_admin FROM users WHERE is_admin = 1');
    $stmt->execute();
    $admins = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<p>Admin users found: " . count($admins) . "</p>\n";
    foreach ($admins as $admin) {
        $status = $admin['is_active'] ? 'Active' : 'Inactive';
        echo "<p>👤 Admin: {$admin['username']} ({$admin['email']}) - $status</p>\n";
    }
    
    // Check total users
    $totalUsers = (int)$db->query('SELECT COUNT(*) FROM users')->fetchColumn();
    $activeUsers = (int)$db->query('SELECT COUNT(*) FROM users WHERE is_active = 1')->fetchColumn();
    echo "<p>Total users: $totalUsers (Active: $activeUsers)</p>\n";
    
    // Check authentication functions
    $functions = ['user_login', 'user_register', 'user_logout', 'user_is_logged_in', 'user_is_admin'];
    foreach ($functions as $func) {
        if (function_exists($func)) {
            echo "<p>✅ Function '$func': EXISTS</p>\n";
        } else {
            echo "<p>❌ Function '$func': MISSING</p>\n";
        }
    }
    
    // Check auth files
    $files = ['login.php', 'signup.php', 'logout.php', 'forgot-password.php', 'reset-password.php'];
    foreach ($files as $file) {
        if (file_exists($file)) {
            echo "<p>✅ File '$file': EXISTS</p>\n";
        } else {
            echo "<p>❌ File '$file': MISSING</p>\n";
        }
    }
    
    // No hardcoded credentials: only report status
    echo "<h3>Login Check</h3>\n";
    if (count($admins) > 0) {
        echo "<p>✅ Admin account present. Use your configured credentials.</p>\n";
    } else {
        echo "<p>❌ No admin accounts found. Run the installer to create the first admin.</p>\n";
    }
    
} catch (Exception $e) {
    echo "<p>❌ Error: " . htmlspecialchars($e->getMessage()) . "</p>\n";
}

echo "<h3>System Status Summary</h3>\n";
echo "<p>If all items above show ✅, the authentication system is properly configured.</p>\n";
echo "<p>Admin credentials are set during installation. No defaults are embedded in code.</p>\n";
echo "<p><a href='login.php'>Go to Login Page</a></p>\n";
?>
