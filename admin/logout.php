<?php
require_once __DIR__ . '/../includes/bootstrap.php';

// Clear both old and new session data
unset($_SESSION['admin_auth']);
user_logout();

header('Location: ../login.php?logout=1');
exit;

