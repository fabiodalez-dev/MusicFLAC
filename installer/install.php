<?php
// MusicFLAC Installer
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set timezone
date_default_timezone_set('UTC');

// Define paths
define('INSTALL_DIR', __DIR__);
define('ROOT_DIR', dirname(INSTALL_DIR));
define('DATA_DIR', ROOT_DIR . '/data');
define('DOWNLOADS_DIR', ROOT_DIR . '/downloads');

// Session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// CSRF token for installer
if (empty($_SESSION['installer_csrf'])) {
    $_SESSION['installer_csrf'] = bin2hex(random_bytes(16));
}
$installer_csrf = $_SESSION['installer_csrf'];

// Check if already installed
$alreadyInstalled = false;
$installedAdminCount = 0;
if (file_exists(DATA_DIR . '/app.sqlite') && filesize(DATA_DIR . '/app.sqlite') > 0) {
    try {
        $db = new PDO('sqlite:' . DATA_DIR . '/app.sqlite');
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $stmt = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='users'");
        if ($stmt->fetch()) {
            $stmt = $db->query("SELECT COUNT(*) FROM users WHERE is_admin = 1");
            $installedAdminCount = (int)$stmt->fetchColumn();
            if ($installedAdminCount > 0) {
                $alreadyInstalled = true;
            }
        }
    } catch (PDOException $e) {
        // Database error, continue with installation
    }
}

// Installation steps (prefer POST on submissions)
$step = isset($_POST['step']) ? (int)$_POST['step'] : (int)($_GET['step'] ?? 1);
if ($alreadyInstalled) {
    $step = 0; // show installed info
}

// Process form submissions
$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf']) || !hash_equals($_SESSION['installer_csrf'], (string)$_POST['csrf'])) {
        $errors[] = 'Token di sicurezza non valido. Riprova.';
    }
    if ($step === 1 && empty($errors)) {
        // Directory permissions check with security validation
        $required_dirs = [DATA_DIR, DOWNLOADS_DIR, ROOT_DIR . '/cache'];
        
        // Validate that all required directories are within the application root
        $safe_root = realpath(ROOT_DIR);
        if (!$safe_root) {
            $errors[] = "Impossibile determinare la directory root dell'applicazione";
        } else {
            foreach ($required_dirs as $dir) {
                // Resolve and validate directory path
                $resolved_dir = $dir;
                if (!file_exists($dir)) {
                    // Create parent directories if needed
                    $parent = dirname($dir);
                    if (!is_dir($parent)) {
                        if (!mkdir($parent, 0755, true)) {
                            $errors[] = "Impossibile creare la directory parent: $parent";
                            continue;
                        }
                    }
                }
                
                // Security check: ensure directory is within application root
                $real_dir = realpath(dirname($dir));
                if (!$real_dir || strpos($real_dir, $safe_root) !== 0) {
                    $errors[] = "Directory non sicura rilevata: $dir";
                    continue;
                }
                
                if (!is_dir($dir)) {
                    if (!mkdir($dir, 0755, true)) {
                        $errors[] = "Impossibile creare la directory: $dir";
                        continue;
                    }
                    
                    // Set secure permissions
                    chmod($dir, 0755);
                    
                    // Create .htaccess to prevent direct access
                    $htaccess_content = "Require all denied\nOptions -Indexes\n";
                    if (strpos(basename($dir), 'downloads') === false) { // Allow downloads dir
                        @file_put_contents($dir . '/.htaccess', $htaccess_content);
                    }
                }
                
                if (!is_writable($dir)) {
                    $errors[] = "La directory $dir non è scrivibile";
                }
            }
        }
        
        if (empty($errors)) {
            $step = 2;
        }
    } elseif ($step === 2 && empty($errors)) {
        // Admin user creation with enhanced security validation
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        
        // Enhanced validation
        if (empty($username) || strlen($username) < 3 || strlen($username) > 50) {
            $errors[] = 'Il nome utente deve essere lungo tra 3 e 50 caratteri';
        }
        
        // Username security check
        if (!preg_match('/^[a-zA-Z0-9_-]+$/', $username)) {
            $errors[] = 'Il nome utente può contenere solo lettere, numeri, _ e -';
        }
        
        // Check for dangerous usernames
        $forbidden_usernames = ['admin', 'root', 'administrator', 'system', 'guest', 'test', 'null', 'undefined'];
        if (in_array(strtolower($username), $forbidden_usernames)) {
            $errors[] = 'Nome utente non consentito';
        }
        
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 100) {
            $errors[] = 'Email non valida o troppo lunga';
        }
        
        // Strong password requirements
        if (empty($password) || strlen($password) < 8 || strlen($password) > 128) {
            $errors[] = 'La password deve essere lunga tra 8 e 128 caratteri';
        }
        
        // Password complexity check
        if (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).*$/', $password)) {
            $errors[] = 'La password deve contenere almeno una lettera minuscola, una maiuscola e un numero';
        }
        
        if ($password !== $confirm_password) {
            $errors[] = 'Le password non coincidono';
        }
        
        // Check for common weak passwords
        $weak_passwords = ['password', '12345678', 'admin123', 'password123', 'qwerty123'];
        if (in_array(strtolower($password), $weak_passwords)) {
            $errors[] = 'Password troppo debole';
        }
        
        if (empty($errors)) {
            // Create database and admin user
            try {
                // Ensure data directory exists
                if (!is_dir(DATA_DIR)) {
                    mkdir(DATA_DIR, 0755, true);
                }
                
                // Create database
                $db = new PDO('sqlite:' . DATA_DIR . '/app.sqlite');
                $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                
                // Create tables
                $db->exec('CREATE TABLE IF NOT EXISTS settings (
                    key TEXT PRIMARY KEY,
                    value TEXT
                )');
                
                $db->exec('CREATE TABLE IF NOT EXISTS services (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    name TEXT UNIQUE,
                    enabled INTEGER NOT NULL DEFAULT 1,
                    endpoint TEXT,
                    config TEXT,
                    notes TEXT
                )');
                
                $db->exec('CREATE TABLE IF NOT EXISTS tokens (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    service_id INTEGER,
                    name TEXT,
                    value TEXT,
                    updated_at TEXT,
                    FOREIGN KEY(service_id) REFERENCES services(id) ON DELETE CASCADE
                )');
                
                $db->exec("CREATE TABLE IF NOT EXISTS users (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    username TEXT UNIQUE NOT NULL,
                    email TEXT UNIQUE NOT NULL,
                    password_hash TEXT NOT NULL,
                    is_active INTEGER DEFAULT 1,
                    is_admin INTEGER DEFAULT 1,
                    created_at TEXT DEFAULT (datetime('now')),
                    last_login TEXT,
                    reset_token TEXT,
                    reset_token_expires TEXT
                )");
                
                $db->exec("CREATE TABLE IF NOT EXISTS downloads (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    user_id INTEGER,
                    username TEXT,
                    type TEXT,
                    title TEXT,
                    spotify_url TEXT,
                    service TEXT,
                    file_size INTEGER,
                    ip_address TEXT,
                    user_agent TEXT,
                    downloaded_at TEXT DEFAULT (datetime('now')),
                    meta TEXT,
                    FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE SET NULL
                )");
                
                $db->exec("CREATE TABLE IF NOT EXISTS active_downloads (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    job_id TEXT,
                    title TEXT,
                    started_at TEXT DEFAULT (datetime('now'))
                )");
                
                $db->exec("CREATE TABLE IF NOT EXISTS jobs (
                    job_id TEXT PRIMARY KEY,
                    type TEXT,
                    total INTEGER,
                    completed INTEGER DEFAULT 0,
                    started_at TEXT DEFAULT (datetime('now')),
                    finished_at TEXT
                )");
                
                // Insert default services
                $services = [
                    'tidal' => 'Tidal',
                    'amazon' => 'Amazon',
                    'qobuz' => 'Qobuz'
                ];
                
                $stmt = $db->prepare('INSERT OR IGNORE INTO services(name, enabled) VALUES(?, ?)');
                foreach ($services as $key => $label) {
                    $stmt->execute([$key, 1]);
                }
                
                // Insert default download concurrency setting
                $stmt = $db->prepare('INSERT OR IGNORE INTO settings(key, value) VALUES(?, ?)');
                $stmt->execute(['download_concurrency', '4']);
                
                // Create admin user
                $password_hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $db->prepare('INSERT INTO users (username, email, password_hash, is_active, is_admin) VALUES (?, ?, ?, 1, 1)');
                $stmt->execute([$username, $email, $password_hash]);
                
                $success = 'Installazione completata con successo!';
                $step = 3;
            } catch (PDOException $e) {
                $errors[] = 'Errore durante la creazione del database: ' . $e->getMessage();
            }
        }
    } elseif ($step === 3 && empty($errors)) {
        // Final step - redirect to login
        header('Location: ../login.php');
        exit;
    }
}

// HTML Template
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MusicFLAC - Installazione</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <style>
        body { background: #0b0c0d; }
        .card { background:#0f172a; border:1px solid #1f2937; }
        .step-active { background: #1f2937; border-color: #3b82f6; }
        .step-complete { background: #1f2937; border-color: #10b981; }
    </style>
</head>
<body class="text-white">
    <div class="min-h-screen flex items-center justify-center p-4">
        <div class="max-w-2xl w-full">
            <div class="text-center mb-8">
                <h1 class="text-3xl font-bold text-accent mb-2">MusicFLAC</h1>
                <p class="text-gray-400">Installazione dell'applicazione</p>
            </div>
            
            <!-- Progress Steps -->
            <div class="flex justify-between mb-8">
                <div class="flex-1 text-center <?= $step >= 1 || $step === 0 ? 'step-active' : '' ?> <?= $step > 1 ? 'step-complete' : '' ?> border rounded-lg p-3 mx-2">
                    <div class="font-semibold"><?= $step === 0 ? 'Stato' : '1. Verifica' ?></div>
                    <div class="text-xs text-gray-400 mt-1"><?= $step === 0 ? 'Installazione' : 'Permessi' ?></div>
                </div>
                <div class="flex-1 text-center <?= $step >= 2 ? 'step-active' : '' ?> <?= $step > 2 ? 'step-complete' : '' ?> border rounded-lg p-3 mx-2">
                    <div class="font-semibold">2. Admin</div>
                    <div class="text-xs text-gray-400 mt-1">Utente</div>
                </div>
                <div class="flex-1 text-center <?= $step >= 3 ? 'step-active' : '' ?> border rounded-lg p-3 mx-2">
                    <div class="font-semibold">3. Completa</div>
                    <div class="text-xs text-gray-400 mt-1">Installazione</div>
                </div>
            </div>
            
            <div class="card rounded-xl p-6">
                <?php if ($errors): ?>
                    <div class="mb-6 p-4 rounded border border-red-700 bg-red-900 bg-opacity-30 text-red-200">
                        <ul class="list-disc pl-5">
                            <?php foreach ($errors as $error): ?>
                                <li><?= htmlspecialchars($error) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="mb-6 p-4 rounded border border-green-700 bg-green-900 bg-opacity-30 text-green-200">
                        <?= htmlspecialchars($success) ?>
                    </div>
                <?php endif; ?>
                <?php if ($step === 0): ?>
                    <div class="text-center py-8">
                        <div class="text-5xl text-green-500 mb-6">✓</div>
                        <h2 class="text-2xl font-bold mb-4">Applicazione già installata</h2>
                        <p class="text-gray-400 mb-8">MusicFLAC risulta già installato. Admin presenti: <?= (int)$installedAdminCount ?>.</p>
                        <div class="flex gap-3 justify-center">
                            <a href="../index.php" class="px-4 py-3 bg-green-600 hover:bg-green-700 rounded-lg font-semibold">Vai all'applicazione</a>
                            <a href="../login.php" class="px-4 py-3 bg-gray-700 hover:bg-gray-600 rounded-lg font-semibold">Accedi</a>
                        </div>
                    </div>
                <?php elseif ($step === 1): ?>
                    <form method="post">
                        <input type="hidden" name="step" value="1">
                        <input type="hidden" name="csrf" value="<?= htmlspecialchars($installer_csrf) ?>">
                        <h2 class="text-xl font-bold mb-4">Verifica dei permessi</h2>
                        <p class="text-gray-400 mb-6">Verifichiamo che le directory necessarie siano accessibili e scrivibili.</p>
                        
                        <div class="space-y-4 mb-6">
                            <div class="flex items-center p-3 rounded bg-gray-800">
                                <div class="mr-3">
                                    <?php if (is_dir(DATA_DIR) && is_writable(DATA_DIR)): ?>
                                        <span class="text-green-500">✓</span>
                                    <?php else: ?>
                                        <span class="text-yellow-500">⚠</span>
                                    <?php endif; ?>
                                </div>
                                <div>
                                    <div class="font-medium">Directory data</div>
                                    <div class="text-sm text-gray-400"><?= htmlspecialchars(DATA_DIR) ?></div>
                                </div>
                                <div class="ml-auto">
                                    <?php if (is_dir(DATA_DIR) && is_writable(DATA_DIR)): ?>
                                        <span class="text-green-500 text-sm">SCRIVIBILE</span>
                                    <?php else: ?>
                                        <span class="text-yellow-500 text-sm">DA CONFIGURARE</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="flex items-center p-3 rounded bg-gray-800">
                                <div class="mr-3">
                                    <?php if (is_dir(DOWNLOADS_DIR) && is_writable(DOWNLOADS_DIR)): ?>
                                        <span class="text-green-500">✓</span>
                                    <?php else: ?>
                                        <span class="text-yellow-500">⚠</span>
                                    <?php endif; ?>
                                </div>
                                <div>
                                    <div class="font-medium">Directory downloads</div>
                                    <div class="text-sm text-gray-400"><?= htmlspecialchars(DOWNLOADS_DIR) ?></div>
                                </div>
                                <div class="ml-auto">
                                    <?php if (is_dir(DOWNLOADS_DIR) && is_writable(DOWNLOADS_DIR)): ?>
                                        <span class="text-green-500 text-sm">SCRIVIBILE</span>
                                    <?php else: ?>
                                        <span class="text-yellow-500 text-sm">DA CONFIGURARE</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="flex items-center p-3 rounded bg-gray-800">
                                <div class="mr-3">
                                    <?php $cacheDir = ROOT_DIR . '/cache'; ?>
                                    <?php if (is_dir($cacheDir) && is_writable($cacheDir)): ?>
                                        <span class="text-green-500">✓</span>
                                    <?php else: ?>
                                        <span class="text-yellow-500">⚠</span>
                                    <?php endif; ?>
                                </div>
                                <div>
                                    <div class="font-medium">Directory cache</div>
                                    <div class="text-sm text-gray-400"><?= htmlspecialchars($cacheDir ?? (ROOT_DIR . '/cache')) ?></div>
                                </div>
                                <div class="ml-auto">
                                    <?php if (is_dir($cacheDir) && is_writable($cacheDir)): ?>
                                        <span class="text-green-500 text-sm">SCRIVIBILE</span>
                                    <?php else: ?>
                                        <span class="text-yellow-500 text-sm">DA CONFIGURARE</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                        <button type="submit" class="w-full px-4 py-3 bg-green-600 hover:bg-green-700 rounded-lg font-semibold">
                            Verifica e continua
                        </button>
                    </form>
                <?php elseif ($step === 2): ?>
                    <form method="post">
                        <input type="hidden" name="step" value="2">
                        <input type="hidden" name="csrf" value="<?= htmlspecialchars($installer_csrf) ?>">
                        <h2 class="text-xl font-bold mb-4">Creazione utente amministratore</h2>
                        <p class="text-gray-400 mb-6">Crea il tuo account amministratore per accedere all'area di gestione.</p>
                        
                        <div class="space-y-4 mb-6">
                            <div>
                                <label class="block text-gray-300 mb-2" for="username">Nome utente</label>
                                <input type="text" id="username" name="username" required class="w-full px-4 py-3 bg-gray-800 border border-gray-700 rounded" autocomplete="username">
                            </div>
                            
                            <div>
                                <label class="block text-gray-300 mb-2" for="email">Email</label>
                                <input type="email" id="email" name="email" required class="w-full px-4 py-3 bg-gray-800 border border-gray-700 rounded" autocomplete="email">
                            </div>
                            
                            <div>
                                <label class="block text-gray-300 mb-2" for="password">Password</label>
                                <input type="password" id="password" name="password" required class="w-full px-4 py-3 bg-gray-800 border border-gray-700 rounded" autocomplete="new-password">
                                <div class="text-sm text-gray-400 mt-1">Minimo 6 caratteri</div>
                            </div>
                            
                            <div>
                                <label class="block text-gray-300 mb-2" for="confirm_password">Conferma Password</label>
                                <input type="password" id="confirm_password" name="confirm_password" required class="w-full px-4 py-3 bg-gray-800 border border-gray-700 rounded" autocomplete="new-password">
                            </div>
                        </div>
                        
                        <div class="flex gap-3">
                            <a href="?step=1" class="px-4 py-3 bg-gray-700 hover:bg-gray-600 rounded-lg font-semibold">
                                Indietro
                            </a>
                            <button type="submit" class="flex-1 px-4 py-3 bg-green-600 hover:bg-green-700 rounded-lg font-semibold">
                                Crea utente admin
                            </button>
                        </div>
                    </form>
                <?php elseif ($step === 3): ?>
                    <div class="text-center py-8">
                        <div class="text-5xl text-green-500 mb-6">✓</div>
                        <h2 class="text-2xl font-bold mb-4">Installazione completata!</h2>
                        <p class="text-gray-400 mb-8">MusicFlac è stato installato con successo sul tuo server.</p>
                        
                        <div class="bg-gray-800 rounded-lg p-4 mb-6 text-left">
                            <h3 class="font-semibold mb-2">Prossimi passi:</h3>
                            <ul class="list-disc pl-5 space-y-2 text-sm">
                                <li>Accedi all'applicazione con le credenziali appena create</li>
                                <li>Configura i servizi di download nell'area amministrativa</li>
                                <li>Aggiungi i token API per i servizi che utilizzi</li>
                            </ul>
                        </div>
                        
                        <form method="post">
                            <input type="hidden" name="step" value="3">
                            <input type="hidden" name="csrf" value="<?= htmlspecialchars($installer_csrf) ?>">
                            <button type="submit" class="w-full px-4 py-3 bg-green-600 hover:bg-green-700 rounded-lg font-semibold">
                                Accedi all'applicazione
                            </button>
                        </form>
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="text-center text-gray-500 text-sm mt-8">
                MusicFlac &copy; <?= date('Y') ?> - Installer
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/gsap@3.12.5/dist/gsap.min.js"></script>
    <script>
      if (window.gsap) { gsap.from('.card', { opacity: 0, y: 16, duration: 0.6, ease: 'power2.out' }); }
    </script>
</body>
</html>
