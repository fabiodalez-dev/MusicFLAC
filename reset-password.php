<?php
@ini_set('display_errors', '0');
error_reporting(E_ALL);
require_once __DIR__ . '/includes/bootstrap.php';

$token = $_GET['token'] ?? '';
$msg = '';
$err = '';
$success = false;
$csrf = app_csrf_token();

if (empty($token)) {
    $err = 'Invalid reset link';
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && !empty($token)) {
    if (!app_verify_csrf($_POST['csrf'] ?? '')) {
        $err = 'Token CSRF non valido';
    } else {
        $password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        
        if (empty($password)) {
            $err = 'Password is required';
        } elseif (strlen($password) < 6) {
            $err = 'Password must be at least 6 characters';
        } elseif ($password !== $confirm_password) {
            $err = 'Passwords do not match';
        } else {
            $result = user_reset_password($token, $password);
            if ($result['success']) {
                $success = true;
                $msg = 'Password reset successfully! You can now login with your new password.';
            } else {
                $err = $result['error'];
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - MusicFLAC</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="css/style.css">
</head>
<body class="min-h-screen bg-gradient-to-br from-gray-900 via-gray-800 to-gray-900">
    <div class="min-h-screen flex items-center justify-center px-4">
        <div class="max-w-md w-full">
            <div class="text-center mb-8">
                <h1 class="text-4xl font-bold text-white mb-2">MusicFLAC</h1>
                <p class="text-gray-400">Imposta nuova password</p>
            </div>
            
            <div class="bg-gray-800 rounded-2xl shadow-2xl p-8 border border-gray-700">
                <?php if ($msg): ?>
                    <div class="mb-6 p-4 rounded-lg bg-green-900 bg-opacity-50 border border-green-700 text-green-200">
                        <i class="fas fa-check-circle mr-2"></i>
                        <?= htmlspecialchars($msg) ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($err): ?>
                    <div class="mb-6 p-4 rounded-lg bg-red-900 bg-opacity-50 border border-red-700 text-red-200">
                        <i class="fas fa-exclamation-circle mr-2"></i>
                        <?= htmlspecialchars($err) ?>
                    </div>
                <?php endif; ?>
                
                <?php if (!$success && !empty($token)): ?>
                    <form method="post" class="space-y-6">
                        <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
                        <div>
                            <label for="password" class="block text-sm font-medium text-gray-300 mb-2">Nuova Password</label>
                            <div class="relative">
                                <i class="fas fa-lock absolute left-3 top-3 text-gray-400"></i>
                                <input type="password" id="password" name="password" required minlength="6"
                                       class="w-full pl-10 pr-4 py-3 bg-gray-700 border border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent text-white"
                                       placeholder="Almeno 6 caratteri">
                            </div>
                        </div>
                        
                        <div>
                            <label for="confirm_password" class="block text-sm font-medium text-gray-300 mb-2">Conferma Nuova Password</label>
                            <div class="relative">
                                <i class="fas fa-lock absolute left-3 top-3 text-gray-400"></i>
                                <input type="password" id="confirm_password" name="confirm_password" required minlength="6"
                                       class="w-full pl-10 pr-4 py-3 bg-gray-700 border border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent text-white"
                                       placeholder="Ripeti la nuova password">
                            </div>
                        </div>
                        
                        <button type="submit" class="w-full animated-btn rounded-lg">
                            <span><i class="fas fa-save mr-2"></i> Imposta nuova password</span>
                        </button>
                    </form>
                <?php elseif ($success): ?>
                    <div class="text-center">
                        <i class="fas fa-check-circle text-4xl text-green-400 mb-4"></i>
                        <h3 class="text-xl font-semibold text-white mb-4">Password aggiornata!</h3>
                        <p class="text-gray-300 mb-6">La tua password è stata cambiata con successo.</p>
                        
                        <a href="login.php" class="inline-block animated-btn rounded-lg">
                            <span><i class="fas fa-sign-in-alt mr-2"></i> Accedi ora</span>
                        </a>
                    </div>
                <?php else: ?>
                    <div class="text-center">
                        <i class="fas fa-exclamation-triangle text-4xl text-red-400 mb-4"></i>
                        <h3 class="text-xl font-semibold text-white mb-4">Link non valido</h3>
                        <p class="text-gray-300 mb-6">Il link di reset è scaduto o non valido.</p>
                        
                        <a href="forgot-password.php" class="inline-block animated-btn rounded-lg">
                            <span><i class="fas fa-redo mr-2"></i> Richiedi nuovo reset</span>
                        </a>
                    </div>
                <?php endif; ?>
                
                <div class="mt-6 text-center">
                    <p class="text-gray-400">
                        <a href="login.php" class="text-green-500 hover:text-green-400 font-medium">Torna al login</a>
                    </p>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // Password confirmation validation
        document.getElementById('confirm_password')?.addEventListener('input', function() {
            const password = document.getElementById('password').value;
            const confirmPassword = this.value;
            
            if (confirmPassword && password !== confirmPassword) {
                this.setCustomValidity('Le password non corrispondono');
            } else {
                this.setCustomValidity('');
            }
        });
        
        document.getElementById('password')?.addEventListener('input', function() {
            const confirmPassword = document.getElementById('confirm_password');
            if (confirmPassword && confirmPassword.value) {
                confirmPassword.dispatchEvent(new Event('input'));
            }
        });
    </script>
    <script src="https://cdn.jsdelivr.net/npm/gsap@3.12.5/dist/gsap.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/gsap@3.12.5/dist/ScrollTrigger.min.js"></script>
    <script src="js/ux.js?v=<?php echo time(); ?>"></script>
</body>
</html>
