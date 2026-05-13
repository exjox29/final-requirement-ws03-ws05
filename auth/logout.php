<?php
session_start();
require dirname(__DIR__) . '/includes/config.php';

// Clear remember me cookie
clearRememberMe();

// Destroy session
session_unset();
session_destroy();

header("Location: login.php");
exit;
?>