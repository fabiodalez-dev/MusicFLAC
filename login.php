<?php
@ini_set('display_errors', '0');
error_reporting(E_ALL);
require_once __DIR__ . '/includes/bootstrap.php';

// Redirect if already logged in
if (user_is_logged_in()) {
    header('Location: index.php');
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
        $password = $_POST['password'] ?? '';
        
        if (empty($username) || empty($password)) {
            $err = 'Username and password are required';
        } else {
            $result = user_login($username, $password);
            if ($result['success']) {
                // Redirect to original page or index (sanitized)
                $redirect = $_GET['redirect'] ?? 'index.php';
                $redirect = is_string($redirect) ? $redirect : 'index.php';
                // Disallow schemes/hosts and header injection; allow only same-site relative paths
                if (preg_match('/^https?:\/\//i', $redirect) || strpos($redirect, '//') === 0 || strpos($redirect, "\n") !== false || strpos($redirect, "\r") !== false) {
                    $redirect = 'index.php';
                }
                if ($redirect === '' || $redirect[0] === '#') { $redirect = 'index.php'; }
                header('Location: ' . $redirect);
                exit;
            } else {
                $err = $result['error'];
            }
        }
    }
}

// Handle logout message
if (isset($_GET['logout'])) {
    $msg = 'You have been logged out successfully.';
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - MusicFLAC</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="css/style.css">
</head>
<body class="min-h-screen bg-gradient-to-br from-gray-900 via-gray-800 to-gray-900">
    <div class="min-h-screen flex items-center justify-center px-4">
        <div class="max-w-md w-full">
            <div class="text-center mb-8">
                <h1 class="text-4xl font-bold text-white mb-2">MusicFLAC</h1>
                <p class="text-gray-400">Accedi al tuo account</p>
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
                        <label for="username" class="block text-sm font-medium text-gray-300 mb-2">Username o Email</label>
                        <div class="relative">
                            <i class="fas fa-user absolute left-3 top-3 text-gray-400"></i>
                            <input type="text" id="username" name="username" value="<?= htmlspecialchars($username ?? '') ?>" 
                                   required
                                   class="w-full pl-10 pr-4 py-3 bg-gray-700 border border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent text-white"
                                   placeholder="Inserisci username o email">
                        </div>
                    </div>
                    
                    <div>
                        <label for="password" class="block text-sm font-medium text-gray-300 mb-2">Password</label>
                        <div class="relative">
                            <i class="fas fa-lock absolute left-3 top-3 text-gray-400"></i>
                            <input type="password" id="password" name="password" required
                                   class="w-full pl-10 pr-4 py-3 bg-gray-700 border border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent text-white"
                                   placeholder="La tua password">
                        </div>
                    </div>
                    
                    <button type="submit" class="w-full animated-btn rounded-lg">
                        <span><i class="fas fa-sign-in-alt mr-2"></i> Accedi</span>
                    </button>
                </form>
                
                <div class="mt-6 space-y-4 text-center">
                    <p class="text-gray-400">
                        <a href="forgot-password.php" class="text-blue-400 hover:text-blue-300 font-medium">
                            <i class="fas fa-key mr-1"></i> Password dimenticata?
                        </a>
                    </p>
                    
                    <hr class="border-gray-600">
                    
                    <?php if ((int)app_get_setting('production_mode', 0) !== 1): ?>
                      <p class="text-gray-400">
                          Non hai ancora un account? 
                          <a href="signup.php" class="text-green-500 hover:text-green-400 font-medium">Registrati qui</a>
                      </p>
                    <?php endif; ?>
                </div>
            </div>
            
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/gsap@3.12.5/dist/gsap.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/gsap@3.12.5/dist/ScrollTrigger.min.js"></script>
    <script src="js/ux.js?v=<?php echo time(); ?>"></script>
</body>
</html>
