<?php


session_start();
require dirname(__DIR__) . '/includes/config.php';

checkRoleAccess('Admin');

$message = "";
$currentPage = $_GET['page'] ?? 'dashboard';


if (isset($_GET['action'])) {
    $id = $_GET['id'] ?? null;
    $public_id = $_GET['public_id'] ?? null;

    if ($_GET['action'] == 'archive_user' && $id) {
        $pdo->prepare("UPDATE users SET status = 'archived' WHERE id = ?")->execute([$id]);
        logActivity($pdo, $_SESSION['user_id'], "Archived user ID: $id", "User Management");
        header("Location: ?page=all_users&msg=User Archived"); exit;
    }
    
    if ($_GET['action'] == 'restore_user' && $id) {
        $pdo->prepare("UPDATE users SET status = 'active' WHERE id = ?")->execute([$id]);
        logActivity($pdo, $_SESSION['user_id'], "Restored user ID: $id", "User Management");
        header("Location: ?page=archived_users&msg=User Restored"); exit;
    }

    if ($_GET['action'] == 'archive_item' && $public_id) {
        $pdo->prepare("UPDATE items SET status = 'archived' WHERE public_id = ?")->execute([$public_id]);
        logActivity($pdo, $_SESSION['user_id'], "Archived item: $public_id", "Inventory");
        header("Location: ?page=all_products&msg=Item Archived"); exit;
    }

    if ($_GET['action'] == 'restore_item' && $public_id) {
        $pdo->prepare("UPDATE items SET status = 'approved' WHERE public_id = ?")->execute([$public_id]);
        logActivity($pdo, $_SESSION['user_id'], "Restored item: $public_id", "Inventory");
        header("Location: ?page=archived_products&msg=Item Restored"); exit;
    }

    if ($_GET['action'] == 'delete_rejected' && $public_id) {
        $pdo->prepare("DELETE FROM items WHERE public_id = ? AND status = 'rejected'")->execute([$public_id]);
        header("Location: ?page=pending_approvals&msg=Rejected Item Deleted"); exit;
    }
}

if (isset($_POST['add_user'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) { die("CSRF validation failed!"); }
    $pw = password_hash($_POST['password'], PASSWORD_BCRYPT);
    $stmt = $pdo->prepare("INSERT INTO users (firstname, lastname, email, password, role_id, status) VALUES (?, ?, ?, ?, 3, 'active')");
    $stmt->execute([$_POST['firstname'], $_POST['lastname'], $_POST['email'], $pw]);
    $message = "User registered successfully!";
}

if (isset($_POST['add_item'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) { die("CSRF validation failed!"); }
    $public_id = bin2hex(random_bytes(16)); 
    $image_name = "default.png";
    if (isset($_FILES['item_image']) && $_FILES['item_image']['error'] === 0) {
        $ext = strtolower(pathinfo($_FILES['item_image']['name'], PATHINFO_EXTENSION));
        $image_name = "IMG_" . bin2hex(random_bytes(8)) . "." . $ext;
        move_uploaded_file($_FILES['item_image']['tmp_name'], "../uploads/" . $image_name);
    }
    $stmt = $pdo->prepare("INSERT INTO items (public_id, item_name, brand, category, item_condition, price, stock_quantity, description, warranty, item_image, status, added_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'approved', ?)");
    $stmt->execute([$public_id, $_POST['item_name'], $_POST['brand'], $_POST['category'], $_POST['item_condition'], $_POST['price'], $_POST['stock'], $_POST['description'], $_POST['warranty'], $image_name, $_SESSION['user_id']]);
    $message = "Item added to inventory!";
}

if (isset($_POST['reset_user_pw'])) {
    $new_pw = password_hash($_POST['new_password'], PASSWORD_BCRYPT);
    $pdo->prepare("UPDATE users SET password = ? WHERE id = ?")->execute([$new_pw, $_POST['user_id']]);
    $message = "Password reset successful!";
}

if (isset($_GET['approve'])) {
    $pdo->prepare("UPDATE items SET status = 'approved' WHERE public_id = ?")->execute([$_GET['approve']]);
    header("Location: ?page=pending_approvals&msg=Approved"); exit;
}
if (isset($_GET['reject'])) {
    $pdo->prepare("UPDATE items SET status = 'rejected' WHERE public_id = ?")->execute([$_GET['reject']]);
    header("Location: ?page=pending_approvals&msg=Rejected"); exit;
}

$users = $pdo->query("SELECT * FROM users WHERE role_id = 3 AND status = 'active'")->fetchAll();
$archived_users = $pdo->query("SELECT * FROM users WHERE role_id = 3 AND status = 'archived'")->fetchAll();
$items = $pdo->query("SELECT * FROM items WHERE status = 'approved'")->fetchAll();
$pending_items = $pdo->query("SELECT * FROM items WHERE status = 'pending'")->fetchAll();
$archived_items = $pdo->query("SELECT * FROM items WHERE status = 'archived'")->fetchAll();
$rejected_items = $pdo->query("SELECT * FROM items WHERE status = 'rejected'")->fetchAll();

$recent_activities = $pdo->query("SELECT * FROM activity_logs ORDER BY created_at DESC LIMIT 5")->fetchAll();
$low_stock_items = $pdo->query("SELECT * FROM items WHERE stock_quantity <= 5 AND status = 'approved' ORDER BY stock_quantity ASC LIMIT 5")->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <title>Admin Dashboard | Computer Store</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --primary: #4f46e5; --sidebar: #0f172a; --bg: #f8fafc; --white: #ffffff; --text: #1e293b; --danger: #ef4444; --success: #10b981; --warning: #f59e0b; --info: #3b82f6; }
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Plus Jakarta Sans', sans-serif; }
        body { background: var(--bg); display: flex; color: var(--text); min-height: 100vh; }
        
        .sidebar { width: 260px; background: var(--sidebar); position: fixed; height: 100vh; color: white; padding: 20px; }
        .nav-link { 
            display: flex; align-items: center; padding: 12px; color: #94a3b8; text-decoration: none; 
            border-radius: 8px; margin-bottom: 5px; transition: 0.3s; background: transparent; border: none; width: 100%; cursor: pointer;
        }
        .nav-link:hover, .nav-link.active { background: #1e293b; color: white; }
        .nav-link.active { background: var(--primary); }
        .dropdown-container { display: none; padding-left: 20px; background: rgba(0,0,0,0.2); border-radius: 8px; margin-bottom: 10px; }
        .dropdown-container.show { display: block; }
        .dropdown-container a { display: block; padding: 10px; color: #94a3b8; text-decoration: none; font-size: 0.9rem; }
        .dropdown-container a:hover { color: white; }

        .main { flex: 1; margin-left: 260px; padding: 40px; }
        .grid-stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .stat-card { background: white; padding: 25px; border-radius: 15px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); text-decoration: none; color: inherit; border-bottom: 4px solid #e2e8f0; transition: 0.2s; }
        .stat-card:hover { transform: translateY(-3px); border-bottom-color: var(--primary); }
        .stat-card h3 { font-size: 0.75rem; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 10px; }
        .stat-card .num { font-size: 2rem; font-weight: 800; display: block; margin-top: 5px; }
        
        .glass-box { background: white; padding: 30px; border-radius: 20px; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1); margin-bottom: 30px; }
        .section-header { margin-bottom: 20px; }
        .section-header h3 { font-size: 1.2rem; font-weight: 700; margin: 0; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th { text-align: left; padding: 12px; background: #f8fafc; font-size: 0.75rem; text-transform: uppercase; color: #64748b; }
        td { padding: 15px 12px; border-bottom: 1px solid #f1f5f9; font-size: 0.9rem; }
        .thumb { width: 50px; height: 50px; object-fit: cover; border-radius: 8px; }
        
        .btn { padding: 8px 14px; border-radius: 8px; border: none; cursor: pointer; font-weight: 600; text-decoration: none; font-size: 0.8rem; display: inline-flex; align-items: center; gap: 5px; }
        .btn-primary { background: var(--primary); color: white; }
        .btn-danger { background: #fef2f2; color: var(--danger); }
        .btn-success { background: #f0fdf4; color: #166534; }
        .btn-warning { background: #fffbeb; color: #f59e0b; }
        .btn-info { background: #eff6ff; color: var(--info); }

        .two-columns { display: grid; grid-template-columns: 1fr 1fr; gap: 30px; margin-bottom: 30px; }
        .activity-list { list-style: none; }
        .activity-list li { padding: 12px 0; border-bottom: 1px solid #f1f5f9; display: flex; align-items: center; gap: 12px; }
        .activity-list li:last-child { border-bottom: none; }
        .activity-icon { width: 32px; height: 32px; background: #f1f5f9; border-radius: 50%; display: flex; align-items: center; justify-content: center; }
        .activity-detail { flex: 1; }
        .activity-detail strong { display: block; font-size: 0.85rem; }
        .activity-detail small { font-size: 0.7rem; color: #94a3b8; }
        .badge-low-stock { background: #fef2f2; color: #dc2626; padding: 2px 8px; border-radius: 20px; font-size: 0.7rem; font-weight: 600; }
        
        input, select, textarea { width: 100%; padding: 12px; margin: 8px 0 15px; border: 1px solid #e2e8f0; border-radius: 10px; background: #fcfdfe; }
        
        .modal { display: none; position: fixed; inset: 0; background: rgba(15, 23, 42, 0.7); backdrop-filter: blur(4px); justify-content: center; align-items: center; z-index: 2000; }
        .modal-content { background: white; padding: 30px; border-radius: 20px; width: 400px; text-align: center; }
        .edit-modal { display: none; position: fixed; inset: 0; background: rgba(15, 23, 42, 0.7); backdrop-filter: blur(4px); justify-content: center; align-items: center; z-index: 2100; padding: 20px; overflow-y: auto; }
        .edit-modal-content { background: white; padding: 25px 30px; border-radius: 15px; max-width: 500px; width: 100%; max-height: 90vh; overflow-y: auto; box-shadow: 0 5px 20px rgba(0,0,0,0.2); }
        
        @media (max-width: 768px) {
            .two-columns { grid-template-columns: 1fr; gap: 20px; }
        }
    </style>
</head>
<body>

<div class="sidebar">
    <h2 style="margin-bottom: 35px; font-weight: 800; display:flex; align-items:center; gap:10px;">
        <i class="fas fa-microchip" style="color:var(--primary)"></i> TechAdmin
    </h2>
    
    <a href="?page=dashboard" class="nav-link <?= $currentPage=='dashboard'?'active':'' ?>">
        <i class="fas fa-house"></i> &nbsp; Dashboard
    </a>
    
    <button class="nav-link" onclick="toggleDrop('prodDrop')">
        <i class="fas fa-boxes-stacked"></i> &nbsp; Products &nbsp; <i class="fas fa-chevron-down" style="font-size: 0.7rem; margin-left: auto;"></i>
    </button>
    <div id="prodDrop" class="dropdown-container <?= (strpos($currentPage, 'prod')!==false || $currentPage == 'pending_approvals') ? 'show':'' ?>">
        <a href="?page=all_products">All Products</a>
        <a href="?page=add_products">Add Product</a>
        <a href="?page=pending_approvals">Pending (<?= count($pending_items) ?>)</a>
        <a href="?page=archived_products">Archived Items</a>
    </div>

    <button class="nav-link" onclick="toggleDrop('userDrop')">
        <i class="fas fa-user-gear"></i> &nbsp; Users &nbsp; <i class="fas fa-chevron-down" style="font-size: 0.7rem; margin-left: auto;"></i>
    </button>
    <div id="userDrop" class="dropdown-container <?= strpos($currentPage, 'user')!==false ? 'show':'' ?>">
        <a href="?page=all_users">All Users</a>
        <a href="?page=archived_users">Archived Users</a>
    </div>

    <a href="../auth/logout.php" class="nav-link" style="margin-top: 50px; color: #fca5a5;"><i class="fas fa-power-off"></i> &nbsp; Logout</a>
</div>

<div class="main">
    <?php if(isset($_GET['msg']) || $message): ?>
        <div style="background: #dcfce7; color: #166534; padding: 15px; border-radius: 12px; margin-bottom: 25px; font-weight: 600; border: 1px solid #bbf7d0;">
            <i class="fas fa-circle-check"></i> &nbsp; <?= $message ?: $_GET['msg'] ?>
        </div>
    <?php endif; ?>

    <?php if($currentPage == 'dashboard'): ?>
        <!-- Row 1: Main Stats Cards (4 cards) -->
        <div class="grid-stats">
            <div class="stat-card">
                <h3><i class="fas fa-boxes"></i> Active Inventory</h3>
                <span class="num"><?= count($items) ?></span>
                <small>Total products in stock</small>
            </div>
            <div class="stat-card">
                <h3><i class="fas fa-clock"></i> Pending Approvals</h3>
                <span class="num" style="color:#f59e0b"><?= count($pending_items) ?></span>
                <small>Items waiting for review</small>
            </div>
            <div class="stat-card">
                <h3><i class="fas fa-users"></i> Total Customers</h3>
                <span class="num"><?= count($users) ?></span>
                <small>Active registered users</small>
            </div>
            <div class="stat-card">
                <h3><i class="fas fa-archive"></i> Archived Items</h3>
                <span class="num"><?= count($archived_items) ?></span>
                <small>Items in recycle bin</small>
            </div>
        </div>

        <div class="two-columns">
            <div class="glass-box">
                <div class="section-header">
                    <h3><i class="fas fa-history"></i> Recent Activities</h3>
                </div>
                <?php if(empty($recent_activities)): ?>
                    <p style="color:#94a3b8; text-align: center; padding: 20px;">No recent activities</p>
                <?php else: ?>
                    <ul class="activity-list">
                        <?php foreach($recent_activities as $activity): ?>
                        <li>
                            <div class="activity-icon">
                                <?php if($activity['action_type'] == 'Inventory'): ?>
                                    <i class="fas fa-box" style="color: var(--primary);"></i>
                                <?php elseif($activity['action_type'] == 'User Management'): ?>
                                    <i class="fas fa-user" style="color: var(--success);"></i>
                                <?php else: ?>
                                    <i class="fas fa-shield-alt" style="color: var(--warning);"></i>
                                <?php endif; ?>
                            </div>
                            <div class="activity-detail">
                                <strong><?= htmlspecialchars($activity['action_details']) ?></strong>
                                <small><?= date('M d, Y h:i A', strtotime($activity['created_at'])) ?></small>
                            </div>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>

            <div class="glass-box">
                <div class="section-header">
                    <h3><i class="fas fa-exclamation-triangle"></i> Low Stock Alerts</h3>
                </div>
                <?php if(empty($low_stock_items)): ?>
                    <p style="color:#94a3b8; text-align: center; padding: 20px;">
                        <i class="fas fa-check-circle" style="color: var(--success); font-size: 2rem; display: block; margin-bottom: 10px;"></i>
                        All items have sufficient stock
                    </p>
                <?php else: ?>
                    <div style="overflow-x: auto;">
                        <table style="width: 100%;">
                            <thead>
                                <tr>
                                    <th>Product</th>
                                    <th>Current Stock</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($low_stock_items as $item): ?>
                                <tr>
                                    <td>
                                        <strong><?= htmlspecialchars($item['item_name']) ?></strong><br>
                                        <small style="color:#64748b"><?= htmlspecialchars($item['brand']) ?></small>
                                    </td>
                                    <td style="font-weight: 700; color: #dc2626;"><?= $item['stock_quantity'] ?> left</td>
                                    <td><span class="badge-low-stock"><i class="fas fa-bell"></i> Low Stock</span></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="glass-box">
            <div class="section-header">
                <h3><i class="fas fa-bolt"></i> Quick Actions</h3>
            </div>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 15px;">
                <a href="?page=add_products" class="btn btn-primary" style="justify-content: center; padding: 12px;">
                    <i class="fas fa-plus-circle"></i> Add New Product
                </a>
                <a href="?page=all_users" class="btn btn-info" style="justify-content: center; padding: 12px; background: #eff6ff; color: #2563eb;">
                    <i class="fas fa-user-plus"></i> Create User
                </a>
                <a href="?page=pending_approvals" class="btn btn-warning" style="justify-content: center; padding: 12px;">
                    <i class="fas fa-check-double"></i> Review Pending
                    <?php if(count($pending_items) > 0): ?>
                        <span style="background: #ef4444; color: white; border-radius: 20px; padding: 2px 8px; font-size: 0.7rem;"><?= count($pending_items) ?></span>
                    <?php endif; ?>
                </a>
                <a href="?page=archived_products" class="btn btn-danger" style="justify-content: center; padding: 12px;">
                    <i class="fas fa-trash-restore"></i> Manage Archive
                </a>
            </div>
        </div>

    <?php elseif($currentPage == 'all_products'): ?>
        <div class="glass-box">
            <div class="section-header" style="display:flex; justify-content:space-between; align-items:center;">
                <h3>Current Stock</h3>
                <a href="?page=add_products" class="btn btn-primary">+ Add New</a>
            </div>
            <div style="overflow-x: auto;">
                <table style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr>
                            <th style="text-align: left; padding: 12px; background: #f8fafc;">Image</th>
                            <th style="text-align: left; padding: 12px; background: #f8fafc;">Details</th>
                            <th style="text-align: left; padding: 12px; background: #f8fafc;">Price</th>
                            <th style="text-align: left; padding: 12px; background: #f8fafc;">Stock</th>
                            <th style="text-align: right; padding: 12px; background: #f8fafc;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($items as $i): ?>
                        <tr>
                            <td><img src="../uploads/<?= $i['item_image'] ?>" class="thumb"></td>
                            <td><strong><?= htmlspecialchars($i['item_name']) ?></strong><br><small style="color:#64748b"><?= $i['brand'] ?></small></td>
                            <td>₱<?= number_format($i['price'], 2) ?></td>
                            <td><?= $i['stock_quantity'] ?> 
                                <?php if($i['stock_quantity'] <= 5): ?>
                                    <span class="badge-low-stock" style="margin-left: 5px;">Low</span>
                                <?php endif; ?>
                            </td>
                            <td style="text-align: right;">
                                <button class="btn btn-success openEditModal" 
                                        data-id="<?= $i['public_id'] ?>"
                                        data-name="<?= htmlspecialchars($i['item_name'], ENT_QUOTES) ?>"
                                        data-brand="<?= htmlspecialchars($i['brand'], ENT_QUOTES) ?>"
                                        data-category="<?= $i['category'] ?>"
                                        data-condition="<?= $i['item_condition'] ?>"
                                        data-price="<?= $i['price'] ?>"
                                        data-stock="<?= $i['stock_quantity'] ?>"
                                        data-warranty="<?= htmlspecialchars($i['warranty'], ENT_QUOTES) ?>"
                                        data-description="<?= htmlspecialchars($i['description'], ENT_QUOTES) ?>"
                                        style="padding: 8px 14px; border-radius: 8px; border: none; cursor: pointer; font-weight: 600; font-size: 0.8rem; background: #f0fdf4; color: #166534; margin-right: 5px;">
                                    <i class="fas fa-edit"></i> Edit
                                </button>
                                <button class="btn btn-danger openArchiveModal" data-type="item" data-id="<?= $i['public_id'] ?>" style="padding: 8px 14px; border-radius: 8px; border: none; cursor: pointer; font-weight: 600; font-size: 0.8rem; background: #fef2f2; color: #ef4444;">
                                    <i class="fas fa-archive"></i> Archive
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

    <?php elseif($currentPage == 'add_products'): ?>
        <div class="glass-box" style="max-width: 800px; margin: auto;">
            <h3>New Product Submission</h3><br>
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                <div style="display:grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                    <div><label>Model Name</label><input type="text" name="item_name" required></div>
                    <div><label>Brand</label><input type="text" name="brand" required></div>
                </div>
                <div style="display:grid; grid-template-columns: 1fr 1fr 1fr; gap: 15px;">
                    <div>
                        <label>Category</label>
                        <select name="category" >
                            <option value=""> Select Category </option>
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
                        <label>Condition</label>
                        <select name="item_condition">
                            <option value="Brand New">Brand New</option>
                            <option value="Used">Used</option>
                        </select>
                    </div>
                    <div><label>Price (₱)</label><input type="number" name="price" step="0.01"></div>
                    <div><label>Stock</label><input type="number" name="stock"></div>
                </div>
                <label>Description</label><textarea name="description" rows="4"></textarea>
                <label>Warranty</label><input type="text" name="warranty">
                <label>Image</label><input type="file" name="item_image">
                <button type="submit" name="add_item" class="btn btn-primary" style="width:100%; padding:15px; font-size:1rem;">Save Product</button>
            </form>
        </div>

    <?php elseif($currentPage == 'pending_approvals'): ?>
        <div class="glass-box">
            <div class="section-header">
                <h3>Pending Review</h3>
            </div>
            <div style="overflow-x: auto;">
                <table style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr>
                            <th style="text-align: left; padding: 12px; background: #f8fafc;">Image</th>
                            <th style="text-align: left; padding: 12px; background: #f8fafc;">Name</th>
                            <th style="text-align: left; padding: 12px; background: #f8fafc;">Price</th>
                            <th style="text-align: right; padding: 12px; background: #f8fafc;">Decision</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($pending_items as $p): ?>
                        <tr>
                            <td><img src="../uploads/<?= $p['item_image'] ?>" class="thumb"></td>
                            <td><strong><?= $p['item_name'] ?></strong></td>
                            <td>₱<?= number_format($p['price'], 2) ?></td>
                            <td style="text-align: right;">
                                <a href="?approve=<?= $p['public_id'] ?>" class="btn btn-primary" style="margin-right: 5px;">Approve</a>
                                <a href="?reject=<?= $p['public_id'] ?>" class="btn btn-danger">Reject</a>
                            <td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="glass-box">
            <div class="section-header">
                <h3>Rejected Suggestions</h3>
            </div>
            <?php if(empty($rejected_items)): ?>
                <p style="color:#94a3b8; text-align: center; padding: 20px;">No rejected items found.</p>
            <?php else: ?>
                <div style="overflow-x: auto;">
                    <table style="width: 100%; border-collapse: collapse;">
                        <thead>
                            <tr>
                                <th style="text-align: left; padding: 12px; background: #f8fafc;">Name</th>
                                <th style="text-align: left; padding: 12px; background: #f8fafc;">Category</th>
                                <th style="text-align: left; padding: 12px; background: #f8fafc;">Price</th>
                                <th style="text-align: right; padding: 12px; background: #f8fafc;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($rejected_items as $ri): ?>
                            <tr>
                                <td><?= htmlspecialchars($ri['item_name']) ?></td>
                                <td><?= $ri['category'] ?></td>
                                <td>₱<?= number_format($ri['price'], 2) ?></td>
                                <td style="text-align: right;">
                                    <button class="btn btn-danger openDeleteModal"
                                            data-id="<?= $ri['public_id'] ?>"
                                            style="padding: 8px 14px; border-radius: 8px; border: none; cursor: pointer; font-weight: 600; font-size: 0.8rem; background: #fef2f2; color: #ef4444;">
                                        <i class="fas fa-trash"></i> Delete
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

    <?php elseif($currentPage == 'all_users'): ?>
        <div class="glass-box">
            <div class="section-header">
                <h3>Register New User</h3>
            </div>
            <form method="POST" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 15px;">
                <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                <input type="text" name="firstname" placeholder="First Name" required>
                <input type="text" name="lastname" placeholder="Last Name" required>
                <input type="email" name="email" placeholder="Email" required>
                <input type="password" name="password" placeholder="Password" required>
                <button type="submit" name="add_user" class="btn btn-primary" style="height:48px; margin-top:8px;">Create User</button>
            </form>
        </div>
        <div class="glass-box">
            <div class="section-header">
                <h3>Active Regular Users</h3>
            </div>
            <div style="overflow-x: auto;">
                <table style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr>
                            <th style="text-align: left; padding: 12px; background: #f8fafc;">Name</th>
                            <th style="text-align: left; padding: 12px; background: #f8fafc;">Email</th>
                            <th style="text-align: left; padding: 12px; background: #f8fafc;">Reset Password</th>
                            <th style="text-align: right; padding: 12px; background: #f8fafc;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($users as $u): ?>
                        <tr>
                            <td><i class="fas fa-user" style="color:#94a3b8; margin-right: 8px;"></i><?= htmlspecialchars($u['firstname'].' '.$u['lastname']) ?></td>
                            <td><i class="fas fa-envelope" style="color:#94a3b8; margin-right: 8px;"></i><?= $u['email'] ?></td>
                            <td>
                                <button class="btn btn-warning" onclick="openResetModal('<?= $u['id'] ?>', '<?= htmlspecialchars($u['firstname']) ?>')">
                                    <i class="fas fa-key"></i> Reset
                                </button>
                            </td>
                            <td style="text-align: right;">
                                <button class="btn btn-danger openArchiveModal" data-type="user" data-id="<?= $u['id'] ?>">
                                    <i class="fas fa-archive"></i> Archive
                                </button>
                            </td>
                        </table>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

    <?php elseif($currentPage == 'archived_products'): ?>
        <div class="glass-box">
            <div class="section-header">
                <h3>Recycle Bin (Items)</h3>
            </div>
            <div style="overflow-x: auto;">
                <table style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr>
                            <th style="text-align: left; padding: 12px; background: #f8fafc;">Image</th>
                            <th style="text-align: left; padding: 12px; background: #f8fafc;">Item Name</th>
                            <th style="text-align: left; padding: 12px; background: #f8fafc;">Brand</th>
                            <th style="text-align: left; padding: 12px; background: #f8fafc;">Category</th>
                            <th style="text-align: left; padding: 12px; background: #f8fafc;">Price</th>
                            <th style="text-align: right; padding: 12px; background: #f8fafc;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($archived_items as $ai): ?>
                        <tr>
                            <td><img src="../uploads/<?= htmlspecialchars($ai['item_image']) ?>" width="50" height="50" style="border-radius: 8px; object-fit: cover;"></td>
                            <td style="font-weight: 600;"><?= htmlspecialchars($ai['item_name']) ?></td>
                            <td><?= htmlspecialchars($ai['brand']) ?></td>
                            <td><span style="background: #f1f5f9; padding: 4px 10px; border-radius: 20px; font-size: 0.75rem;"><?= htmlspecialchars($ai['category']) ?></span></td>
                            <td style="font-weight: 700; color: #059669;">₱<?= number_format($ai['price'], 2) ?></td>
                            <td style="text-align: right;">
                                <button class="btn btn-success openRestoreModal" data-type="item" data-id="<?= $ai['public_id'] ?>" style="padding: 8px 14px; border-radius: 8px; border: none; cursor: pointer; font-weight: 600; font-size: 0.8rem; background: #f0fdf4; color: #166534;">
                                    <i class="fas fa-undo"></i> Restore
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if(empty($archived_items)): ?>
                        <tr>
                            <td colspan="6" style="text-align: center; padding: 40px; color: #94a3b8;">
                                <i class="fas fa-box-open" style="font-size: 2rem; margin-bottom: 10px; display: block;"></i>
                                No archived items found.
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    <?php elseif($currentPage == 'archived_users'): ?>
        <div class="glass-box">
            <div class="section-header">
                <h3>Disabled Accounts</h3>
            </div>
            <div style="overflow-x: auto;">
                <table style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr>
                            <th style="text-align: left; padding: 12px; background: #f8fafc;">#</th>
                            <th style="text-align: left; padding: 12px; background: #f8fafc;">Full Name</th>
                            <th style="text-align: left; padding: 12px; background: #f8fafc;">Email Address</th>
                            <th style="text-align: left; padding: 12px; background: #f8fafc;">Status</th>
                            <th style="text-align: right; padding: 12px; background: #f8fafc;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $counter = 1; ?>
                        <?php foreach($archived_users as $au): ?>
                        <tr>
                            <td style="color: #64748b; width: 50px;"><?= $counter++ ?></td>
                            <td style="font-weight: 600;"><i class="fas fa-user-circle" style="color: #94a3b8; margin-right: 8px;"></i><?= htmlspecialchars($au['firstname'].' '.$au['lastname']) ?></td>
                            <td><i class="fas fa-envelope" style="color: #94a3b8; margin-right: 8px;"></i><?= htmlspecialchars($au['email']) ?></td>
                            <td><span style="background: #fef2f2; color: #dc2626; padding: 4px 12px; border-radius: 20px; font-size: 0.7rem; font-weight: 700;"><i class="fas fa-ban"></i> ARCHIVED</span></td>
                            <td style="text-align: right;">
                                <button class="btn btn-success openRestoreModal" data-type="user" data-id="<?= $au['id'] ?>" style="padding: 8px 14px; border-radius: 8px; border: none; cursor: pointer; font-weight: 600; font-size: 0.8rem; background: #f0fdf4; color: #166534;">
                                    <i class="fas fa-undo"></i> Restore
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if(empty($archived_users)): ?>
                        <tr>
                            <td colspan="5" style="text-align: center; padding: 40px; color: #94a3b8;">
                                <i class="fas fa-users-slash" style="font-size: 2rem; margin-bottom: 10px; display: block;"></i>
                                No archived users found.
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- MODALS -->
<div id="deleteModal" class="modal">
    <div class="modal-content">
        <i class="fas fa-trash" style="font-size:2rem; color:var(--danger)"></i>
        <h3 style="margin-top:15px;">Delete Permanently?</h3>
        <p style="margin:10px 0; color:var(--text-muted);">This action cannot be undone.</p>
        <div style="display:flex; gap:10px; justify-content:center; margin-top:20px;">
            <a id="deleteConfirmBtn" href="#" class="btn btn-danger">Yes, Delete</a>
            <button type="button" class="btn btn-primary" onclick="closeModals()">Cancel</button>
        </div>
    </div>
</div>

<div id="editItemModal" class="modal edit-modal">
    <div class="modal-content edit-modal-content">
        <h3>Edit Item</h3>
        <form id="editItemForm" method="POST" enctype="multipart/form-data" action="">
            <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
            <label>Item Name</label>
            <input type="text" name="item_name" id="editItemName" required>
            <label>Brand</label>
            <input type="text" name="brand" id="editItemBrand" required>
            <label>Category</label>
            <select name="category" id="editItemCategory">
                <option value=""> Select Category </option>
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
            <label>Condition</label>
            <select name="item_condition" id="editItemCondition">
                <option value="Brand New">Brand New</option>
                <option value="Used">Used</option>
                <option value="Refurbished">Refurbished</option>
            </select>
            <label>Price (₱)</label>
            <input type="number" name="price" id="editItemPrice" step="0.01">
            <label>Stock Quantity</label>
            <input type="number" name="stock" id="editItemStock">
            <label>Warranty</label>
            <input type="text" name="warranty" id="editItemWarranty">
            <label>Description</label>
            <textarea name="description" id="editItemDescription"></textarea>
            <label>Change Image (Optional)</label>
            <input type="file" name="item_image" accept="image/*">
            <div style="display:flex; gap:10px; justify-content:center; margin-top:15px;">
                <button type="submit" name="update" class="btn btn-primary">Save Changes</button>
                <button type="button" class="btn btn-danger" onclick="closeModals()">Cancel</button>
            </div>
        </form>
    </div>
</div>

<div id="archiveModal" class="modal">
    <div class="modal-content">
        <i class="fas fa-exclamation-triangle" style="font-size:2rem; color:var(--danger)"></i>
        <h3 id="archiveTitle" style="margin-top:15px;">Archive?</h3>
        <p id="archiveMessage" style="margin:10px 0; color:var(--text-muted);"></p>
        <div style="display:flex; gap:10px; justify-content:center; margin-top:20px;">
            <a id="archiveConfirmBtn" href="#" class="btn btn-danger">Yes, Archive</a>
            <button type="button" class="btn btn-primary" onclick="closeModals()">Cancel</button>
        </div>
    </div>
</div>

<div id="restoreModal" class="modal">
    <div class="modal-content">
        <h3 id="restoreTitle">Restore?</h3>
        <p id="restoreMessage" style="margin:10px 0; color:var(--text-muted);"></p>
        <div style="display:flex; gap:10px; justify-content:center; margin-top:20px;">
            <a id="restoreConfirmBtn" href="#" class="btn btn-success">Yes, Restore</a>
            <button type="button" class="btn btn-primary" onclick="closeModals()">Cancel</button>
        </div>
    </div>
</div>

<div id="resetModal" class="modal">
    <div class="modal-content">
        <h3>Reset Password</h3>
        <p style="margin:10px 0; color:var(--text-muted);">New password for <strong id="resetTargetName"></strong></p>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
            <input type="hidden" name="user_id" id="resetUserId">
            <input type="password" name="new_password" placeholder="Enter New Password" required>
            <div style="display:flex; gap:10px; justify-content:center; margin-top:10px;">
                <button type="submit" name="reset_user_pw" class="btn btn-primary">Update</button>
                <button type="button" class="btn btn-danger" onclick="closeModals()">Cancel</button>
            </div>
        </form>
    </div>
</div>

<script>
    function closeModals() {
        document.querySelectorAll('.modal').forEach(m => m.style.display = 'none');
    }

    function openResetModal(id, name) {
        document.getElementById('resetUserId').value = id;
        document.getElementById('resetTargetName').innerText = name;
        document.getElementById('resetModal').style.display = 'flex';
    }

    document.querySelectorAll(".openArchiveModal").forEach(btn => {
        btn.onclick = () => {
            const type = btn.dataset.type;
            const id = btn.dataset.id;
            const title = document.getElementById("archiveTitle");
            const message = document.getElementById("archiveMessage");
            const confirmBtn = document.getElementById("archiveConfirmBtn");
            let url = "";
            if (type === "user") {
                title.innerText = "Archive User?";
                message.innerText = "This user account will be deactivated and lose system access.";
                url = "?action=archive_user&id=" + id;
            }
            if (type === "item") {
                title.innerText = "Archive Product?";
                message.innerText = "This product will be archived and removed from the active inventory.";
                url = "?action=archive_item&public_id=" + id;
            }
            confirmBtn.href = url;
            document.getElementById("archiveModal").style.display = "flex";
        }
    });

    document.querySelectorAll(".openEditModal").forEach(btn => {
        btn.onclick = () => {
            const modal = document.getElementById("editItemModal");
            const form = document.getElementById("editItemForm");
            document.getElementById("editItemName").value = btn.dataset.name;
            document.getElementById("editItemBrand").value = btn.dataset.brand;
            document.getElementById("editItemCategory").value = btn.dataset.category;
            document.getElementById("editItemCondition").value = btn.dataset.condition;
            document.getElementById("editItemPrice").value = btn.dataset.price;
            document.getElementById("editItemStock").value = btn.dataset.stock;
            document.getElementById("editItemWarranty").value = btn.dataset.warranty;
            document.getElementById("editItemDescription").value = btn.dataset.description;
            form.action = "update_item.php?id=" + btn.dataset.id;
            modal.style.display = "flex";
        }
    });

    /* DELETE MODAL */
    document.querySelectorAll(".openDeleteModal").forEach(btn => {
        btn.onclick = () => {
            const id = btn.dataset.id;
            document.getElementById("deleteConfirmBtn").href = "?action=delete_rejected&public_id=" + id;
            document.getElementById("deleteModal").style.display = "flex";
        }
    });

    document.querySelectorAll(".openRestoreModal").forEach(btn => {
        btn.onclick = () => {
            const type = btn.dataset.type;
            const id = btn.dataset.id;
            const title = document.getElementById("restoreTitle");
            const message = document.getElementById("restoreMessage");
            const confirmBtn = document.getElementById("restoreConfirmBtn");
            let url = "";
            if (type === "user") {
                title.innerText = "Restore User Account?";
                message.innerText = "This will reactivate the user account and restore access.";
                url = "?action=restore_user&id=" + id;
            }
            if (type === "item") {
                title.innerText = "Restore Product?";
                message.innerText = "This product will return to the active inventory.";
                url = "?action=restore_item&public_id=" + id;
            }
            confirmBtn.href = url;
            document.getElementById("restoreModal").style.display = "flex";
        }
    });

    window.onclick = (event) => {
        if (event.target.classList.contains("modal")) {
            closeModals();
        }
    };

    function toggleDrop(id) {
        const drop = document.getElementById(id);
        const allDrops = document.querySelectorAll('.dropdown-container');
        const isShown = drop.classList.contains('show');
        allDrops.forEach(d => d.classList.remove('show'));
        if (!isShown) drop.classList.add('show');
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
