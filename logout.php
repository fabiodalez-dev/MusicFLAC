<?php
require_once 'includes/bootstrap.php';

user_logout();
header('Location: login.php?logout=1');
exit;