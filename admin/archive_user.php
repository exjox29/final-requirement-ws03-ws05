<?php
session_start();
require dirname(__DIR__) . '/includes/config.php';

// Security Check
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Admin') { 
    exit("Unauthorized access"); 
}

if (isset($_GET['id'])) {
    $u_id = $_GET['id'];

    // 1. Kunin muna ang pangalan ng user para sa log
    $getUser = $pdo->prepare("SELECT firstname, lastname FROM users WHERE id = ?");
    $getUser->execute([$u_id]);
    $targetUser = $getUser->fetch();

    if ($targetUser) {
        // 2. I-update ang status sa 'archived'
        $stmt = $pdo->prepare("UPDATE users SET status = 'archived' WHERE id = ? AND role_id = 3");
        $stmt->execute([$u_id]);

        // 3. I-log ang activity
        $adminName = $_SESSION['firstname'] ?? $_SESSION['name'] ?? 'Admin';
        $details = "Admin $adminName archived user: {$targetUser['firstname']} {$targetUser['lastname']}";
        logActivity($pdo, $_SESSION['user_id'], $details, "User Management");
    }
}

header("Location: dashboard.php");
exit;
?>