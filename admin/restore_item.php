<?php
session_start();
require dirname(__DIR__) . '/includes/config.php';

if (isset($_SESSION['role']) && $_SESSION['role'] === 'Admin' && isset($_GET['id'])) {
    $p_id = $_GET['id'];

    // 1. Kunin ang detalye ng item at ang owner para sa log
    $stmtItem = $pdo->prepare("SELECT i.item_name, u.firstname, u.lastname 
                               FROM items i 
                               JOIN users u ON i.added_by = u.id 
                               WHERE i.public_id = ?");
    $stmtItem->execute([$p_id]);
    $item = $stmtItem->fetch();

    if ($item) {
        // 2. I-restore ang item (ibabalik sa 'approved' status)
        $stmt = $pdo->prepare("UPDATE items SET status = 'approved' WHERE public_id = ?");
        $stmt->execute([$p_id]);

        // 3. I-log ang activity
        $adminName = $_SESSION['firstname'] ?? $_SESSION['name'] ?? 'Admin';
        $details = "Admin $adminName restored item '{$item['item_name']}' to inventory (Owner: {$item['firstname']} {$item['lastname']})";
        logActivity($pdo, $_SESSION['user_id'], $details, "Inventory Management");
    }
}
header("Location: dashboard.php");
exit;
?>