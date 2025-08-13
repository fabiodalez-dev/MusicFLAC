<?php
// App bootstrap: SQLite, settings, services, tokens, downloads

if (!function_exists('app_is_https')) {
    function app_is_https(): bool {
        if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') return true;
        $proto = $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '';
        if (stripos($proto, 'https') !== false) return true;
        $scheme = $_SERVER['REQUEST_SCHEME'] ?? '';
        return strtolower($scheme) === 'https';
    }
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!function_exists('app_reissue_session_cookie')) {
    function app_reissue_session_cookie(): void {
        if (headers_sent()) return;
        $params = session_get_cookie_params();
        $secure = app_is_https();
        $cookie = [
            'expires' => 0,
            'path' => $params['path'] ?: '/',
            'domain' => $params['domain'] ?: '',
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Lax'
        ];
        @setcookie(session_name(), session_id(), $cookie);
    }
}

// Ensure hardened cookie attributes for the current session
app_reissue_session_cookie();

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/version.php';

if (!function_exists('app_is_installed')) {
    function app_is_installed(): bool {
        $dataDir = __DIR__ . '/../data';
        $dbFile = $dataDir . '/app.sqlite';
        if (!file_exists($dbFile) || filesize($dbFile) === 0) return false;
        try {
            $db = new PDO('sqlite:' . $dbFile);
            $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $t = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='users'")->fetchColumn();
            if (!$t) return false;
            $admins = (int)$db->query("SELECT COUNT(*) FROM users WHERE is_admin = 1")->fetchColumn();
            return $admins > 0;
        } catch (Throwable $e) {
            return false;
        }
    }
}

function base_url(string $path = ''): string {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $docRoot = isset($_SERVER['DOCUMENT_ROOT']) ? realpath($_SERVER['DOCUMENT_ROOT']) : null;
    $appRoot = realpath(__DIR__ . '/..');
    $rel = '';
    if ($docRoot && $appRoot && strpos($appRoot, $docRoot) === 0) {
        $rel = str_replace('\\', '/', substr($appRoot, strlen($docRoot)));
    }
    if ($rel === false || $rel === null) $rel = '';
    $rel = '/' . ltrim($rel, '/');
    $base = $scheme . '://' . $host . rtrim($rel, '/');
    if ($path === '' ) return $base . '/';
    return rtrim($base, '/') . '/' . ltrim($path, '/');
}

function app_db(): PDO {
    static $pdo = null;
    if ($pdo instanceof PDO) return $pdo;
    $dataDir = __DIR__ . '/../data';
    if (!is_dir($dataDir)) @mkdir($dataDir, 0755, true);
    $dsn = 'sqlite:' . $dataDir . '/app.sqlite';
    $pdo = new PDO($dsn);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    app_migrate($pdo);
    return $pdo;
}

function app_migrate(PDO $db): void {
    // settings
    $db->exec('CREATE TABLE IF NOT EXISTS settings (
        key TEXT PRIMARY KEY,
        value TEXT
    )');
    // services
    $db->exec('CREATE TABLE IF NOT EXISTS services (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT UNIQUE,
        enabled INTEGER NOT NULL DEFAULT 1,
        endpoint TEXT,
        config TEXT,
        notes TEXT
    )');
    // tokens
    $db->exec('CREATE TABLE IF NOT EXISTS tokens (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        service_id INTEGER,
        name TEXT,
        value TEXT,
        updated_at TEXT,
        FOREIGN KEY(service_id) REFERENCES services(id) ON DELETE CASCADE
    )');
    // users table
    $db->exec('CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        username TEXT UNIQUE NOT NULL,
        email TEXT UNIQUE NOT NULL,
        password_hash TEXT NOT NULL,
        is_active INTEGER DEFAULT 0,
        is_admin INTEGER DEFAULT 0,
        created_at TEXT DEFAULT (datetime(\'now\')),
        last_login TEXT,
        reset_token TEXT,
        reset_token_expires TEXT
    )');

    // downloads log (updated with user tracking)
    $db->exec('CREATE TABLE IF NOT EXISTS downloads (
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
        downloaded_at TEXT DEFAULT (datetime(\'now\')),
        meta TEXT,
        FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE SET NULL
    )');

    // active downloads (for live progress)
    $db->exec('CREATE TABLE IF NOT EXISTS active_downloads (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        job_id TEXT,
        title TEXT,
        started_at TEXT DEFAULT (datetime(\'now\'))
    )');

    // jobs progress
    $db->exec('CREATE TABLE IF NOT EXISTS jobs (
        job_id TEXT PRIMARY KEY,
        type TEXT,
        total INTEGER,
        completed INTEGER DEFAULT 0,
        started_at TEXT DEFAULT (datetime(\'now\')),
        finished_at TEXT
    )');

    // Note: Admin user is created via installer; do not seed here
    // This migration only ensures tables exist and core data is seeded.

    // Seed services from SUPPORTED_SERVICES if table empty
    $count = (int)$db->query('SELECT COUNT(*) FROM services')->fetchColumn();
    if ($count === 0 && defined('SUPPORTED_SERVICES')) {
        $stmt = $db->prepare('INSERT INTO services(name, enabled, endpoint, config) VALUES(?, 1, ?, ?)');
        foreach (SUPPORTED_SERVICES as $key => $label) {
            $endpoint = '';
            if ($key === 'tidal') $endpoint = defined('TIDAL_API_URL') ? TIDAL_API_URL : '';
            elseif ($key === 'qobuz') $endpoint = defined('QOBUZ_API_URL') ? QOBUZ_API_URL : '';
            elseif ($key === 'amazon') $endpoint = defined('AMAZON_API_URL') ? AMAZON_API_URL : '';
            $stmt->execute([$key, $endpoint, json_encode(new stdClass())]);
        }
    }
}

function app_get_setting(string $key, $default = null) {
    $db = app_db();
    $stmt = $db->prepare('SELECT value FROM settings WHERE key = ?');
    $stmt->execute([$key]);
    $val = $stmt->fetchColumn();
    return $val === false ? $default : $val;
}

function app_set_setting(string $key, string $value): void {
    $db = app_db();
    $stmt = $db->prepare('INSERT INTO settings(key, value) VALUES(?, ?) ON CONFLICT(key) DO UPDATE SET value=excluded.value');
    $stmt->execute([$key, $value]);
}

function app_list_services(): array {
    $db = app_db();
    $rows = $db->query('SELECT * FROM services ORDER BY name ASC')->fetchAll(PDO::FETCH_ASSOC);
    if (!$rows) {
        $out = [];
        foreach (SUPPORTED_SERVICES as $key => $label) {
            $out[$key] = ['name' => $key, 'enabled' => 1, 'endpoint' => '', 'label' => $label];
        }
        return $out;
    }
    $out = [];
    foreach ($rows as $r) {
        $label = SUPPORTED_SERVICES[$r['name']] ?? ucfirst($r['name']);
        $out[$r['name']] = [
            'id' => (int)$r['id'],
            'name' => $r['name'],
            'label' => $label,
            'enabled' => (int)$r['enabled'],
            'endpoint' => $r['endpoint'] ?? ''
        ];
    }
    return $out;
}

function app_service_enabled(string $name): bool {
    $services = app_list_services();
    return isset($services[$name]) ? (bool)$services[$name]['enabled'] : array_key_exists($name, SUPPORTED_SERVICES);
}

function app_get_service_endpoint(string $name, string $fallback = ''): string {
    $services = app_list_services();
    if (isset($services[$name]) && !empty($services[$name]['endpoint'])) return $services[$name]['endpoint'];
    return $fallback;
}



function app_get_tokens(int $service_id): array {
    $db = app_db();
    $stmt = $db->prepare('SELECT * FROM tokens WHERE service_id = ? ORDER BY name');
    $stmt->execute([$service_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function app_set_token(int $service_id, string $name, string $value): void {
    $db = app_db();
    // Upsert by (service_id, name)
    $exists = $db->prepare('SELECT id FROM tokens WHERE service_id = ? AND name = ?');
    $exists->execute([$service_id, $name]);
    $id = $exists->fetchColumn();
    if ($id) {
        $stmt = $db->prepare('UPDATE tokens SET value = ?, updated_at = datetime("now") WHERE id = ?');
        $stmt->execute([$value, $id]);
    } else {
        $stmt = $db->prepare('INSERT INTO tokens(service_id, name, value, updated_at) VALUES(?, ?, ?, datetime("now"))');
        $stmt->execute([$service_id, $name, $value]);
    }
}

function app_delete_token(int $id): void {
    $db = app_db();
    $stmt = $db->prepare('DELETE FROM tokens WHERE id = ?');
    $stmt->execute([$id]);
}

function app_active_add(string $title, ?string $job_id = null): void {
    try { $db = app_db(); $st = $db->prepare('INSERT INTO active_downloads(job_id, title) VALUES(?, ?)'); $st->execute([$job_id, $title]); } catch (Throwable $e) { debug_log('active_add_error',['e'=>$e->getMessage()]); }
}

function app_active_remove(string $title, ?string $job_id = null): void {
    try { $db = app_db();
        if ($job_id) { $st = $db->prepare('DELETE FROM active_downloads WHERE title = ? AND job_id = ?'); $st->execute([$title, $job_id]); }
        else { $st = $db->prepare('DELETE FROM active_downloads WHERE title = ?'); $st->execute([$title]); }
    } catch (Throwable $e) { debug_log('active_remove_error',['e'=>$e->getMessage()]); }
}

function app_active_list(?string $job_id = null): array {
    try { $db = app_db();
        if ($job_id) { $st = $db->prepare('SELECT title FROM active_downloads WHERE job_id = ? ORDER BY id ASC'); $st->execute([$job_id]); return $st->fetchAll(PDO::FETCH_COLUMN) ?: []; }
        $rows = $db->query('SELECT title FROM active_downloads ORDER BY id ASC')->fetchAll(PDO::FETCH_COLUMN) ?: []; return $rows;
    } catch (Throwable $e) { return []; }
}

function app_job_start(string $job_id, int $total, string $type = 'album'): void {
    try { $db = app_db(); $st = $db->prepare('INSERT OR REPLACE INTO jobs(job_id, type, total, completed, started_at, finished_at) VALUES(?, ?, ?, COALESCE((SELECT completed FROM jobs WHERE job_id = ?),0), datetime(\'now\'), NULL)'); $st->execute([$job_id, $type, $total, $job_id]); } catch (Throwable $e) { debug_log('job_start_error',['e'=>$e->getMessage()]); }
}

function app_job_increment_complete(string $job_id): void {
    try {
        $db = app_db();
        $inc = $db->prepare('UPDATE jobs SET completed = completed + 1 WHERE job_id = ?');
        $inc->execute([$job_id]);
        $st = $db->prepare('SELECT total, completed FROM jobs WHERE job_id = ?');
        $st->execute([$job_id]);
        $r = $st->fetch(PDO::FETCH_ASSOC);
        if ($r && (int)$r['completed'] >= (int)$r['total']) {
            $end = $db->prepare('UPDATE jobs SET finished_at = datetime(\'now\') WHERE job_id = ?');
            $end->execute([$job_id]);
        }
    } catch (Throwable $e) { debug_log('job_inc_error',[ 'e' => $e->getMessage() ]); }
}

function app_job_get(string $job_id): array {
    try { $db = app_db(); $st = $db->prepare('SELECT job_id, type, total, completed, started_at, finished_at FROM jobs WHERE job_id = ?'); $st->execute([$job_id]); $r = $st->fetch(PDO::FETCH_ASSOC) ?: []; return $r; } catch (Throwable $e) { return []; }
}

// User Management Functions
function user_register(string $username, string $email, string $password): array {
    $db = app_db();
    
    // Check if users table exists
    $tables = $db->query('SELECT name FROM sqlite_master WHERE type="table" AND name="users"')->fetchAll(PDO::FETCH_COLUMN);
    if (empty($tables)) {
        return ['success' => false, 'error' => 'User system not available'];
    }
    
    // Validate input
    if (strlen($username) < 3 || strlen($password) < 6) {
        return ['success' => false, 'error' => 'Username must be at least 3 characters, password at least 6'];
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['success' => false, 'error' => 'Invalid email format'];
    }
    
    try {
        // Check if username or email already exists
        $stmt = $db->prepare('SELECT id FROM users WHERE username = ? OR email = ?');
        $stmt->execute([$username, $email]);
        if ($stmt->fetch()) {
            return ['success' => false, 'error' => 'Username or email already exists'];
        }
        
        // Create user (inactive by default)
        $stmt = $db->prepare('INSERT INTO users (username, email, password_hash, is_active, is_admin) VALUES (?, ?, ?, 0, 0)');
        $stmt->execute([$username, $email, password_hash($password, PASSWORD_DEFAULT)]);
        
        return ['success' => true, 'user_id' => $db->lastInsertId()];
    } catch (PDOException $e) {
        return ['success' => false, 'error' => 'Registration failed: ' . $e->getMessage()];
    }
}

function user_login(string $username, string $password): array {
    $db = app_db();
    
    // Check if users table exists
    $tables = $db->query('SELECT name FROM sqlite_master WHERE type="table" AND name="users"')->fetchAll(PDO::FETCH_COLUMN);
    if (empty($tables)) {
        return ['success' => false, 'error' => 'User system not available'];
    }
    
    try {
        $stmt = $db->prepare('SELECT id, username, email, password_hash, is_active, is_admin FROM users WHERE username = ? OR email = ?');
        $stmt->execute([$username, $username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user || !password_verify($password, $user['password_hash'])) {
            return ['success' => false, 'error' => 'Invalid credentials'];
        }
        
        if (!$user['is_active']) {
            return ['success' => false, 'error' => 'Account not activated yet. Please wait for admin approval.'];
        }
        
        // Update last login
        $stmt = $db->prepare('UPDATE users SET last_login = datetime(\'now\') WHERE id = ?');
        $stmt->execute([$user['id']]);
        
        // Regenerate session ID to prevent fixation
        @session_regenerate_id(true);
        // Set session
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['is_admin'] = (bool)$user['is_admin'];
        
        return ['success' => true, 'user' => $user];
    } catch (PDOException $e) {
        return ['success' => false, 'error' => 'Login failed'];
    }
}

function user_logout(): void {
    session_destroy();
    session_start();
}

function user_is_logged_in(): bool {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function user_is_admin(): bool {
    return isset($_SESSION['is_admin']) && $_SESSION['is_admin'];
}

function user_get_current(): ?array {
    if (!user_is_logged_in()) return null;
    
    $db = app_db();
    
    // Check if users table exists
    $tables = $db->query('SELECT name FROM sqlite_master WHERE type="table" AND name="users"')->fetchAll(PDO::FETCH_COLUMN);
    if (empty($tables)) return null;
    
    try {
        $stmt = $db->prepare('SELECT id, username, email, is_active, is_admin, created_at, last_login FROM users WHERE id = ?');
        $stmt->execute([$_SESSION['user_id']]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    } catch (PDOException $e) {
        return null;
    }
}

function user_list_all(): array {
    $db = app_db();
    
    // Check if users table exists
    $tables = $db->query('SELECT name FROM sqlite_master WHERE type="table" AND name="users"')->fetchAll(PDO::FETCH_COLUMN);
    if (empty($tables)) return [];
    
    try {
        return $db->query('SELECT id, username, email, is_active, is_admin, created_at, last_login FROM users ORDER BY created_at DESC')->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return [];
    }
}

function user_activate(int $user_id, bool $active = true): bool {
    $db = app_db();
    
    // Check if users table exists
    $tables = $db->query('SELECT name FROM sqlite_master WHERE type="table" AND name="users"')->fetchAll(PDO::FETCH_COLUMN);
    if (empty($tables)) return false;
    
    try {
        $stmt = $db->prepare('UPDATE users SET is_active = ? WHERE id = ?');
        return $stmt->execute([($active ? 1 : 0), $user_id]);
    } catch (PDOException $e) {
        return false;
    }
}

function user_set_admin(int $user_id, bool $is_admin = true): bool {
    $db = app_db();
    
    // Check if users table exists
    $tables = $db->query('SELECT name FROM sqlite_master WHERE type="table" AND name="users"')->fetchAll(PDO::FETCH_COLUMN);
    if (empty($tables)) return false;
    
    try {
        $stmt = $db->prepare('UPDATE users SET is_admin = ? WHERE id = ?');
        return $stmt->execute([($is_admin ? 1 : 0), $user_id]);
    } catch (PDOException $e) {
        return false;
    }
}

function user_generate_reset_token(string $email): array {
    $db = app_db();
    
    // Check if users table exists
    $tables = $db->query('SELECT name FROM sqlite_master WHERE type="table" AND name="users"')->fetchAll(PDO::FETCH_COLUMN);
    if (empty($tables)) {
        return ['success' => false, 'error' => 'User system not available'];
    }
    
    try {
        $stmt = $db->prepare('SELECT id FROM users WHERE email = ?');
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if (!$user) {
            return ['success' => false, 'error' => 'Email not found'];
        }
        
        $token = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', strtotime('+2 hours'));
        
        $stmt = $db->prepare('UPDATE users SET reset_token = ?, reset_token_expires = ? WHERE id = ?');
        $stmt->execute([$token, $expires, $user['id']]);
        
        return ['success' => true, 'token' => $token, 'user_id' => $user['id']];
    } catch (PDOException $e) {
        return ['success' => false, 'error' => 'Reset token generation failed'];
    }
}

function user_reset_password(string $token, string $new_password): array {
    $db = app_db();
    
    // Check if users table exists
    $tables = $db->query('SELECT name FROM sqlite_master WHERE type="table" AND name="users"')->fetchAll(PDO::FETCH_COLUMN);
    if (empty($tables)) {
        return ['success' => false, 'error' => 'User system not available'];
    }
    
    try {
        $stmt = $db->prepare('SELECT id FROM users WHERE reset_token = ? AND reset_token_expires > datetime(\'now\')');
        $stmt->execute([$token]);
        $user = $stmt->fetch();
        
        if (!$user) {
            return ['success' => false, 'error' => 'Invalid or expired reset token'];
        }
        
        if (strlen($new_password) < 6) {
            return ['success' => false, 'error' => 'Password must be at least 6 characters'];
        }
        
        $stmt = $db->prepare('UPDATE users SET password_hash = ?, reset_token = NULL, reset_token_expires = NULL WHERE id = ?');
        $stmt->execute([password_hash($new_password, PASSWORD_DEFAULT), $user['id']]);
        
        return ['success' => true];
    } catch (PDOException $e) {
        return ['success' => false, 'error' => 'Password reset failed'];
    }
}

// Update the download logging function to include user info
function app_log_download(string $type, string $title, string $spotify_url, array $meta = []): void {
    $db = app_db();
    $user = user_get_current();
    
    // Check which columns exist in downloads table
    $columns = $db->query('PRAGMA table_info(downloads)')->fetchAll(PDO::FETCH_ASSOC);
    $availableColumns = [];
    foreach ($columns as $column) {
        $availableColumns[$column['name']] = true;
    }
    
    // Get file size if available
    $file_size = null;
    if (isset($meta['file']) && file_exists(DOWNLOAD_DIR . $meta['file'])) {
        $file_size = filesize(DOWNLOAD_DIR . $meta['file']);
    }
    
    // Build insert query based on available columns
    $insertColumns = ['type', 'title', 'spotify_url'];
    $insertValues = [$type, $title, $spotify_url];
    
    if (isset($availableColumns['user_id'])) {
        $insertColumns[] = 'user_id';
        $insertValues[] = $user ? $user['id'] : null;
    }
    
    if (isset($availableColumns['username'])) {
        $insertColumns[] = 'username';
        $insertValues[] = $user ? $user['username'] : 'anonymous';
    }
    
    if (isset($availableColumns['service'])) {
        $insertColumns[] = 'service';
        $insertValues[] = $meta['service'] ?? '';
    }
    
    if (isset($availableColumns['file_size'])) {
        $insertColumns[] = 'file_size';
        $insertValues[] = $file_size;
    }
    
    if (isset($availableColumns['ip_address'])) {
        $insertColumns[] = 'ip_address';
        $insertValues[] = $_SERVER['REMOTE_ADDR'] ?? '';
    }
    
    if (isset($availableColumns['user_agent'])) {
        $insertColumns[] = 'user_agent';
        $insertValues[] = $_SERVER['HTTP_USER_AGENT'] ?? '';
    }
    
    if (isset($availableColumns['meta'])) {
        $insertColumns[] = 'meta';
        $insertValues[] = json_encode($meta);
    }
    
    if (isset($availableColumns['downloaded_at'])) {
        $insertColumns[] = 'downloaded_at';
        $insertValues[] = date('Y-m-d H:i:s');
    }
    
    $placeholders = str_repeat('?,', count($insertColumns) - 1) . '?';
    $query = 'INSERT INTO downloads (' . implode(', ', $insertColumns) . ') VALUES (' . $placeholders . ')';
    
    $stmt = $db->prepare($query);
    $stmt->execute($insertValues);
}

function app_get_download_stats(): array {
    $db = app_db();
    
    // Check if tables exist
    $tables = $db->query('SELECT name FROM sqlite_master WHERE type="table"')->fetchAll(PDO::FETCH_COLUMN);
    $hasDownloadsTable = in_array('downloads', $tables);
    $hasUsersTable = in_array('users', $tables);
    
    // Basic stats
    $stats = [];
    $stats['total_downloads'] = $hasDownloadsTable ? (int)$db->query('SELECT COUNT(*) FROM downloads')->fetchColumn() : 0;
    $stats['total_users'] = $hasUsersTable ? (int)$db->query('SELECT COUNT(*) FROM users')->fetchColumn() : 0;
    $stats['active_users'] = $hasUsersTable ? (int)$db->query('SELECT COUNT(*) FROM users WHERE is_active = 1')->fetchColumn() : 0;
    
    if ($hasDownloadsTable) {
        // Check if downloaded_at column exists
        $columns = $db->query('PRAGMA table_info(downloads)')->fetchAll(PDO::FETCH_ASSOC);
        $hasDownloadedAtColumn = false;
        foreach ($columns as $column) {
            if ($column['name'] === 'downloaded_at') {
                $hasDownloadedAtColumn = true;
                break;
            }
        }
        
        if ($hasDownloadedAtColumn) {
            $stats['downloads_today'] = (int)$db->query('SELECT COUNT(*) FROM downloads WHERE DATE(downloaded_at) = DATE(\'now\')')->fetchColumn();
            $stats['downloads_this_week'] = (int)$db->query('SELECT COUNT(*) FROM downloads WHERE downloaded_at >= date(\'now\', \'-7 days\')')->fetchColumn();
            $stats['downloads_this_month'] = (int)$db->query('SELECT COUNT(*) FROM downloads WHERE downloaded_at >= date(\'now\', \'-30 days\')')->fetchColumn();
        } else {
            $stats['downloads_today'] = 0;
            $stats['downloads_this_week'] = 0;
            $stats['downloads_this_month'] = 0;
        }
    } else {
        $stats['downloads_today'] = 0;
        $stats['downloads_this_week'] = 0;
        $stats['downloads_this_month'] = 0;
    }
    
    // Check which columns exist in downloads table (if table exists)
    $hasUsernameColumn = false;
    $hasUserIdColumn = false;
    $hasServiceColumn = false;
    
    if ($hasDownloadsTable) {
        $columns = $db->query('PRAGMA table_info(downloads)')->fetchAll(PDO::FETCH_ASSOC);
        foreach ($columns as $column) {
            if ($column['name'] === 'username') $hasUsernameColumn = true;
            if ($column['name'] === 'user_id') $hasUserIdColumn = true;
            if ($column['name'] === 'service') $hasServiceColumn = true;
        }
    }
    
    // Top users
    if ($hasUsernameColumn && $hasDownloadsTable) {
        $stats['top_users'] = $db->query('SELECT username, COUNT(*) as download_count FROM downloads WHERE username IS NOT NULL GROUP BY username ORDER BY download_count DESC LIMIT 10')->fetchAll(PDO::FETCH_ASSOC);
    } elseif ($hasUserIdColumn && $hasDownloadsTable && $hasUsersTable) {
        $stats['top_users'] = $db->query('SELECT u.username, COUNT(*) as download_count FROM downloads d JOIN users u ON d.user_id = u.id WHERE d.user_id IS NOT NULL GROUP BY u.username ORDER BY download_count DESC LIMIT 10')->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $stats['top_users'] = [];
    }
    
    // Top services
    if ($hasServiceColumn && $hasDownloadsTable) {
        $stats['top_services'] = $db->query('SELECT service, COUNT(*) as download_count FROM downloads WHERE service IS NOT NULL GROUP BY service ORDER BY download_count DESC')->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $stats['top_services'] = [];
    }
    
    // Download types
    if ($hasDownloadsTable) {
        $stats['download_types'] = $db->query('SELECT type, COUNT(*) as download_count FROM downloads GROUP BY type ORDER BY download_count DESC')->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $stats['download_types'] = [];
    }
    
    // Recent downloads - build query based on available columns
    if ($hasDownloadsTable) {
        $selectFields = [];
        $joinClause = '';
        $fromClause = 'downloads d';
        
        if ($hasUsernameColumn) {
            $selectFields[] = 'username';
        } elseif ($hasUserIdColumn && $hasUsersTable) {
            $selectFields[] = 'u.username';
            $joinClause = ' LEFT JOIN users u ON d.user_id = u.id';
            $fromClause = 'downloads d';
        } else {
            $selectFields[] = '"anonimo" as username';
        }
        
        $selectFields[] = ($hasUsernameColumn || ($hasUserIdColumn && $hasUsersTable)) ? 'd.type' : 'type';
        $selectFields[] = ($hasUsernameColumn || ($hasUserIdColumn && $hasUsersTable)) ? 'd.title' : 'title';
        
        if ($hasServiceColumn) {
            $selectFields[] = ($hasUsernameColumn || ($hasUserIdColumn && $hasUsersTable)) ? 'd.service' : 'service';
        } else {
            $selectFields[] = '"N/A" as service';
        }
        
        if ($hasDownloadedAtColumn) {
            $selectFields[] = ($hasUsernameColumn || ($hasUserIdColumn && $hasUsersTable)) ? 'd.downloaded_at' : 'downloaded_at';
            $orderBy = ($hasUsernameColumn || ($hasUserIdColumn && $hasUsersTable)) ? 'd.downloaded_at' : 'downloaded_at';
        } else {
            $selectFields[] = '"N/A" as downloaded_at';
            $orderBy = ($hasUsernameColumn || ($hasUserIdColumn && $hasUsersTable)) ? 'd.id' : 'id';
        }
        
        $query = 'SELECT ' . implode(', ', $selectFields) . ' FROM ' . $fromClause . $joinClause . ' ORDER BY ' . $orderBy . ' DESC LIMIT 20';
        $stats['recent_downloads'] = $db->query($query)->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $stats['recent_downloads'] = [];
    }
    
    return $stats;
}

// Intentionally no closing PHP tag to avoid accidental output
