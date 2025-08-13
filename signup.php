<?php
@ini_set('display_errors', '0');
error_reporting(E_ALL);
require_once __DIR__ . '/includes/bootstrap.php';

// Disable signup when in production mode
if ((int)app_get_setting('production_mode', 0) === 1) {
    header('Location: login.php');
    exit;
}

$msg = '';
$err = '';
$csrf = app_csrf_token();

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!app_verify_csrf($_POST['csrf'] ?? '')) {
        $err = 'Token CSRF non valido';
    } else {
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        
        if (empty($username) || empty($email) || empty($password)) {
            $err = 'All fields are required';
        } elseif ($password !== $confirm_password) {
            $err = 'Passwords do not match';
        } else {
            $result = user_register($username, $email, $password);
            if ($result['success']) {
                $msg = 'Registration successful! Please wait for admin approval before you can login.';
                // Clear form
                $username = $email = '';
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
    <title>Registrazione - MusicFLAC</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="css/style.css">
</head>
<body class="min-h-screen bg-gradient-to-br from-gray-900 via-gray-800 to-gray-900">
    <div class="min-h-screen flex items-center justify-center px-4">
        <div class="max-w-md w-full">
            <div class="text-center mb-8">
                <h1 class="text-4xl font-bold text-white mb-2">MusicFLAC</h1>
                <p class="text-gray-400">Crea il tuo account</p>
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
                
                <form method="post" class="space-y-6">
                    <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
                    <div>
                        <label for="username" class="block text-sm font-medium text-gray-300 mb-2">Username</label>
                        <div class="relative">
                            <i class="fas fa-user absolute left-3 top-3 text-gray-400"></i>
                            <input type="text" id="username" name="username" value="<?= htmlspecialchars($username ?? '') ?>" 
                                   required minlength="3" maxlength="50"
                                   class="w-full pl-10 pr-4 py-3 bg-gray-700 border border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent text-white"
                                   placeholder="Scegli un username">
                        </div>
                    </div>
                    
                    <div>
                        <label for="email" class="block text-sm font-medium text-gray-300 mb-2">Email</label>
                        <div class="relative">
                            <i class="fas fa-envelope absolute left-3 top-3 text-gray-400"></i>
                            <input type="email" id="email" name="email" value="<?= htmlspecialchars($email ?? '') ?>" 
                                   required maxlength="100"
                                   class="w-full pl-10 pr-4 py-3 bg-gray-700 border border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent text-white"
                                   placeholder="La tua email">
                        </div>
                    </div>
                    
                    <div>
                        <label for="password" class="block text-sm font-medium text-gray-300 mb-2">Password</label>
                        <div class="relative">
                            <i class="fas fa-lock absolute left-3 top-3 text-gray-400"></i>
                            <input type="password" id="password" name="password" required minlength="6"
                                   class="w-full pl-10 pr-4 py-3 bg-gray-700 border border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent text-white"
                                   placeholder="Almeno 6 caratteri">
                        </div>
                    </div>
                    
                    <div>
                        <label for="confirm_password" class="block text-sm font-medium text-gray-300 mb-2">Conferma Password</label>
                        <div class="relative">
                            <i class="fas fa-lock absolute left-3 top-3 text-gray-400"></i>
                            <input type="password" id="confirm_password" name="confirm_password" required minlength="6"
                                   class="w-full pl-10 pr-4 py-3 bg-gray-700 border border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent text-white"
                                   placeholder="Ripeti la password">
                        </div>
                    </div>
                    
                    <div class="bg-yellow-900 bg-opacity-30 border border-yellow-700 rounded-lg p-4">
                        <div class="flex">
                            <i class="fas fa-info-circle text-yellow-400 mt-0.5 mr-2"></i>
                            <div class="text-sm text-yellow-200">
                                <p class="font-semibold">Nota importante:</p>
                                <p>Il tuo account deve essere attivato da un amministratore prima di poter accedere al servizio.</p>
                            </div>
                        </div>
                    </div>
                    
                    <button type="submit" class="w-full animated-btn rounded-lg">
                        <span><i class="fas fa-user-plus mr-2"></i> Registrati</span>
                    </button>
                </form>
                
                <div class="mt-6 text-center">
                    <p class="text-gray-400">
                        Hai già un account? 
                        <a href="login.php" class="text-green-500 hover:text-green-400 font-medium">Accedi qui</a>
                    </p>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // Password confirmation validation
        document.getElementById('confirm_password').addEventListener('input', function() {
            const password = document.getElementById('password').value;
            const confirmPassword = this.value;
            
            if (confirmPassword && password !== confirmPassword) {
                this.setCustomValidity('Le password non corrispondono');
            } else {
                this.setCustomValidity('');
            }
        });
        
        document.getElementById('password').addEventListener('input', function() {
            const confirmPassword = document.getElementById('confirm_password');
            if (confirmPassword.value) {
                confirmPassword.dispatchEvent(new Event('input'));
            }
        });
    </script>
    <script src="https://cdn.jsdelivr.net/npm/gsap@3.12.5/dist/gsap.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/gsap@3.12.5/dist/ScrollTrigger.min.js"></script>
    <script src="js/ux.js?v=<?php echo time(); ?>"></script>
</body>
</html>
