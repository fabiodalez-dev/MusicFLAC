<?php
require_once __DIR__ . '/../includes/bootstrap.php';

// Redirect to installer if app not installed
if (!app_is_installed()) { header('Location: ' . base_url('installer/install.php')); exit; }

require_admin_auth();

$db = app_db();
$msg='';$err='';

// Get comprehensive stats
$stats = app_get_download_stats();

// Filters
$q = trim($_GET['q'] ?? '');
$type = $_GET['type'] ?? '';
$from = $_GET['from'] ?? '';
$to = $_GET['to'] ?? '';

$where = [];$params = [];
if ($q !== '') { $where[] = '(title LIKE :q OR spotify_url LIKE :q OR username LIKE :q)'; $params[':q'] = '%'.$q.'%'; }
if ($type === 'track' || $type === 'album') { $where[] = 'type = :type'; $params[':type'] = $type; }
if ($from) { $where[] = 'downloaded_at >= :from'; $params[':from'] = $from; }
if ($to) { $where[] = 'downloaded_at <= :to'; $params[':to'] = $to; }
$sqlWhere = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

if (isset($_GET['export']) && $_GET['export'] == '1') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=downloads.csv');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['id','username','type','title','spotify_url','service','file_size','ip_address','downloaded_at']);
    $stmt = $db->prepare("SELECT id,username,type,title,spotify_url,service,file_size,ip_address,downloaded_at FROM downloads $sqlWhere ORDER BY id DESC");
    $stmt->execute($params);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($out, $row);
    }
    fclose($out);
    exit;
}

$stmt = $db->prepare("SELECT * FROM downloads $sqlWhere ORDER BY id DESC LIMIT 500");
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

?>
<?php include __DIR__ . '/_header.php'; ?>
  <main class="max-w-6xl mx-auto p-6">
    
    <!-- Stats Overview -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
      <div class="card rounded-xl p-4 text-center">
        <div class="text-2xl font-bold text-blue-400"><?= number_format($stats['total_downloads']) ?></div>
        <div class="text-sm text-gray-400">Download Totali</div>
      </div>
      <div class="card rounded-xl p-4 text-center">
        <div class="text-2xl font-bold text-green-400"><?= number_format($stats['downloads_today']) ?></div>
        <div class="text-sm text-gray-400">Oggi</div>
      </div>
      <div class="card rounded-xl p-4 text-center">
        <div class="text-2xl font-bold text-yellow-400"><?= number_format($stats['downloads_this_week']) ?></div>
        <div class="text-sm text-gray-400">Questa Settimana</div>
      </div>
      <div class="card rounded-xl p-4 text-center">
        <div class="text-2xl font-bold text-purple-400"><?= number_format($stats['downloads_this_month']) ?></div>
        <div class="text-sm text-gray-400">Questo Mese</div>
      </div>
    </div>
    
    <!-- User Stats -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
      <div class="card rounded-xl p-4">
        <h3 class="text-lg font-semibold mb-3 flex items-center">
          <i class="fas fa-users mr-2 text-blue-400"></i> Statistiche Utenti
        </h3>
        <div class="space-y-2">
          <div class="flex justify-between">
            <span class="text-gray-400">Utenti Totali:</span>
            <span class="text-white font-semibold"><?= number_format($stats['total_users']) ?></span>
          </div>
          <div class="flex justify-between">
            <span class="text-gray-400">Utenti Attivi:</span>
            <span class="text-green-400 font-semibold"><?= number_format($stats['active_users']) ?></span>
          </div>
          <div class="flex justify-between">
            <span class="text-gray-400">In Attesa:</span>
            <span class="text-yellow-400 font-semibold"><?= number_format($stats['total_users'] - $stats['active_users']) ?></span>
          </div>
        </div>
      </div>
      
      <div class="card rounded-xl p-4">
        <h3 class="text-lg font-semibold mb-3 flex items-center">
          <i class="fas fa-crown mr-2 text-yellow-400"></i> Top Utenti
        </h3>
        <div class="space-y-2">
          <?php if (empty($stats['top_users'])): ?>
            <p class="text-gray-400 text-sm">Nessun download registrato</p>
          <?php else: ?>
            <?php foreach (array_slice($stats['top_users'], 0, 5) as $i => $user): ?>
              <div class="flex justify-between items-center">
                <div class="flex items-center">
                  <span class="w-6 h-6 bg-gray-700 rounded-full flex items-center justify-center text-xs mr-2"><?= $i+1 ?></span>
                  <span class="text-sm <?= $user['username'] === 'anonymous' ? 'text-gray-400 italic' : '' ?>">
                    <?= htmlspecialchars($user['username'] === 'anonymous' ? 'Utenti Anonimi' : $user['username']) ?>
                  </span>
                </div>
                <span class="text-green-400 font-semibold"><?= $user['download_count'] ?></span>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </div>
      
      <div class="card rounded-xl p-4">
        <h3 class="text-lg font-semibold mb-3 flex items-center">
          <i class="fas fa-chart-pie mr-2 text-purple-400"></i> Download per Servizio
        </h3>
        <div class="space-y-2">
          <?php if (empty($stats['top_services'])): ?>
            <p class="text-gray-400 text-sm">Nessun servizio utilizzato</p>
          <?php else: ?>
            <?php foreach ($stats['top_services'] as $service): ?>
              <div class="flex justify-between items-center">
                <div class="flex items-center">
                  <i class="fas fa-music mr-2 text-gray-400"></i>
                  <span class="text-sm capitalize"><?= htmlspecialchars($service['service'] ?: 'Sconosciuto') ?></span>
                </div>
                <span class="text-blue-400 font-semibold"><?= $service['download_count'] ?></span>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </div>
    </div>
    
    <!-- Download Types -->
    <div class="card rounded-xl p-4 mb-6">
      <h3 class="text-lg font-semibold mb-3 flex items-center">
        <i class="fas fa-chart-bar mr-2 text-green-400"></i> Tipi di Download
      </h3>
      <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <?php foreach ($stats['download_types'] as $type): ?>
          <div class="bg-gray-800 rounded-lg p-3 text-center">
            <div class="text-xl font-bold text-green-400"><?= $type['download_count'] ?></div>
            <div class="text-sm text-gray-400 capitalize"><?= htmlspecialchars($type['type']) ?></div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
    <form method="get" class="card rounded-xl p-4 mb-6 grid md:grid-cols-5 gap-3 items-end">
      <div>
        <label class="block text-xs text-gray-400 mb-1">Cerca</label>
        <input type="text" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="Titolo, link o username" class="w-full px-3 py-2 bg-gray-800 border border-gray-700 rounded">
      </div>
      <div>
        <label class="block text-xs text-gray-400 mb-1">Tipo</label>
        <select name="type" class="w-full px-3 py-2 bg-gray-800 border border-gray-700 rounded">
          <option value="">Tutti</option>
          <option value="track" <?= $type==='track'?'selected':'' ?>>Traccia</option>
          <option value="album" <?= $type==='album'?'selected':'' ?>>Album/Playlist</option>
        </select>
      </div>
      <div>
        <label class="block text-xs text-gray-400 mb-1">Da</label>
        <input type="datetime-local" name="from" value="<?= htmlspecialchars($from) ?>" class="w-full px-3 py-2 bg-gray-800 border border-gray-700 rounded">
      </div>
      <div>
        <label class="block text-xs text-gray-400 mb-1">A</label>
        <input type="datetime-local" name="to" value="<?= htmlspecialchars($to) ?>" class="w-full px-3 py-2 bg-gray-800 border border-gray-700 rounded">
      </div>
      <div class="flex gap-2">
        <button class="px-4 py-2 bg-gray-700 hover:bg-gray-600 rounded">Filtra</button>
        <a class="px-4 py-2 bg-green-600 hover:bg-green-700 rounded" href="?<?= http_build_query(array_merge($_GET,["export"=>1])) ?>">Export CSV</a>
      </div>
    </form>

    <div class="card rounded-xl p-6">
      <!-- Mobile stacked list -->
      <div class="md:hidden space-y-4">
        <?php if (!$rows): ?>
          <div class="text-gray-400">Nessun download registrato</div>
        <?php else: foreach ($rows as $r): ?>
          <div class="bg-gray-900 border border-gray-800 rounded-lg p-3">
            <div class="flex justify-between items-start mb-2">
              <div class="text-xs text-gray-400">Quando</div>
              <?php if ($r['username']): ?>
                <span class="bg-blue-600 px-2 py-1 rounded-full text-xs"><?= htmlspecialchars($r['username']) ?></span>
              <?php endif; ?>
            </div>
            <div class="text-gray-300 mb-2"><?= htmlspecialchars($r['downloaded_at']) ?></div>
            <div class="flex gap-4 mb-2">
              <div>
                <div class="text-xs text-gray-400">Tipo</div>
                <div class="uppercase text-gray-300"><?= htmlspecialchars($r['type']) ?></div>
              </div>
              <?php if ($r['service']): ?>
                <div>
                  <div class="text-xs text-gray-400">Servizio</div>
                  <div class="capitalize text-green-400"><?= htmlspecialchars($r['service']) ?></div>
                </div>
              <?php endif; ?>
              <?php if ($r['file_size']): ?>
                <div>
                  <div class="text-xs text-gray-400">Dimensione</div>
                  <div class="text-yellow-400"><?= number_format($r['file_size'] / 1024 / 1024, 1) ?> MB</div>
                </div>
              <?php endif; ?>
            </div>
            <div class="text-xs text-gray-400">Titolo</div>
            <div class="font-semibold mb-2"><?= htmlspecialchars($r['title']) ?></div>
            <div class="text-xs text-gray-400">Spotify</div>
            <div class="break-all"><a class="text-blue-300" href="<?= htmlspecialchars($r['spotify_url']) ?>" target="_blank" rel="noopener"><?= htmlspecialchars($r['spotify_url']) ?></a></div>
            <?php if ($r['ip_address']): ?>
              <div class="text-xs text-gray-500 mt-2">IP: <?= htmlspecialchars($r['ip_address']) ?></div>
            <?php endif; ?>
          </div>
        <?php endforeach; endif; ?>
      </div>

      <!-- Desktop table -->
      <div class="hidden md:block">
        <table class="text-sm w-full">
          <thead>
            <tr class="text-gray-400 border-b border-gray-700">
              <th class="text-left py-3">Quando</th>
              <th class="text-left py-3">Utente</th>
              <th class="text-left py-3">Tipo</th>
              <th class="text-left py-3">Servizio</th>
              <th class="text-left py-3">Dimensione</th>
              <th class="text-left py-3">Titolo</th>
              <th class="text-left py-3">IP</th>
            </tr>
          </thead>
          <tbody>
            <?php if (!$rows): ?>
              <tr><td colspan="7" class="text-gray-400 py-3 text-center">Nessun download registrato</td></tr>
            <?php else: foreach ($rows as $r): ?>
              <tr class="border-b border-gray-800 hover:bg-gray-900">
                <td class="whitespace-nowrap text-gray-300 py-2 pr-3"><?= date('d/m/Y H:i', strtotime($r['downloaded_at'])) ?></td>
                <td class="py-2 pr-3">
                  <?php if ($r['username'] && $r['username'] !== 'anonymous'): ?>
                    <span class="bg-blue-600 px-2 py-1 rounded-full text-xs"><?= htmlspecialchars($r['username']) ?></span>
                  <?php else: ?>
                    <span class="text-gray-400 text-xs italic">anonymous</span>
                  <?php endif; ?>
                </td>
                <td class="uppercase text-gray-400 py-2 pr-3"><?= htmlspecialchars($r['type']) ?></td>
                <td class="py-2 pr-3">
                  <?php if ($r['service']): ?>
                    <span class="capitalize text-green-400"><?= htmlspecialchars($r['service']) ?></span>
                  <?php else: ?>
                    <span class="text-gray-500">-</span>
                  <?php endif; ?>
                </td>
                <td class="py-2 pr-3">
                  <?php if ($r['file_size']): ?>
                    <span class="text-yellow-400"><?= number_format($r['file_size'] / 1024 / 1024, 1) ?> MB</span>
                  <?php else: ?>
                    <span class="text-gray-500">-</span>
                  <?php endif; ?>
                </td>
                <td class="font-semibold py-2 pr-3">
                  <?php $title = (string)($r['title'] ?? ''); $surl = (string)($r['spotify_url'] ?? ''); ?>
                  <div class="max-w-xs truncate" title="<?= htmlspecialchars($title) ?>"><?= htmlspecialchars($title) ?></div>
                  <div class="text-xs text-blue-300 mt-1">
                    <?php if ($surl !== ''): ?>
                      <a href="<?= htmlspecialchars($surl) ?>" target="_blank" rel="noopener" class="hover:underline">
                        <?= htmlspecialchars((string)(parse_url($surl, PHP_URL_PATH) ?? $surl)) ?>
                      </a>
                    <?php else: ?>
                      <span class="text-gray-500">—</span>
                    <?php endif; ?>
                  </div>
                </td>
                <td class="text-gray-500 py-2 text-xs"><?= htmlspecialchars((string)($r['ip_address'] ?? '')) ?></td>
              </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </main>
<?php include __DIR__ . '/_footer.php'; ?>
