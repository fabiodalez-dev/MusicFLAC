<?php
require_once __DIR__ . '/../includes/bootstrap.php';

// Redirect to installer if app not installed
if (!app_is_installed()) { header('Location: ' . base_url('installer/install.php')); exit; }

require_admin_auth();

$msg='';$err='';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!app_verify_csrf($_POST['csrf'] ?? '')) { 
        $err = 'Token CSRF non valido'; 
    } else {
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        
        if (empty($current_password) || empty($new_password)) {
            $err = 'Tutti i campi sono obbligatori';
        } elseif (strlen($new_password) < 6) {
            $err = 'La nuova password deve essere di almeno 6 caratteri';
        } elseif ($new_password !== $confirm_password) {
            $err = 'Le password non corrispondono';
        } else {
            // Get current user
            $user = user_get_current();
            if (!$user) {
                $err = 'Utente non trovato';
            } else {
                // Verify current password
                $db = app_db();
                $stmt = $db->prepare('SELECT password_hash FROM users WHERE id = ?');
                $stmt->execute([$user['id']]);
                $current_hash = $stmt->fetchColumn();
                
                if (!password_verify($current_password, $current_hash)) {
                    $err = 'Password attuale non corretta';
                } else {
                    // Update password
                    $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
                    $stmt = $db->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
                    if ($stmt->execute([$new_hash, $user['id']])) {
                        $msg = 'Password cambiata con successo!';
                    } else {
                        $err = 'Errore nel cambio password';
                    }
                }
            }
        }
    }
}

$csrf = app_csrf_token();
?>
<?php include __DIR__ . '/_header.php'; ?>
  <main class="max-w-2xl mx-auto p-6">
    <?php if ($msg): ?><div class="mb-4 p-3 rounded border border-green-700 bg-green-900 bg-opacity-30 text-green-200"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
    <?php if ($err): ?><div class="mb-4 p-3 rounded border border-red-700 bg-red-900 bg-opacity-30 text-red-200"><?= htmlspecialchars($err) ?></div><?php endif; ?>

    <div class="card rounded-xl p-6">
      <h1 class="text-2xl font-bold mb-6 flex items-center">
        <i class="fas fa-key mr-3 text-yellow-400"></i>
        Cambia Password
      </h1>
      
      <form method="post" class="space-y-6">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
        
        <div>
          <label for="current_password" class="block text-sm font-medium text-gray-300 mb-2">Password Attuale</label>
          <div class="relative">
            <i class="fas fa-lock absolute left-3 top-3 text-gray-400"></i>
            <input type="password" id="current_password" name="current_password" required
                   class="w-full pl-10 pr-4 py-3 bg-gray-700 border border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent text-white"
                   placeholder="Inserisci la password attuale">
          </div>
        </div>
        
        <div>
          <label for="new_password" class="block text-sm font-medium text-gray-300 mb-2">Nuova Password</label>
          <div class="relative">
            <i class="fas fa-key absolute left-3 top-3 text-gray-400"></i>
            <input type="password" id="new_password" name="new_password" required minlength="6"
                   class="w-full pl-10 pr-4 py-3 bg-gray-700 border border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent text-white"
                   placeholder="Almeno 6 caratteri">
          </div>
        </div>
        
        <div>
          <label for="confirm_password" class="block text-sm font-medium text-gray-300 mb-2">Conferma Nuova Password</label>
          <div class="relative">
            <i class="fas fa-key absolute left-3 top-3 text-gray-400"></i>
            <input type="password" id="confirm_password" name="confirm_password" required minlength="6"
                   class="w-full pl-10 pr-4 py-3 bg-gray-700 border border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent text-white"
                   placeholder="Ripeti la nuova password">
          </div>
        </div>
        
        <div class="bg-yellow-900 bg-opacity-30 border border-yellow-700 rounded-lg p-4">
          <div class="flex">
            <i class="fas fa-exclamation-triangle text-yellow-400 mt-0.5 mr-2"></i>
            <div class="text-sm text-yellow-200">
              <p class="font-semibold">Attenzione:</p>
              <p>Dopo aver cambiato la password, dovrai utilizzare la nuova password per i prossimi accessi.</p>
            </div>
          </div>
        </div>
        
        <div class="flex gap-4">
          <button type="submit" class="px-6 py-3 bg-green-600 hover:bg-green-700 rounded-lg font-semibold transition flex items-center">
            <i class="fas fa-save mr-2"></i>
            Cambia Password
          </button>
          <a href="index.php" class="px-6 py-3 bg-gray-600 hover:bg-gray-700 rounded-lg font-semibold transition flex items-center">
            <i class="fas fa-arrow-left mr-2"></i>
            Annulla
          </a>
        </div>
      </form>
    </div>
  </main>
  
  <script>
    // Password confirmation validation
    document.getElementById('confirm_password').addEventListener('input', function() {
        const password = document.getElementById('new_password').value;
        const confirmPassword = this.value;
        
        if (confirmPassword && password !== confirmPassword) {
            this.setCustomValidity('Le password non corrispondono');
        } else {
            this.setCustomValidity('');
        }
    });
    
    document.getElementById('new_password').addEventListener('input', function() {
        const confirmPassword = document.getElementById('confirm_password');
        if (confirmPassword.value) {
            confirmPassword.dispatchEvent(new Event('input'));
        }
    });
  </script>
<?php include __DIR__ . '/_footer.php'; ?>
