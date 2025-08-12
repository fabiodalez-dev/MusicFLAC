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
    if (!app_verify_csrf($_POST['csrf'] ?? '')) { 
        $err = 'Token CSRF non valido'; 
    } else {
        $action = $_POST['action'] ?? '';
        $user_id = (int)($_POST['user_id'] ?? 0);
        
        if ($action === 'activate' && $user_id > 0) {
            if (user_activate($user_id, true)) {
                $msg = 'Utente attivato con successo';
            } else {
                $err = 'Errore nell\'attivazione dell\'utente';
            }
        } elseif ($action === 'deactivate' && $user_id > 0) {
            if (user_activate($user_id, false)) {
                $msg = 'Utente disattivato con successo';
            } else {
                $err = 'Errore nella disattivazione dell\'utente';
            }
        } elseif ($action === 'make_admin' && $user_id > 0) {
            if (user_set_admin($user_id, true)) {
                $msg = 'Utente promosso ad amministratore';
            } else {
                $err = 'Errore nella promozione dell\'utente';
            }
        } elseif ($action === 'remove_admin' && $user_id > 0) {
            if (user_set_admin($user_id, false)) {
                $msg = 'Privilegi di amministratore rimossi';
            } else {
                $err = 'Errore nella rimozione dei privilegi';
            }
        } elseif ($action === 'delete' && $user_id > 0) {
            // Don't allow deleting yourself
            if ($user_id == $_SESSION['user_id']) {
                $err = 'Non puoi eliminare il tuo stesso account';
            } else {
                $stmt = $db->prepare('DELETE FROM users WHERE id = ?');
                if ($stmt->execute([$user_id])) {
                    $msg = 'Utente eliminato con successo';
                } else {
                    $err = 'Errore nell\'eliminazione dell\'utente';
                }
            }
        }
    }
}

$users = user_list_all();
$csrf = app_csrf_token();
?>
<?php include __DIR__ . '/_header.php'; ?>
  <main class="max-w-7xl mx-auto p-6">
    <?php if ($msg): ?><div class="mb-4 p-3 rounded border border-green-700 bg-green-900 bg-opacity-30 text-green-200"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
    <?php if ($err): ?><div class="mb-4 p-3 rounded border border-red-700 bg-red-900 bg-opacity-30 text-red-200"><?= htmlspecialchars($err) ?></div><?php endif; ?>

    <div class="flex justify-between items-center mb-6">
      <h1 class="text-2xl font-bold">Gestione Utenti</h1>
      <div class="flex gap-2">
        <span class="bg-blue-600 px-3 py-1 rounded-full text-sm">Totale: <?= count($users) ?></span>
        <span class="bg-green-600 px-3 py-1 rounded-full text-sm">Attivi: <?= count(array_filter($users, fn($u) => $u['is_active'])) ?></span>
        <span class="bg-yellow-600 px-3 py-1 rounded-full text-sm">In Attesa: <?= count(array_filter($users, fn($u) => !$u['is_active'])) ?></span>
      </div>
    </div>

    <div class="card rounded-xl p-6">
      <!-- Mobile cards -->
      <div class="md:hidden space-y-4">
        <?php foreach ($users as $user): ?>
          <div class="bg-gray-900 border border-gray-800 rounded-lg p-4">
            <div class="flex items-center justify-between mb-3">
              <div>
                <div class="font-semibold flex items-center gap-2">
                  <?= htmlspecialchars($user['username']) ?>
                  <?php if ($user['is_admin']): ?>
                    <span class="bg-red-600 px-2 py-1 rounded-full text-xs">Admin</span>
                  <?php endif; ?>
                  <span class="bg-<?= $user['is_active'] ? 'green' : 'yellow' ?>-600 px-2 py-1 rounded-full text-xs">
                    <?= $user['is_active'] ? 'Attivo' : 'In Attesa' ?>
                  </span>
                </div>
                <div class="text-sm text-gray-400 mt-1"><?= htmlspecialchars($user['email']) ?></div>
                <div class="text-xs text-gray-500 mt-1">
                  Registrato: <?= date('d/m/Y', strtotime($user['created_at'])) ?>
                  <?php if ($user['last_login']): ?>
                    | Ultimo accesso: <?= date('d/m/Y H:i', strtotime($user['last_login'])) ?>
                  <?php endif; ?>
                </div>
              </div>
            </div>
            
            <?php if ($user['id'] != $_SESSION['user_id']): ?>
              <div class="flex flex-wrap gap-2">
                <form method="post" class="inline">
                  <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
                  <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                  <?php if ($user['is_active']): ?>
                    <button type="submit" name="action" value="deactivate" class="bg-yellow-600 hover:bg-yellow-700 px-3 py-2 rounded text-sm font-medium text-white" onclick="return confirm('Disattivare questo utente?')">
                      <i class="fas fa-pause mr-1"></i> Disattiva
                    </button>
                  <?php else: ?>
                    <button type="submit" name="action" value="activate" class="bg-green-600 hover:bg-green-700 px-3 py-2 rounded text-sm font-medium text-white">
                      <i class="fas fa-check mr-1"></i> Attiva
                    </button>
                  <?php endif; ?>
                  
                  <?php if ($user['is_admin']): ?>
                    <button type="submit" name="action" value="remove_admin" class="bg-orange-600 hover:bg-orange-700 px-3 py-2 rounded text-sm font-medium text-white" onclick="return confirm('Rimuovere privilegi admin?')">
                      <i class="fas fa-user-minus mr-1"></i> Rimuovi Admin
                    </button>
                  <?php else: ?>
                    <button type="submit" name="action" value="make_admin" class="bg-purple-600 hover:bg-purple-700 px-3 py-2 rounded text-sm font-medium text-white" onclick="return confirm('Rendere questo utente amministratore?')">
                      <i class="fas fa-user-shield mr-1"></i> Rendi Admin
                    </button>
                  <?php endif; ?>
                  
                  <button type="submit" name="action" value="delete" class="bg-red-600 hover:bg-red-700 px-3 py-2 rounded text-sm font-medium text-white" onclick="return confirm('ATTENZIONE: Eliminare definitivamente questo utente? Questa azione non può essere annullata!')">
                    <i class="fas fa-trash mr-1"></i> Elimina
                  </button>
                </form>
              </div>
            <?php else: ?>
              <p class="text-blue-400 text-xs">
                <i class="fas fa-user"></i> Sei tu
              </p>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>

      <!-- Desktop table -->
      <div class="hidden md:block overflow-x-auto">
        <table class="text-sm w-full">
          <thead>
            <tr class="text-gray-400 border-b border-gray-700">
              <th class="text-left py-3">Utente</th>
              <th class="text-left py-3">Email</th>
              <th class="text-left py-3">Stato</th>
              <th class="text-left py-3">Ruolo</th>
              <th class="text-left py-3">Registrazione</th>
              <th class="text-left py-3">Ultimo Accesso</th>
              <th class="text-center py-3">Azioni</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($users as $user): ?>
            <tr class="border-b border-gray-800 hover:bg-gray-900">
              <td class="py-3">
                <div class="font-semibold"><?= htmlspecialchars($user['username']) ?></div>
                <?php if ($user['id'] == $_SESSION['user_id']): ?>
                  <div class="text-xs text-blue-400"><i class="fas fa-user"></i> Sei tu</div>
                <?php endif; ?>
              </td>
              <td class="py-3"><?= htmlspecialchars($user['email']) ?></td>
              <td class="py-3">
                <span class="px-2 py-1 rounded-full text-xs <?= $user['is_active'] ? 'bg-green-600' : 'bg-yellow-600' ?>">
                  <?= $user['is_active'] ? 'Attivo' : 'In Attesa' ?>
                </span>
              </td>
              <td class="py-3">
                <?php if ($user['is_admin']): ?>
                  <span class="bg-red-600 px-2 py-1 rounded-full text-xs">Admin</span>
                <?php else: ?>
                  <span class="bg-gray-600 px-2 py-1 rounded-full text-xs">Utente</span>
                <?php endif; ?>
              </td>
              <td class="py-3 text-gray-400"><?= date('d/m/Y H:i', strtotime($user['created_at'])) ?></td>
              <td class="py-3 text-gray-400">
                <?= $user['last_login'] ? date('d/m/Y H:i', strtotime($user['last_login'])) : 'Mai' ?>
              </td>
              <td class="py-3">
                <?php if ($user['id'] != $_SESSION['user_id']): ?>
                  <div class="flex gap-2">
                    <form method="post" class="inline">
                      <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
                      <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                      <?php if ($user['is_active']): ?>
                        <button type="submit" name="action" value="deactivate" class="bg-yellow-600 hover:bg-yellow-700 px-2 py-1 rounded text-xs text-white font-medium" onclick="return confirm('Disattivare questo utente?')" title="Disattiva">
                          <i class="fas fa-pause mr-1"></i>Disattiva
                        </button>
                      <?php else: ?>
                        <button type="submit" name="action" value="activate" class="bg-green-600 hover:bg-green-700 px-2 py-1 rounded text-xs text-white font-medium" title="Attiva">
                          <i class="fas fa-check mr-1"></i>Attiva
                        </button>
                      <?php endif; ?>
                      
                      <?php if ($user['is_admin']): ?>
                        <button type="submit" name="action" value="remove_admin" class="bg-orange-600 hover:bg-orange-700 px-2 py-1 rounded text-xs text-white font-medium" onclick="return confirm('Rimuovere privilegi admin?')" title="Rimuovi Admin">
                          <i class="fas fa-user-minus mr-1"></i>Rimuovi
                        </button>
                      <?php else: ?>
                        <button type="submit" name="action" value="make_admin" class="bg-purple-600 hover:bg-purple-700 px-2 py-1 rounded text-xs text-white font-medium" onclick="return confirm('Rendere questo utente amministratore?')" title="Rendi Admin">
                          <i class="fas fa-user-shield mr-1"></i>Admin
                        </button>
                      <?php endif; ?>
                      
                      <button type="submit" name="action" value="delete" class="bg-red-600 hover:bg-red-700 px-2 py-1 rounded text-xs text-white font-medium" onclick="return confirm('ATTENZIONE: Eliminare definitivamente questo utente? Questa azione non può essere annullata!')" title="Elimina">
                        <i class="fas fa-trash mr-1"></i>Elimina
                      </button>
                    </form>
                  </div>
                <?php endif; ?>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      
      <?php if (empty($users)): ?>
        <div class="text-center py-12">
          <i class="fas fa-users text-4xl text-gray-600 mb-4"></i>
          <p class="text-gray-400">Nessun utente trovato</p>
        </div>
      <?php endif; ?>
    </div>
  </main>
<?php include __DIR__ . '/_footer.php'; ?>
