<?php
session_start();
require dirname(__DIR__) . '/includes/config.php';

checkRoleAccess('Regular');

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$msg = "";

if (isset($_POST['submit_item'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("Invalid CSRF token.");
    }

    $name   = trim($_POST['item_name'] ?? '');
    $brand  = trim($_POST['brand'] ?? '');
    $cat    = trim($_POST['category'] ?? '');
    $cond   = trim($_POST['item_condition'] ?? '');
    $desc   = trim($_POST['description'] ?? '');
    $warn   = trim($_POST['warranty'] ?? '');
    $price  = filter_var($_POST['price'] ?? 0, FILTER_VALIDATE_FLOAT);
    $stock  = filter_var($_POST['stock_quantity'] ?? 0, FILTER_VALIDATE_INT);

    if (!$name || !$brand || !$cat || !$cond) {
        $msg = "Please fill in all required fields.";
    } elseif ($price === false || $price < 0) {
        $msg = "Invalid price value.";
    } elseif ($stock === false || $stock < 0) {
        $msg = "Invalid stock quantity.";
    } else {
        $public_id = bin2hex(random_bytes(16));
        $image_name = "default.png";
        if (isset($_FILES['item_image']) && $_FILES['item_image']['error'] === 0) {
            $file_tmp = $_FILES['item_image']['tmp_name'];
            $file_size = $_FILES['item_image']['size'];
            $file_name = $_FILES['item_image']['name'];
            $ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
            $allowed_ext = ['jpg','jpeg','png','webp'];

            if (in_array($ext, $allowed_ext) && $file_size <= 2 * 1024 * 1024) {
                if (!is_dir('../uploads')) mkdir('../uploads', 0777, true);
                $image_name = "IMG_" . bin2hex(random_bytes(8)) . "." . $ext;
                move_uploaded_file($file_tmp, "../uploads/" . $image_name);
            }
        }

        if (!$msg) {
            $stmt = $pdo->prepare("INSERT INTO items (public_id, item_name, brand, category, item_condition, price, stock_quantity, description, warranty, item_image, status, added_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?)");
            $stmt->execute([$public_id, $name, $brand, $cat, $cond, $price, $stock, $desc, $warn, $image_name, $_SESSION['user_id']]);
            $msg = "Item suggested successfully! Waiting for Admin approval.";
        }
    }
}

$view = $_GET['view'] ?? 'home';

$stmt_notify = $pdo->prepare("SELECT item_name, status FROM items WHERE added_by = ? AND status IN ('approved', 'rejected') ORDER BY id DESC LIMIT 5");
$stmt_notify->execute([$_SESSION['user_id']]);
$notifications = $stmt_notify->fetchAll();
$notif_count = count($notifications);

$search = trim($_GET['search'] ?? '');
$cat_filter = trim($_GET['cat_filter'] ?? '');
$query = "SELECT * FROM items WHERE status = 'approved'";
$params = [];
if (!empty($search)) {
    $query .= " AND (item_name LIKE ? OR brand LIKE ?)";
    $params[] = "%$search%"; $params[] = "%$search%";
}
if (!empty($cat_filter)) {
    $query .= " AND LOWER(TRIM(category)) = LOWER(?)";
    $params[] = $cat_filter;
}
$query .= " ORDER BY id DESC";
$stmt = $pdo->prepare($query); $stmt->execute($params);
$items = $stmt->fetchAll();

$stmt_my_items = $pdo->prepare("SELECT * FROM items WHERE added_by = ? ORDER BY id DESC");
$stmt_my_items->execute([$_SESSION['user_id']]);
$my_submissions = $stmt_my_items->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <title>User Dashboard | TechStore</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #2563eb;
            --primary-hover: #1d4ed8;
            --sidebar-bg: #0f172a;
            --bg: #f8fafc;
            --white: #ffffff;
            --text: #1e293b;
            --danger: #ef4444;
            --success: #10b981;
            --warning: #f59e0b;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Plus Jakarta Sans', sans-serif; }
        body { background: var(--bg); display: flex; color: var(--text); min-height: 100vh; }
        
        .sidebar { 
            width: 260px; 
            background: var(--sidebar-bg); 
            position: fixed; 
            height: 100vh; 
            color: white; 
            padding: 20px; 
            z-index: 1000;
        }
        
        .sidebar-brand { 
            margin-bottom: 35px; 
            font-weight: 800; 
            font-size: 1.4rem; 
            display: flex; 
            align-items: center; 
            gap: 10px;
            color: white;
        }
        
        .sidebar-brand i {
            color: var(--primary);
        }
        
        .nav-link { 
            display: flex; 
            align-items: center; 
            gap: 12px;
            padding: 12px 16px; 
            color: #94a3b8; 
            text-decoration: none; 
            border-radius: 8px; 
            margin-bottom: 5px; 
            transition: 0.3s; 
            background: transparent; 
            border: none; 
            width: 100%; 
            cursor: pointer;
            font-size: 0.9rem;
        }
        
        .nav-link:hover, .nav-link.active { 
            background: #1e293b; 
            color: white; 
        }
        
        .nav-link.active { 
            background: var(--primary); 
        }
        
        .nav-link i {
            width: 20px;
            font-size: 1rem;
        }

        .main-wrapper { 
            flex: 1; 
            margin-left: 260px; 
            padding: 100px 40px 40px 40px;
            max-width: calc(100% - 260px);
        }
        
        .section-header { 
            margin-bottom: 30px; 
            margin-top: 0;
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
        }
        
        .section-header h3 {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--text);
            margin: 0;
        }
        
        /* Top Nav - Clean header without white background */
        .top-nav {
            position: fixed;
            top: 0;
            left: 260px;
            right: 0;
            height: 70px;
            background: transparent;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 30px;
            z-index: 999;
        }
        
        /* Page title in header */
        .page-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--text);
            margin: 0;
        }
        
        /* User badge in header */
        .user-badge {
            display: flex;
            align-items: center;
            gap: 12px;
            background: white;
            padding: 8px 20px 8px 12px;
            border-radius: 40px;
            box-shadow: 0 1px 2px 0 rgba(0,0,0,0.05);
            border: 1px solid #e2e8f0;
        }
        
        .user-badge i {
            font-size: 1.2rem;
            color: var(--primary);
        }
        
        .user-badge span {
            font-weight: 600;
            font-size: 0.9rem;
            color: var(--text);
        }
        
        .hamburger-btn {
            display: none;
        }
        
        .nav-left-group {
            display: flex;
            align-items: center;
            gap: 20px;
        }
        
        /* Hide logo in header since it's in sidebar */
        .logo {
            display: none;
        }
        
        .search-container { 
            flex: 1; 
            max-width: 500px; 
            margin: 0 30px; 
            display: flex; 
            background: white; 
            border-radius: 40px; 
            border: 1px solid #e2e8f0;
            overflow: hidden;
            box-shadow: 0 1px 2px 0 rgba(0,0,0,0.05);
        }
        
        .search-container select {
            border: none;
            background: #f8fafc;
            padding: 0 20px;
            font-size: 0.85rem;
            font-weight: 500;
            color: var(--text);
            outline: none;
            cursor: pointer;
            border-right: 1px solid #e2e8f0;
            width: 140px;
            min-width: 120px;
        }
        
        .search-container input { 
            flex: 1; 
            border: none; 
            background: transparent; 
            padding: 12px 20px; 
            outline: none; 
            font-size: 0.9rem;
            min-width: 200px;
        }
        
        .search-container input::placeholder {
            color: #94a3b8;
        }
        
        .nav-right { 
            display: flex; 
            align-items: center; 
            gap: 20px; 
        }
        
        .profile-dropdown { 
            position: relative; 
        }
        
        .icon-btn { 
            cursor: pointer; 
            font-size: 1.2rem; 
            color: var(--text); 
            background: #f1f5f9; 
            width: 40px; 
            height: 40px; 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            border-radius: 50%; 
            transition: 0.3s; 
            position: relative; 
        }
        
        .icon-btn:hover { 
            background: #e2e8f0; 
            color: var(--primary); 
        }
        
        .badge { 
            position: absolute; 
            top: -5px; 
            right: -5px; 
            background: var(--danger); 
            color: white; 
            font-size: 10px; 
            font-weight: bold; 
            width: 18px; 
            height: 18px; 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            border-radius: 50%; 
        }
        
        .notif-panel {
            display: none;
            position: absolute;
            right: 0;
            top: 50px;
            width: 320px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 10px 25px -5px rgba(0,0,0,0.1);
            border: 1px solid #e2e8f0;
            overflow: hidden;
            z-index: 1001;
        }
        
        .profile-dropdown:hover .notif-panel { 
            display: block; 
        }
        
        .notif-header { 
            padding: 12px 16px; 
            font-weight: 700; 
            font-size: 0.85rem; 
            border-bottom: 1px solid #f1f5f9; 
            background: #f8fafc; 
        }
        
        .notif-item { 
            padding: 12px 16px; 
            border-bottom: 1px solid #f1f5f9; 
            transition: 0.2s; 
        }
        
        .notif-item:hover { 
            background: #f8fafc; 
        }
        
        .grid { 
            display: grid; 
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); 
            gap: 25px; 
        }
        
        .card { 
            background: white; 
            border-radius: 16px; 
            overflow: hidden; 
            border: 1px solid #eef2f6; 
            transition: 0.3s; 
        }
        
        .card:hover { 
            transform: translateY(-5px); 
            box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1); 
        }
        
        .card-img-wrapper { 
            height: 200px; 
            background: #f8fafc; 
            display: flex; 
            align-items: center; 
            justify-content: center; 
        }
        
        .product-img { 
            width: 100%; 
            height: 100%; 
            object-fit: cover; 
        }
        
        .card-content { 
            padding: 20px; 
        }
        
        .form-section { 
            background: white; 
            padding: 30px; 
            border-radius: 16px; 
            box-shadow: 0 1px 3px 0 rgba(0,0,0,0.1); 
            border: 1px solid #e2e8f0; 
        }
        
        .form-grid { 
            display: grid; 
            grid-template-columns: 1fr 1fr; 
            gap: 20px; 
        }
        
        .form-group { 
            margin-bottom: 20px; 
        }
        
        .form-group label { 
            display: block; 
            margin-bottom: 8px; 
            font-weight: 600; 
            font-size: 0.85rem; 
        }
        
        input, select, textarea { 
            width: 100%; 
            padding: 10px 14px; 
            border: 1.5px solid #e2e8f0; 
            border-radius: 10px; 
            font-size: 0.9rem; 
            font-family: inherit; 
            transition: 0.3s; 
        }
        
        input:focus, select:focus, textarea:focus { 
            border-color: var(--primary); 
            outline: none; 
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1); 
        }
        
        .btn-submit { 
            background: var(--primary); 
            color: white; 
            border: none; 
            padding: 12px 24px; 
            border-radius: 10px; 
            font-weight: 600; 
            cursor: pointer; 
            transition: 0.3s; 
        }
        
        .btn-submit:hover { 
            background: var(--primary-hover); 
            transform: translateY(-2px); 
        }
        
        .items-table { 
            width: 100%; 
            border-collapse: collapse; 
            background: white;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 1px 3px 0 rgba(0,0,0,0.1);
        }
        
        .items-table th { 
            text-align: left; 
            padding: 16px 20px; 
            background: #f1f5f9; 
            font-size: 0.75rem; 
            text-transform: uppercase; 
            color: #475569; 
            font-weight: 700;
            letter-spacing: 0.5px;
            border-bottom: 2px solid #e2e8f0;
        }
        
        .items-table td { 
            padding: 18px 20px; 
            border-bottom: 1px solid #e2e8f0; 
            font-size: 0.9rem;
            vertical-align: middle;
            background: white;
        }
        
        .items-table tr:hover td {
            background: #f8fafc;
        }
        
        .items-table tr:last-child td {
            border-bottom: none;
        }
        
        .status-badge { 
            padding: 6px 14px; 
            border-radius: 30px; 
            font-size: 0.7rem; 
            font-weight: 700; 
            text-transform: uppercase; 
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        
        .status-approved { 
            background: #ecfdf5; 
            color: #047857; 
            border: 1px solid #a7f3d0;
        }
        
        .status-rejected { 
            background: #fef2f2; 
            color: #b91c1c; 
            border: 1px solid #fecaca;
        }
        
        .status-pending { 
            background: #fffbeb; 
            color: #b45309; 
            border: 1px solid #fde68a;
        }
        
        .modal { 
            display: none; 
            position: fixed; 
            inset: 0; 
            background: rgba(15, 23, 42, 0.7); 
            backdrop-filter: blur(4px); 
            justify-content: center; 
            align-items: center; 
            z-index: 2000; 
        }
        
        .modal-content { 
            background: white; 
            padding: 0; 
            border-radius: 16px; 
            width: 450px; 
            max-width: 90%;
            overflow: hidden;
        }
        
        .modal-header { 
            padding: 20px 25px; 
            background: #f8fafc; 
            border-bottom: 1px solid #e2e8f0; 
        }
        
        .modal-header h3 {
            margin: 0;
            font-weight: 700;
        }
        
        .modal-body { 
            padding: 25px; 
            line-height: 1.6; 
            color: #475569; 
        }
        
        .modal-footer { 
            padding: 15px 25px; 
            text-align: right; 
            border-top: 1px solid #e2e8f0; 
        }
        
        .alert-success {
            background: #ecfdf5;
            color: #047857;
            padding: 15px 20px;
            border-radius: 12px;
            margin-bottom: 25px;
            font-weight: 600;
            border-left: 4px solid #10b981;
        }
        
        @keyframes fadeIn { 
            from { opacity: 0; } 
            to { opacity: 1; } 
        }
        
        @keyframes slideUp { 
            from { transform: translateY(20px); opacity: 0; } 
            to { transform: translateY(0); opacity: 1; } 
        }
        
        @media (max-width: 768px) {
            .sidebar {
                left: -260px;
                transition: 0.3s;
            }
            .sidebar.active {
                left: 0;
            }
            .main-wrapper {
                margin-left: 0;
                max-width: 100%;
                padding: 90px 20px 20px 20px;
            }
            .top-nav {
                left: 0;
            }
            .hamburger-btn {
                display: block;
                background: none;
                border: none;
                font-size: 1.2rem;
                cursor: pointer;
                color: var(--text);
            }
            .search-container {
                display: none;
            }
            .form-grid {
                grid-template-columns: 1fr;
            }
            .grid {
                grid-template-columns: 1fr;
            }
            .items-table th,
            .items-table td {
                padding: 12px 15px;
            }
        }
    </style>
</head>
<body>

<nav class="sidebar" id="sidebar">
    <div class="sidebar-brand">
        <i class="fas fa-microchip"></i> TECH<span style="color:var(--primary)">STORE</span>
    </div>
    
    <a href="?view=home" class="nav-link <?= ($view == 'home') ? 'active' : '' ?>">
        <i class="fas fa-house"></i> Dashboard
    </a>
    
    <a href="?view=add" class="nav-link <?= ($view == 'add') ? 'active' : '' ?>">
        <i class="fas fa-circle-plus"></i> Suggest Product
    </a>
    
    <a href="?view=my_items" class="nav-link <?= ($view == 'my_items') ? 'active' : '' ?>">
        <i class="fas fa-box-archive"></i> My Submissions
    </a>

    <a href="../auth/logout.php" class="nav-link" style="margin-top: auto; color: #fca5a5;">
        <i class="fas fa-power-off"></i> Logout
    </a>
</nav>

<header class="top-nav">
    <div class="nav-left-group">
        <button class="hamburger-btn" id="hamburgerBtn"><i class="fa-solid fa-bars-staggered"></i></button>
        <div class="page-title">
            <?php 
                if ($view == 'home') echo 'Dashboard';
                elseif ($view == 'add') echo 'Suggest Product';
                elseif ($view == 'my_items') echo 'My Submissions';
                else echo 'Dashboard';
            ?>
        </div>
    </div>
            
    <?php if ($view == 'home'): ?>
    <form method="GET" class="search-container" id="filterForm">
        <select name="cat_filter" id="catSelect">
            <option value="">All Categories</option>
            <option value="Processor" <?= ($cat_filter=='Processor')?'selected':'' ?>>Processor</option>
            <option value="Graphics Card" <?= ($cat_filter=='Graphics Card')?'selected':'' ?>>Graphics Card</option>
            <option value="Motherboard" <?= ($cat_filter=='Motherboard')?'selected':'' ?>>Motherboard</option>
            <option value="RAM" <?= ($cat_filter=='RAM')?'selected':'' ?>>RAM</option>
            <option value="Storage" <?= ($cat_filter=='Storage')?'selected':'' ?>>Storage</option>
            <option value="Monitor" <?= ($cat_filter=='Monitor')?'selected':'' ?>>Monitor</option>
            <option value="Laptop" <?= ($cat_filter=='Laptop')?'selected':'' ?>>Laptop</option>
            <option value="Peripherals" <?= ($cat_filter=='Peripherals')?'selected':'' ?>>Peripherals</option>
            <option value="Cellphone" <?= ($cat_filter=='Cellphone')?'selected':'' ?>>Cellphone</option>
        </select>
        <input type="text" name="search" placeholder="Search products by name or brand..." value="<?= htmlspecialchars($search) ?>" id="searchInput">
    </form>
    <?php else: ?>
    <div style="flex: 1; max-width: 500px; margin: 0 30px;"></div>
    <?php endif; ?>

    <div class="nav-right">
        <div class="profile-dropdown">
            <div class="icon-btn">
                <i class="fa-regular fa-bell"></i>
                <?php if($notif_count > 0): ?><span class="badge"><?= $notif_count ?></span><?php endif; ?>
            </div>
            <div class="notif-panel">
                <div class="notif-header">Notifications</div>
                <?php if($notif_count > 0): ?>
                    <?php foreach($notifications as $n): ?>
                        <div class="notif-item">
                            <p style="margin:0;">Item <strong><?= htmlspecialchars($n['item_name']) ?></strong></p>
                            <span class="status-badge status-<?= $n['status'] ?>" style="font-size:0.6rem;"><?= $n['status'] ?></span>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="notif-item" style="color:#64748b; font-size:0.8rem;">No new alerts.</div>
                <?php endif; ?>
            </div>
        </div>
        <div class="user-badge">
            <i class="fas fa-user-circle"></i>
            <span><?= htmlspecialchars($_SESSION['name']) ?> (Regular)</span>
        </div>
    </div>
</header>

<main class="main-wrapper">
    <?php if($msg): ?>
        <div class="alert-success">
            <i class="fa-solid fa-circle-check"></i> <?= htmlspecialchars($msg) ?>
        </div>
    <?php endif; ?>

    <?php if ($view == 'home'): ?>
        <div class="section-header">
            <h3>Available Hardware</h3>
        </div>
        <div class="grid">
            <?php foreach($items as $i): ?>
                <div class="card">
                    <div class="card-img-wrapper">
                        <img src="../uploads/<?= htmlspecialchars($i['item_image'] ?? 'default.png') ?>" class="product-img">
                    </div>
                    <div class="card-content">
                        <span style="color:var(--primary); font-size:0.7rem; font-weight:800; text-transform:uppercase; background:#eff6ff; padding:3px 8px; border-radius:5px;"><?= htmlspecialchars($i['brand']) ?></span>
                        <h4 style="margin:10px 0 5px 0; font-size:1.1rem;"><?= htmlspecialchars($i['item_name']) ?></h4>
                        <p style="font-weight:800; color:#059669; font-size:1.3rem; margin-bottom:15px;">₱<?= number_format($i['price'], 2) ?></p>
                        <button class="read-more" style="width:100%; padding:12px; border:none; border-radius:10px; background:#f1f5f9; color:var(--primary); font-weight:700; cursor:pointer;" 
                                data-title="<?= htmlspecialchars($i['item_name']) ?>" 
                                data-full="<?= htmlspecialchars($i['description']) ?>">VIEW SPECIFICATIONS</button>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if ($view == 'add'): ?>
        <div class="section-header">
            <h3>Suggest New Gadget</h3>
        </div>
        <section class="form-section">
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <div class="form-grid">
                    <div class="form-group">
                        <label>Product Model Name *</label>
                        <input type="text" name="item_name" placeholder="e.g. RTX 4090 Gaming OC" required>
                    </div>
                    <div class="form-group">
                        <label>Manufacturer / Brand *</label>
                        <input type="text" name="brand" placeholder="e.g. Gigabyte" required>
                    </div>
                </div>
                <div class="form-grid">
                    <div class="form-group">
                        <label>Category *</label>
                        <select name="category" required>
                            <option value="Processor">Processor (CPU)</option>
                            <option value="Graphics Card">Graphics Card (GPU)</option>
                            <option value="Motherboard">Motherboard</option>
                            <option value="RAM">RAM</option>
                            <option value="Storage">Storage (SSD/HDD)</option>
                            <option value="Monitor">Monitor</option>
                            <option value="Laptop">Laptop</option>
                            <option value="Peripherals">Peripherals (Keyboard/Mouse)</option>
                            <option value="Cellphone">Cellphone</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Condition *</label>
                        <select name="item_condition">
                            <option value="Brand New">Brand New</option>
                            <option value="Used">Used</option>
                        </select>
                    </div>
                </div>
                <div class="form-grid">
                    <div class="form-group">
                        <label>Price (₱) *</label>
                        <input type="number" name="price" step="0.01" required>
                    </div>
                    <div class="form-group">
                        <label>Stock Quantity *</label>
                        <input type="number" name="stock_quantity" required>
                    </div>
                </div>
                <div class="form-group">
                    <label>Technical Specifications</label>
                    <textarea name="description" rows="5" placeholder="Paste full specs here..."></textarea>
                </div>
                <div class="form-grid">
                    <div class="form-group">
                        <label>Warranty</label>
                        <input type="text" name="warranty" placeholder="e.g. 2 years">
                    </div>
                    <div class="form-group">
                        <label>Product Image</label>
                        <input type="file" name="item_image" accept="image/*">
                    </div>
                </div>
                <div style="text-align:right;">
                    <button type="submit" name="submit_item" class="btn-submit">Submit Proposal</button>
                </div>
            </form>
        </section>
    <?php endif; ?>

    <?php if ($view == 'my_items'): ?>
        <div class="section-header">
            <h3>My Submissions</h3>
        </div>
        <div style="overflow-x: auto; border-radius: 16px;">
            <table class="items-table">
                <thead>
                    <tr>
                        <th style="width: 60%;">Product</th>
                        <th>Category</th>
                        <th>Price</th>
                        <th style="text-align: center;">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($my_submissions)): ?>
                        <tr>
                            <td colspan="4" style="text-align: center; padding: 60px 20px; color: #94a3b8;">
                                <i class="fas fa-box-open" style="font-size: 2.5rem; margin-bottom: 12px; display: block; color: #cbd5e1;"></i>
                                You haven't submitted any product suggestions yet.
                                <br>
                                <a href="?view=add" style="color: var(--primary); text-decoration: none; font-weight: 600; margin-top: 10px; display: inline-block;">+ Suggest your first product</a>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach($my_submissions as $ms): 
                            $clean_product_name = $ms['item_name'];
                            $clean_product_name = html_entity_decode($clean_product_name, ENT_QUOTES, 'UTF-8');
                            $clean_product_name = preg_replace('/[\x{2122}\x{00AE}\x{00A9}]/u', '', $clean_product_name);
                            $clean_product_name = str_replace(['™', '&#8482;', '&trade;', '®', '&#174;', '&reg;', '©', '&#169;', '&copy;'], '', $clean_product_name);
                            $clean_product_name = trim($clean_product_name);
                            
                            $clean_brand = $ms['brand'];
                            $clean_brand = html_entity_decode($clean_brand, ENT_QUOTES, 'UTF-8');
                            $clean_brand = preg_replace('/[\x{2122}\x{00AE}\x{00A9}]/u', '', $clean_brand);
                            $clean_brand = str_replace(['™', '&#8482;', '&trade;', 'TM', '(TM)', '[TM]', '®', '&#174;', '&reg;', '©', '&#169;', '&copy;'], '', $clean_brand);
                            $clean_brand = preg_replace('/\s*TM\s*$/i', '', $clean_brand);
                            $clean_brand = trim($clean_brand);
                        ?>
                        <tr>
                            <td style="display: flex; align-items: center; gap: 18px;">
                                <img src="../uploads/<?= htmlspecialchars($ms['item_image']) ?>" width="55" height="55" style="border-radius: 12px; object-fit: cover; border: 1px solid #e2e8f0;">
                                <div>
                                    <div style="font-weight: 700; font-size: 1rem; margin-bottom: 4px;"><?= htmlspecialchars($clean_product_name) ?></div>
                                    <small style="color: #64748b; display: block; margin-top: 4px;"><?= htmlspecialchars($clean_brand) ?></small>
                                </div>
                            </td>
                            <td style="white-space: nowrap;">
                                <span style="background: #f1f5f9; padding: 6px 12px; border-radius: 20px; font-size: 0.75rem; font-weight: 600; color: #475569; white-space: nowrap;">
                                    <i class="fas fa-tag" style="font-size: 0.7rem; margin-right: 4px;"></i>
                                    <?= htmlspecialchars($ms['category']) ?>
                                </span>
                            </td>
                            <td style="font-weight: 700; color: #059669; font-size: 1rem;">
                                ₱<?= number_format($ms['price'], 2) ?>
                            </td>
                            <td style="text-align: center;">
                                <?php if($ms['status'] == 'approved'): ?>
                                    <span class="status-badge status-approved"><i class="fas fa-check-circle"></i> APPROVED</span>
                                <?php elseif($ms['status'] == 'pending'): ?>
                                    <span class="status-badge status-pending"><i class="fas fa-clock"></i> PENDING</span>
                                <?php elseif($ms['status'] == 'rejected'): ?>
                                    <span class="status-badge status-rejected"><i class="fas fa-times-circle"></i> REJECTED</span>
                                <?php else: ?>
                                    <span class="status-badge"><?= strtoupper($ms['status']) ?></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</main>

<div id="descModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="modalTitle" style="color:var(--primary);"></h3>
        </div>
        <div class="modal-body" id="modalBody"></div>
        <div class="modal-footer">
            <button id="modalCloseBtn" style="background:var(--primary); color:white; padding:10px 20px; border-radius:8px; border:none; font-weight:600; cursor:pointer;">Close</button>
        </div>
    </div>
</div>

<div id="sidebarOverlay" class="sidebar-overlay" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 998;"></div>

<style>
    .sidebar-overlay {
        display: none;
    }
    .sidebar-overlay.active {
        display: block;
    }
    @media (max-width: 768px) {
        .sidebar-overlay.active {
            display: block;
        }
    }
</style>

<script>
    const hb = document.getElementById('hamburgerBtn');
    const sb = document.getElementById('sidebar');
    const ov = document.getElementById('sidebarOverlay');
    
    if (hb) {
        hb.onclick = () => { 
            sb.classList.toggle('active'); 
            ov.classList.toggle('active'); 
        };
    }
    
    if (ov) {
        ov.onclick = () => { 
            sb.classList.remove('active'); 
            ov.classList.remove('active'); 
        };
    }

    const modal = document.getElementById('descModal');
    const readMoreBtns = document.querySelectorAll('.read-more');
    
    if (readMoreBtns.length > 0) {
        readMoreBtns.forEach(btn => {
            btn.onclick = function() {
                document.getElementById('modalTitle').innerText = this.getAttribute('data-title');
                document.getElementById('modalBody').innerText = this.getAttribute('data-full') || "No specifications available.";
                modal.style.display = "flex";
            };
        });
    }
    
    const closeBtn = document.getElementById('modalCloseBtn');
    if (closeBtn) {
        closeBtn.onclick = () => modal.style.display = "none";
    }
    
    window.onclick = (e) => { if(e.target == modal) modal.style.display = "none"; };

    const catSelect = document.getElementById('catSelect');
    if (catSelect) {
        catSelect.onchange = () => document.getElementById('filterForm').submit();
    }

    window.addEventListener('pageshow', function(event) {
        if (event.persisted) {
            window.location.reload();
        }
    });
    
    history.pushState(null, null, location.href);
    
    window.addEventListener('popstate', function() {
        window.location.reload();
    });
</script>

</body>
</html>
