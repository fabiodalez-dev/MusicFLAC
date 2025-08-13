<?php
// Check if application is installed
$dataDir = __DIR__ . '/../data';
if (!file_exists($dataDir . '/app.sqlite') || filesize($dataDir . '/app.sqlite') == 0) {
    // Redirect to installer
    header('Location: ../installer/install.php');
    exit;
}

// Try to connect to database to check if there are admin users
try {
    $db = new PDO('sqlite:' . $dataDir . '/app.sqlite');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Check if users table exists
    $stmt = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='users'");
    $tableExists = $stmt->fetch();
    
    if ($tableExists) {
        // Check if there are any admin users
        $stmt = $db->query("SELECT COUNT(*) FROM users WHERE is_admin = 1");
        $adminCount = $stmt->fetchColumn();
        
        if ($adminCount == 0) {
            // Redirect to installer
            header('Location: ../installer/install.php');
            exit;
        }
    } else {
        // Redirect to installer
        header('Location: ../installer/install.php');
        exit;
    }
} catch (PDOException $e) {
    // Database error, redirect to installer
    header('Location: ../installer/install.php');
    exit;
}

require_once __DIR__ . '/../includes/bootstrap.php';

require_admin_auth();

$db = app_db();
$svcCount = (int)$db->query('SELECT COUNT(*) FROM services')->fetchColumn();
$svcEnabled = (int)$db->query('SELECT COUNT(*) FROM services WHERE enabled=1')->fetchColumn();
$dlCount = (int)$db->query('SELECT COUNT(*) FROM downloads')->fetchColumn();
$lastDl = $db->query('SELECT downloaded_at, title FROM downloads ORDER BY id DESC LIMIT 1')->fetch(PDO::FETCH_ASSOC) ?: null;

?>
<?php include __DIR__ . '/_header.php'; ?>
  <div class="min-h-screen">
    <main class="max-w-6xl mx-auto p-6">
      <div class="grid md:grid-cols-3 gap-6">
        <div class="card rounded-xl p-6">
          <div class="text-sm text-gray-400">Servizi abilitati</div>
          <div class="text-3xl font-bold mt-2"><?= $svcEnabled ?>/<?= $svcCount ?></div>
        </div>
        <div class="card rounded-xl p-6">
          <div class="text-sm text-gray-400">Download registrati</div>
          <div class="text-3xl font-bold mt-2"><?= $dlCount ?></div>
        </div>
        <div class="card rounded-xl p-6">
          <div class="text-sm text-gray-400">Ultimo download</div>
          <div class="text-lg mt-2"><?= $lastDl ? htmlspecialchars($lastDl['title'].' — '.$lastDl['downloaded_at']) : '—' ?></div>
        </div>
      </div>

      <div class="card rounded-xl p-6 mt-8">
        <h2 class="text-lg font-semibold mb-4">Azioni rapide</h2>
        <div class="flex flex-wrap gap-3 text-sm">
          <a href="service.php" class="px-4 py-2 bg-gray-800 hover:bg-gray-700 rounded">Gestisci servizi</a>
          
          <a href="settings.php" class="px-4 py-2 bg-gray-800 hover:bg-gray-700 rounded">Cambia password</a>
          <a href="stats.php" class="px-4 py-2 bg-gray-800 hover:bg-gray-700 rounded">Vedi statistiche</a>
        </div>
      </div>
    </main>
  </div>
<?php include __DIR__ . '/_footer.php'; ?>
