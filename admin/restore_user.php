<?php
session_start();
require dirname(__DIR__) . '/includes/config.php';

// Security Check
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Admin') { 
    exit("Unauthorized access"); 
}

if (isset($_GET['id'])) {
    $u_id = $_GET['id'];

    // 1. Kunin ang pangalan para sa log
    $getUser = $pdo->prepare("SELECT firstname, lastname FROM users WHERE id = ?");
    $getUser->execute([$u_id]);
    $targetUser = $getUser->fetch();

    if ($targetUser) {
        // 2. I-restore ang status sa 'active'
        $stmt = $pdo->prepare("UPDATE users SET status = 'active' WHERE id = ? AND role_id = 3");
        $stmt->execute([$u_id]);

        // 3. I-log ang activity
        $adminName = $_SESSION['firstname'] ?? $_SESSION['name'] ?? 'Admin';
        $details = "Admin $adminName restored user: {$targetUser['firstname']} {$targetUser['lastname']}";
        logActivity($pdo, $_SESSION['user_id'], $details, "User Management");
    }
}

header("Location: dashboard.php");
exit;
?>