<?php
require_once __DIR__ . '/../includes/bootstrap.php';

// Redirect to installer if app not installed
if (!app_is_installed()) { header('Location: ' . base_url('installer/install.php')); exit; }

require_admin_auth();

$msg = '';$err = '';
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!app_verify_csrf($_POST['csrf'] ?? '')) { 
        $err = 'Token CSRF non valido'; 
    } else {
        // Handle frontend debug toggle (independent form with hidden default)
        if (isset($_POST['frontend_debug'])) {
            $debugVal = ($_POST['frontend_debug'] === '1') ? '1' : '0';
            app_set_setting('frontend_debug', $debugVal);
            $msg = 'Impostazioni salvate con successo';
        }
        // Handle production mode toggle
        if (isset($_POST['production_mode'])) {
            $prodVal = ($_POST['production_mode'] === '1') ? '1' : '0';
            app_set_setting('production_mode', $prodVal);
            $msg = 'Impostazioni salvate con successo';
        }
        // Handle backend debug toggle (enable/disable debug.log)
        if (isset($_POST['backend_debug'])) {
            $backendVal = ($_POST['backend_debug'] === '1') ? '1' : '0';
            app_set_setting('backend_debug', $backendVal);
            $msg = 'Impostazioni salvate con successo';
        }
        // Handle download concurrency setting
        if (isset($_POST['download_concurrency'])) {
            $concurrency = (int)$_POST['download_concurrency'];
            if ($concurrency >= 1 && $concurrency <= 8) {
                app_set_setting('download_concurrency', (string)$concurrency);
                $msg = 'Impostazioni salvate con successo';
            } else {
                $err = 'Il numero di download simultanei deve essere tra 1 e 8';
            }
        }
        
        // Handle admin user password change
        if (isset($_POST['new_password']) && $_POST['new_password'] !== '') {
            $current_password = $_POST['current_password'] ?? '';
            $new_password = $_POST['new_password'] ?? '';
            $confirm_password = $_POST['confirm_password'] ?? '';
            
            // Validate passwords
            if (strlen($new_password) < 6) {
                $err = 'La nuova password deve essere lunga almeno 6 caratteri';
            } elseif ($new_password !== $confirm_password) {
                $err = 'Le password non coincidono';
            } else {
                // Verify current password
                $user = user_get_current();
                if ($user) {
                    $db = app_db();
                    $stmt = $db->prepare('SELECT password_hash FROM users WHERE id = ?');
                    $stmt->execute([$user['id']]);
                    $user_data = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($user_data && password_verify($current_password, $user_data['password_hash'])) {
                        // Update password
                        $stmt = $db->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
                        if ($stmt->execute([password_hash($new_password, PASSWORD_DEFAULT), $user['id']])) {
                            $msg = 'Password aggiornata con successo';
                        } else {
                            $err = 'Errore nell\'aggiornamento della password';
                        }
                    } else {
                        $err = 'Password attuale non corretta';
                    }
                } else {
                    $err = 'Errore nell\'identificazione dell\'utente';
                }
            }
        }
    }
}

$csrf = app_csrf_token();
$current_concurrency = (int)app_get_setting('download_concurrency', 4);
$frontend_debug = (int)app_get_setting('frontend_debug', 0);
?>
<?php include __DIR__ . '/_header.php'; ?>
  <main class="max-w-4xl mx-auto p-6">
    <?php if ($msg): ?><div class="mb-4 p-3 rounded border border-green-700 bg-green-900 bg-opacity-30 text-green-200"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
    <?php if ($err): ?><div class="mb-4 p-3 rounded border border-red-700 bg-red-900 bg-opacity-30 text-red-200"><?= htmlspecialchars($err) ?></div><?php endif; ?>

    <div class="grid md:grid-cols-2 gap-6">
      <!-- Production Mode -->
      <div class="card rounded-xl p-6">
        <h2 class="text-lg font-semibold mb-4">Production Mode</h2>
        <form method="post">
          <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
          <input type="hidden" name="production_mode" value="0">
          <?php $production_mode = (int)app_get_setting('production_mode', 0); ?>
          <div class="mb-4 flex items-center justify-between">
            <label class="block text-gray-300 mr-4" for="production_mode">Enable production hardening</label>
            <label class="inline-flex items-center cursor-pointer">
              <input type="checkbox" id="production_mode" name="production_mode" value="1" class="hidden" <?= $production_mode === 1 ? 'checked' : '' ?>>
              <span class="relative inline-block w-12 h-6 bg-gray-700 rounded-full transition">
                <span class="dot absolute left-1 top-1 w-4 h-4 bg-white rounded-full transition transform <?= $production_mode === 1 ? 'translate-x-6 bg-green-400' : '' ?>"></span>
              </span>
            </label>
          </div>
          <ul class="text-sm text-gray-400 list-disc ml-5 mb-4">
            <li>Installer access is blocked</li>
            <li>Signup link is hidden</li>
          </ul>
          <button class="px-4 py-2 bg-green-600 hover:bg-green-700 rounded">Save</button>
        </form>
      </div>
      <!-- UI / Debug Settings -->
      <div class="card rounded-xl p-6">
        <h2 class="text-lg font-semibold mb-4">Interfaccia</h2>
        <form method="post">
          <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
          <!-- Hidden default ensures value is submitted when checkbox unchecked -->
          <input type="hidden" name="frontend_debug" value="0">
          <div class="mb-4 flex items-center justify-between">
            <label class="block text-gray-300 mr-4" for="frontend_debug">Mostra finestra di debug</label>
            <label class="inline-flex items-center cursor-pointer">
              <input type="checkbox" id="frontend_debug" name="frontend_debug" value="1" class="hidden" <?= $frontend_debug === 1 ? 'checked' : '' ?>>
              <span class="relative inline-block w-12 h-6 bg-gray-700 rounded-full transition">
                <span class="dot absolute left-1 top-1 w-4 h-4 bg-white rounded-full transition transform <?= $frontend_debug === 1 ? 'translate-x-6 bg-green-400' : '' ?>"></span>
              </span>
            </label>
          </div>
          <div class="text-sm text-gray-400 mb-4">Abilita il pulsante e il pannello di debug nel frontend.</div>
          <button class="px-4 py-2 bg-green-600 hover:bg-green-700 rounded">Salva Impostazioni</button>
        </form>
      </div>

      <!-- Backend Logging -->
      <div class="card rounded-xl p-6">
        <h2 class="text-lg font-semibold mb-4">Log Applicazione</h2>
        <form method="post">
          <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
          <input type="hidden" name="backend_debug" value="0">
          <div class="mb-4 flex items-center justify-between">
            <label class="block text-gray-300 mr-4" for="backend_debug">Abilita debug.log (PHP)</label>
            <label class="inline-flex items-center cursor-pointer">
              <?php $backend_debug = (int)app_get_setting('backend_debug', 0); ?>
              <input type="checkbox" id="backend_debug" name="backend_debug" value="1" class="hidden" <?= $backend_debug === 1 ? 'checked' : '' ?>>
              <span class="relative inline-block w-12 h-6 bg-gray-700 rounded-full transition">
                <span class="dot absolute left-1 top-1 w-4 h-4 bg-white rounded-full transition transform <?= $backend_debug === 1 ? 'translate-x-6 bg-green-400' : '' ?>"></span>
              </span>
            </label>
          </div>
          <div class="text-sm text-gray-400 mb-4">Quando attivo, l'app scrive eventi in <code>debug.log</code>. Disattivalo in produzione.</div>
          <button class="px-4 py-2 bg-green-600 hover:bg-green-700 rounded">Salva Impostazioni</button>
        </form>
      </div>

      <!-- Download Concurrency Settings -->
      <div class="card rounded-xl p-6">
        <h2 class="text-lg font-semibold mb-4">Impostazioni Download</h2>
        <form method="post">
          <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
          <div class="mb-4">
            <label class="block text-gray-300 mb-2" for="download_concurrency">Download Simultanei</label>
            <select id="download_concurrency" name="download_concurrency" class="w-full px-4 py-3 bg-gray-800 border border-gray-700 rounded">
              <?php for ($i = 1; $i <= 8; $i++): ?>
                <option value="<?= $i ?>" <?= $i === $current_concurrency ? 'selected' : '' ?>><?= $i ?> download simultanei</option>
              <?php endfor; ?>
            </select>
            <div class="text-sm text-gray-400 mt-1">Numero di file scaricati contemporaneamente (1-8)</div>
          </div>
          <button class="px-4 py-2 bg-green-600 hover:bg-green-700 rounded">Salva Impostazioni</button>
        </form>
      </div>
      
      <!-- Admin Password Change -->
      <div class="card rounded-xl p-6">
        <h2 class="text-lg font-semibold mb-4">Cambia Password Admin</h2>
        <form method="post">
          <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
          <div class="mb-4">
            <label class="block text-gray-300 mb-2" for="current_password">Password Attuale</label>
            <input type="password" id="current_password" name="current_password" class="w-full px-4 py-3 bg-gray-800 border border-gray-700 rounded" autocomplete="current-password" required>
          </div>
          <div class="mb-4">
            <label class="block text-gray-300 mb-2" for="new_password">Nuova Password</label>
            <input type="password" id="new_password" name="new_password" class="w-full px-4 py-3 bg-gray-800 border border-gray-700 rounded" autocomplete="new-password" required>
            <div class="text-sm text-gray-400 mt-1">Minimo 6 caratteri</div>
          </div>
          <div class="mb-6">
            <label class="block text-gray-300 mb-2" for="confirm_password">Conferma Nuova Password</label>
            <input type="password" id="confirm_password" name="confirm_password" class="w-full px-4 py-3 bg-gray-800 border border-gray-700 rounded" autocomplete="new-password" required>
          </div>
          <button class="px-4 py-2 bg-green-600 hover:bg-green-700 rounded">Aggiorna Password</button>
        </form>
      </div>
    </div>
  </main>
<?php include __DIR__ . '/_footer.php'; ?>
