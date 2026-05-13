<?php
session_start();
require dirname(__DIR__) . '/includes/config.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Admin') { 
    exit("Unauthorized access"); 
}

if (isset($_GET['id'])) {
    $u_id = $_GET['id'];

    $getUser = $pdo->prepare("SELECT firstname, lastname FROM users WHERE id = ?");
    $getUser->execute([$u_id]);
    $targetUser = $getUser->fetch();

    if ($targetUser) {
        $stmt = $pdo->prepare("UPDATE users SET status = 'archived' WHERE id = ? AND role_id = 3");
        $stmt->execute([$u_id]);

        $adminName = $_SESSION['firstname'] ?? $_SESSION['name'] ?? 'Admin';
        $details = "Admin $adminName archived user: {$targetUser['firstname']} {$targetUser['lastname']}";
        logActivity($pdo, $_SESSION['user_id'], $details, "User Management");
    }
}

header("Location: dashboard.php");
exit;
?>
