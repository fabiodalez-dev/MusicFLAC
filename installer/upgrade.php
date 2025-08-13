<?php
// MusicFLAC Upgrade Script
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

// Generate CSRF token for upgrade form
if (empty($_SESSION['upgrade_csrf'])) {
    $_SESSION['upgrade_csrf'] = bin2hex(random_bytes(16));
}

// Check if user is admin
if (!isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
    header('Location: ../login.php');
    exit;
}

define('DATA_DIR', __DIR__ . '/../data');

// Get current version
$current_version = defined('APP_VERSION') ? APP_VERSION : '1.0.0';

// Available upgrades (in real scenario, this would check remote server)
$available_upgrades = [
    '1.0.1' => 'Minor bug fixes and improvements',
    '1.1.0' => 'New features and performance improvements',
    '2.0.0' => 'Major update with new functionality'
];

// Check if upgrade is needed
$upgrade_needed = false;
$latest_version = $current_version;

foreach ($available_upgrades as $version => $description) {
    if (version_compare($version, $current_version, '>')) {
        $upgrade_needed = true;
        $latest_version = $version;
        break;
    }
}

// Handle upgrade process
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upgrade'])) {
    // CSRF protection
    if (!isset($_POST['csrf']) || !hash_equals($_SESSION['upgrade_csrf'] ?? '', $_POST['csrf'] ?? '')) {
        die('CSRF token mismatch');
    }
    
    $target_version = $_POST['version'] ?? '';
    
    // CRITICAL SECURITY: Strict validation of version parameter
    if (!preg_match('/^[0-9]+\.[0-9]+\.[0-9]+$/', $target_version)) {
        die('Invalid version format');
    }
    
    if (isset($available_upgrades[$target_version])) {
        // In a real scenario, this would download and apply the upgrade
        // For now, we'll just simulate the process
        
        // SECURITY: Validate file path and prevent directory traversal
        $version_file = realpath(__DIR__ . '/../includes') . '/version.php';
        $includes_dir = realpath(__DIR__ . '/../includes');
        
        if (!$includes_dir || strpos($version_file, $includes_dir) !== 0) {
            die('Invalid file path');
        }
        
        // SECURITY: Sanitize content to prevent code injection
        $safe_version = preg_replace('/[^0-9\.]/', '', $target_version);
        $content = "<?php\n// Application version\ndefine('APP_VERSION', " . var_export($safe_version, true) . ");\ndefine('APP_NAME', 'SpotiFLAC');\n";
        
        // SECURITY: Atomic write with proper permissions
        $temp_file = $version_file . '.tmp';
        if (file_put_contents($temp_file, $content, LOCK_EX) === false) {
            die('Failed to write version file');
        }
        
        if (!rename($temp_file, $version_file)) {
            @unlink($temp_file);
            die('Failed to update version file');
        }
        
        // Set secure permissions
        @chmod($version_file, 0644);
        
        // Log security event
        error_log("[SECURITY] Version upgraded to $safe_version by admin user");
        
        // Redirect to show success
        header('Location: ?upgraded=true&version=' . urlencode($safe_version));
        exit;
    } else {
        die('Invalid version selected');
    }
}

$upgraded = isset($_GET['upgraded']) && $_GET['upgraded'] === 'true';
$upgraded_version = $_GET['version'] ?? '';
?>
<!DOCTYPE html>
<html lang=\"it\">
<head>
    <meta charset=\"UTF-8\">
    <meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\">
    <title>SpotiFLAC - Aggiornamento</title>
    <link href=\"https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css\" rel=\"stylesheet\">
    <style>
        body { background: #0b0c0d; }
        .card { background:#0f172a; border:1px solid #1f2937; }
    </style>
</head>
<body class=\"text-white\">
    <div class=\"min-h-screen flex items-center justify-center p-4\">
        <div class=\"max-w-2xl w-full\">
            <div class=\"text-center mb-8\">
                <h1 class=\"text-3xl font-bold text-accent mb-2\">SpotiFLAC</h1>
                <p class=\"text-gray-400\">Aggiornamento dell'applicazione</p>
            </div>
            
            <div class=\"card rounded-xl p-6\">
                <?php if ($upgraded): ?>
                    <div class=\"mb-6 p-4 rounded border border-green-700 bg-green-900 bg-opacity-30 text-green-200\">
                        Aggiornamento alla versione <?= htmlspecialchars($upgraded_version) ?> completato con successo!
                    </div>
                <?php endif; ?>
                
                <h2 class=\"text-xl font-bold mb-4\">Versione corrente: <?= htmlspecialchars($current_version) ?></h2>
                
                <?php if ($upgrade_needed): ?>
                    <p class=\"text-gray-400 mb-6\">È disponibile un aggiornamento per la tua applicazione.</p>
                    
                    <div class=\"bg-gray-800 rounded-lg p-4 mb-6\">
                        <h3 class=\"font-semibold mb-2\">Aggiornamento disponibile:</h3>
                        <div class=\"flex justify-between items-center\">
                            <div>
                                <div class=\"font-mono\"><?= htmlspecialchars($latest_version) ?></div>
                                <div class=\"text-sm text-gray-400\"><?= htmlspecialchars($available_upgrades[$latest_version] ?? 'Aggiornamento') ?></div>
                            </div>
                        </div>
                    </div>
                    
                    <form method=\"post\">
                        <input type=\"hidden\" name=\"csrf\" value=\"<?= htmlspecialchars($_SESSION['upgrade_csrf']) ?>\">
                        <input type=\"hidden\" name=\"version\" value=\"<?= htmlspecialchars($latest_version) ?>\">
                        <button type=\"submit\" name=\"upgrade\" value=\"1\" class=\"w-full px-4 py-3 bg-green-600 hover:bg-green-700 rounded-lg font-semibold\">
                            Esegui aggiornamento
                        </button>
                    </form>
                <?php else: ?>
                    <div class=\"text-center py-8\">
                        <div class=\"text-5xl text-green-500 mb-6\">✓</div>
                        <p class=\"text-gray-400\">La tua applicazione è aggiornata all'ultima versione disponibile.</p>
                    </div>
                <?php endif; ?>
            </div>
            
            <div class=\"text-center mt-6\">
                <a href=\"../admin/index.php\" class=\"text-blue-400 hover:text-blue-300\">← Torna all'area amministrativa</a>
            </div>
        </div>
    </div>
</body>
</html>