<?php
session_start();
require_once __DIR__ . '/../includes/app.php';

// Clear both old and new session data
unset($_SESSION['admin_auth']);
user_logout();

header('Location: ../login.php?logout=1');
exit;

