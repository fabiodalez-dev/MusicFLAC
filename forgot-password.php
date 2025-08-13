<?php
@ini_set('display_errors', '0');
error_reporting(E_ALL);
require_once __DIR__ . '/includes/bootstrap.php';

$msg = '';
$err = '';
$step = 'request'; // request, token_sent
$csrf = app_csrf_token();

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (isset($_POST['email'])) {
        if (!app_verify_csrf($_POST['csrf'] ?? '')) {
            $err = 'Token CSRF non valido';
        } else {
            // simple session-based rate limiting
            $now = time();
            $last = $_SESSION['last_reset_request'] ?? 0;
            if ($now - $last < 60) { // 1 per minute
                $err = 'Troppi tentativi. Riprova tra un minuto.';
            } else {
                // Step 1: Request reset
                $email = trim($_POST['email'] ?? '');
                
                if (empty($email)) {
                    $err = 'Email is required';
                } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $err = 'Invalid email format';
                } else {
                    $result = user_generate_reset_token($email);
                    if ($result['success']) {
                        // Send email with reset link
                        $reset_link = base_url('reset-password.php?token=' . $result['token']);
                        $subject = 'Reset Password - MusicFLAC';
                        $message = "Ciao,\n\nHai richiesto il reset della password per il tuo account MusicFLAC.\n\nClicca sul seguente link per reimpostare la password:\n" . $reset_link . "\n\nQuesto link è valido per 2 ore.\n\nSe non hai richiesto questo reset, ignora questa email.\n\nGrazie,\nTeam MusicFLAC";
                        $headers = "From: noreply@musicflac.com\r\n";
                        $headers .= "Reply-To: noreply@musicflac.com\r\n";
                        $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
                        
                        if (mail($email, $subject, $message, $headers)) {
                            $msg = 'Se l\'email esiste nel nostro sistema, riceverai un link per il reset della password.';
                        } else {
                            $msg = 'Se l\'email esiste nel nostro sistema, riceverai un link per il reset della password.';
                        }
                        $step = 'token_sent';
                        $_SESSION['last_reset_request'] = $now;
                    } else {
                        // Don't reveal if email exists or not for security
                        $msg = 'Se l\'email esiste nel nostro sistema, riceverai un link per il reset della password.';
                        $step = 'token_sent';
                        $_SESSION['last_reset_request'] = $now;
                    }
                }
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
    <title>Recupera Password - MusicFLAC</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="css/style.css">
</head>
<body class="min-h-screen bg-gradient-to-br from-gray-900 via-gray-800 to-gray-900">
    <div class="min-h-screen flex items-center justify-center px-4">
        <div class="max-w-md w-full">
            <div class="text-center mb-8">
                <h1 class="text-4xl font-bold text-white mb-2">MusicFLAC</h1>
                <p class="text-gray-400">Recupera la tua password</p>
            </div>
            
            <div class="bg-gray-800 rounded-2xl shadow-2xl p-8 border border-gray-700">
                <?php if ($msg): ?>
                    <div class="mb-6 p-4 rounded-lg bg-green-900 bg-opacity-50 border border-green-700 text-green-200">
                        <i class="fas fa-check-circle mr-2"></i>
                        <div><?= $msg ?></div>
                    </div>
                <?php endif; ?>
                
                <?php if ($err): ?>
                    <div class="mb-6 p-4 rounded-lg bg-red-900 bg-opacity-50 border border-red-700 text-red-200">
                        <i class="fas fa-exclamation-circle mr-2"></i>
                        <?= htmlspecialchars($err) ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($step === 'request'): ?>
                    <div class="mb-6">
                        <div class="flex items-center text-blue-200 mb-4">
                            <i class="fas fa-info-circle mr-2"></i>
                            <span class="text-sm">Inserisci la tua email per ricevere un link di reset della password</span>
                        </div>
                    </div>
                    
                    <form method="post" class="space-y-6">
                        <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
                        <div>
                            <label for="email" class="block text-sm font-medium text-gray-300 mb-2">Email</label>
                            <div class="relative">
                                <i class="fas fa-envelope absolute left-3 top-3 text-gray-400"></i>
                                <input type="email" id="email" name="email" required
                                       class="w-full pl-10 pr-4 py-3 bg-gray-700 border border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent text-white"
                                       placeholder="La tua email registrata">
                            </div>
                        </div>
                        
                        <button type="submit" class="w-full animated-btn rounded-lg">
                            <span><i class="fas fa-paper-plane mr-2"></i> Invia link di reset</span>
                        </button>
                    </form>
                <?php elseif ($step === 'token_sent'): ?>
                    <div class="text-center">
                        <i class="fas fa-envelope-open-text text-4xl text-green-400 mb-4"></i>
                        <h3 class="text-xl font-semibold text-white mb-4">Reset richiesto!</h3>
                        <p class="text-gray-300 mb-6">Controlla la tua email per il link di reset della password.</p>
                        
                        
                        <a href="forgot-password.php" class="inline-block text-blue-400 hover:text-blue-300">
                            <i class="fas fa-redo mr-1"></i> Richiedi un altro reset
                        </a>
                    </div>
                <?php endif; ?>
                
                <div class="mt-6 text-center">
                    <p class="text-gray-400">
                        Ricordi la password? 
                        <a href="login.php" class="text-green-500 hover:text-green-400 font-medium">Accedi qui</a>
                    </p>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/gsap@3.12.5/dist/gsap.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/gsap@3.12.5/dist/ScrollTrigger.min.js"></script>
    <script src="js/ux.js?v=<?php echo time(); ?>"></script>
</body>
</html>
