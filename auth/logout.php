<?php
session_start();
require dirname(__DIR__) . '/includes/config.php';

clearRememberMe();

session_unset();
session_destroy();

header("Location: login.php");
exit;
?>
