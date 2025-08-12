<?php
session_start();
require_once 'includes/app.php';

user_logout();
header('Location: login.php?logout=1');
exit;