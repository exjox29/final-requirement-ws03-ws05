<?php
session_start();
require dirname(__DIR__) . '/includes/config.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Admin') {
    header("Location: ../auth/login.php");
    exit;
}

$p_id = $_GET['id'] ?? '';
$message = "";

$stmt = $pdo->prepare("
    SELECT i.*, u.firstname, u.lastname 
    FROM items i 
    LEFT JOIN users u ON i.added_by = u.id 
    WHERE i.public_id = ?
");
$stmt->execute([$p_id]);
$item = $stmt->fetch();

if (!$item) { 
    die("Item not found!"); 
}

if (isset($_POST['update'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        die("CSRF token validation failed!");
    }

    $name  = $_POST['item_name'];
    $brand = $_POST['brand'];
    $cat   = $_POST['category'];
    $cond  = $_POST['item_condition'];
    $price = $_POST['price'];
    $stock = $_POST['stock'];
    $desc  = $_POST['description']; 
    $warn  = $_POST['warranty'];

    $image_name = $item['item_image']; 

    if (isset($_FILES['item_image']) && $_FILES['item_image']['error'] === 0) {
        $ext = strtolower(pathinfo($_FILES['item_image']['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'webp'];
        
        if (in_array($ext, $allowed)) {
            $image_name = "IMG_" . bin2hex(random_bytes(8)) . "." . $ext;
            if (!is_dir('../uploads')) { mkdir('../uploads', 0777, true); }
            move_uploaded_file($_FILES['item_image']['tmp_name'], "../uploads/" . $image_name);
        }
    }

 
    $stmt = $pdo->prepare("UPDATE items SET item_name = ?, brand = ?, category = ?, item_condition = ?, price = ?, stock_quantity = ?, description = ?, warranty = ?, item_image = ? WHERE public_id = ?");
    $stmt->execute([$name, $brand, $cat, $cond, $price, $stock, $desc, $warn, $image_name, $p_id]);
    
    $adminName = $_SESSION['firstname'] ?? $_SESSION['name'] ?? 'Admin';
    $ownerName = $item['firstname'] . " " . $item['lastname'];
    $logDetails = "Admin $adminName updated item '$name' (Owner: $ownerName)";
    logActivity($pdo, $_SESSION['user_id'], $logDetails, "Inventory Management");
    
    header("Location: dashboard.php?msg=Updated");
    exit;
}
?>
