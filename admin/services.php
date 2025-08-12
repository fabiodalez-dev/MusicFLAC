<?php
@ini_set('display_errors','0');
error_reporting(E_ALL);
require_once __DIR__ . '/../includes/app.php';
require_once __DIR__ . '/../includes/auth.php';

// Redirect to installer if app not installed
if (!app_is_installed()) { header('Location: ' . base_url('installer/install.php')); exit; }

require_admin_auth();

$db = app_db();
$msg='';$err='';
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!app_verify_csrf($_POST['csrf'] ?? '')) { $err = 'Token CSRF non valido'; }
    else {
        // Update services enable/disable and endpoints
        $names = array_keys(app_list_services());
        foreach ($names as $name) {
            $enabled = isset($_POST['enabled'][$name]) ? 1 : 0;
            $endpoint = trim($_POST['endpoint'][$name] ?? '');
            $stmt = $db->prepare('UPDATE services SET enabled = ?, endpoint = ? WHERE name = ?');
            $stmt->execute([$enabled, $endpoint, $name]);
        }
        $msg = 'Servizi aggiornati';
    }
}

$services = app_list_services();
$csrf = app_csrf_token();
?>
<?php include __DIR__ . '/_header.php'; ?>
  <main class="max-w-5xl mx-auto p-6">
    <?php if ($msg): ?><div class="mb-4 p-3 rounded border border-green-700 bg-green-900 bg-opacity-30 text-green-200"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
    <?php if ($err): ?><div class="mb-4 p-3 rounded border border-red-700 bg-red-900 bg-opacity-30 text-red-200"><?= htmlspecialchars($err) ?></div><?php endif; ?>

    <form method="post" class="card rounded-xl p-6">
      <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">

      <!-- Mobile cards -->
      <div class="md:hidden space-y-4">
        <?php foreach ($services as $key => $s): ?>
          <div class="bg-gray-900 border border-gray-800 rounded-lg p-4">
            <div class="flex items-center justify-between mb-3">
              <div class="font-semibold"><?= htmlspecialchars($s['label']) ?> <span class="text-gray-500">(<?= htmlspecialchars($key) ?>)</span></div>
              <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="enabled[<?= htmlspecialchars($key) ?>]" <?= $s['enabled'] ? 'checked' : '' ?>> <span>Abilitato</span></label>
            </div>
            <label class="block text-xs text-gray-400 mb-1">Endpoint base (override)</label>
            <input type="text" name="endpoint[<?= htmlspecialchars($key) ?>]" value="<?= htmlspecialchars($s['endpoint'] ?? '') ?>" placeholder="https://..." class="w-full px-3 py-2 bg-gray-800 border border-gray-700 rounded">
          </div>
        <?php endforeach; ?>
      </div>

      <!-- Desktop table -->
      <div class="hidden md:block">
        <table class="text-sm w-full">
          <thead>
            <tr class="text-gray-400">
              <th class="text-left">Servizio</th>
              <th class="text-left">Abilitato</th>
              <th class="text-left">Endpoint base (override)</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($services as $key => $s): ?>
            <tr>
              <td class="font-semibold py-2 pr-3"><?= htmlspecialchars($s['label']) ?> <span class="text-gray-500">(<?= htmlspecialchars($key) ?>)</span></td>
              <td class="py-2 pr-3">
                <input type="checkbox" name="enabled[<?= htmlspecialchars($key) ?>]" <?= $s['enabled'] ? 'checked' : '' ?> class="h-4 w-4">
              </td>
              <td class="py-2">
                <input type="text" name="endpoint[<?= htmlspecialchars($key) ?>]" value="<?= htmlspecialchars($s['endpoint'] ?? '') ?>" placeholder="https://..." class="w-full px-3 py-2 bg-gray-800 border border-gray-700 rounded">
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <div class="mt-4">
        <button class="px-4 py-2 bg-green-600 hover:bg-green-700 rounded"><span>Salva</span></button>
      </div>
    </form>
  </main>
<?php include __DIR__ . '/_footer.php'; ?>
