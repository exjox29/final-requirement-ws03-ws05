<?php

session_start();
require dirname(__DIR__) . '/includes/config.php';

checkRoleAccess('Super Admin');

$message = "";
$view = $_GET['view'] ?? 'home'; 

$totalAdmins = $pdo->query("SELECT COUNT(*) FROM users WHERE role_id = 2 AND status = 'active'")->fetchColumn();
$archivedAdmins = $pdo->query("SELECT COUNT(*) FROM users WHERE role_id = 2 AND status = 'archived'")->fetchColumn();
$totalLogs = $pdo->query("SELECT COUNT(*) FROM activity_logs")->fetchColumn();

$totalRegularUsers = $pdo->query("SELECT COUNT(*) FROM users WHERE role_id = 3 AND status = 'active'")->fetchColumn();
$recentActivities = $pdo->query("SELECT * FROM activity_logs ORDER BY created_at DESC LIMIT 5")->fetchAll();

if (isset($_POST['reset_pw'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) { die("CSRF validation failed!"); }
    $id = $_POST['user_id'];
    $new_pass = password_hash($_POST['new_password'], PASSWORD_BCRYPT);
    $stmtName = $pdo->prepare("SELECT firstname, lastname FROM users WHERE id = ?");
    $stmtName->execute([$id]);
    $target = $stmtName->fetch();
    $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ? AND role_id = 2");
    $stmt->execute([$new_pass, $id]);
    $targetName = ($target) ? $target['firstname'] . " " . $target['lastname'] : "Admin";
    logActivity($pdo, $_SESSION['user_id'], "Super Admin {$_SESSION['name']} reset password for: $targetName", "Security");
    $message = "Password for $targetName reset successfully!";
}

if (isset($_POST['add_admin'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) { die("CSRF validation failed!"); }
    $fn = $_POST['firstname']; $ln = $_POST['lastname']; $em = $_POST['email'];
    $pw = password_hash($_POST['password'], PASSWORD_BCRYPT);
    try {
        $stmt = $pdo->prepare("INSERT INTO users (firstname, lastname, email, password, role_id, status) VALUES (?, ?, ?, ?, 2, 'active')");
        $stmt->execute([$fn, $ln, $em, $pw]);
        logActivity($pdo, $_SESSION['user_id'], "Super Admin created Admin: $fn $ln", "User Management");
        $message = "Admin account created successfully!";
    } catch (Exception $e) { $message = "Error: Email already exists."; }
}

if (isset($_GET['archive'])) {
    $pdo->prepare("UPDATE users SET status = 'archived' WHERE id = ? AND role_id = 2")->execute([$_GET['archive']]);
    header("Location: ?view=manage_admins&msg=Archived"); exit;
}
if (isset($_GET['restore'])) {
    $pdo->prepare("UPDATE users SET status = 'active' WHERE id = ? AND role_id = 2")->execute([$_GET['restore']]);
    header("Location: ?view=archived_admins&msg=Restored"); exit;
}

$admins = $pdo->query("SELECT * FROM users WHERE role_id = 2 AND status = 'active'")->fetchAll();
$archived_list = $pdo->query("SELECT * FROM users WHERE role_id = 2 AND status = 'archived'")->fetchAll();

$limit = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;
$logStmt = $pdo->prepare("SELECT al.*, u.firstname, u.lastname FROM activity_logs al LEFT JOIN users u ON al.user_id = u.id ORDER BY al.created_at DESC LIMIT ? OFFSET ?");
$logStmt->bindValue(1, $limit, PDO::PARAM_INT);
$logStmt->bindValue(2, $offset, PDO::PARAM_INT);
$logStmt->execute();
$logs = $logStmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <title>Super Admin | System Control</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --primary: #4f46e5; --sidebar: #0f172a; --bg: #f8fafc; --white: #ffffff; --text: #1e293b; --danger: #ef4444; --success: #10b981; --warning: #f59e0b; --info: #3b82f6; }
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Plus Jakarta Sans', sans-serif; }
        body { background: var(--bg); display: flex; color: var(--text); min-height: 100vh; }
        
        .sidebar { width: 260px; background: var(--sidebar); position: fixed; height: 100vh; color: white; padding: 20px; z-index: 1000; }
        .sidebar-brand { margin-bottom: 35px; font-weight: 800; font-size: 1.4rem; display:flex; align-items:center; gap:10px; }
        .nav-link { 
            display: flex; align-items: center; padding: 12px; color: #94a3b8; text-decoration: none; 
            border-radius: 8px; margin-bottom: 5px; transition: 0.3s; background: transparent; border: none; width: 100%; cursor: pointer;
        }
        .nav-link:hover, .nav-link.active { background: #1e293b; color: white; }
        .nav-link.active { background: var(--primary); }
        
        .main { flex: 1; margin-left: 260px; padding: 40px; }
        .grid-stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .stat-card { background: white; padding: 25px; border-radius: 15px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); border-bottom: 4px solid #e2e8f0; transition: 0.2s; }
        .stat-card:hover { transform: translateY(-3px); border-bottom-color: var(--primary); }
        .stat-card h3 { font-size: 0.75rem; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 8px; }
        .stat-card .num { font-size: 2rem; font-weight: 800; display: block; margin-top: 5px; }
        .stat-card small { font-size: 0.7rem; color: #94a3b8; }
        
        .glass-box { background: white; padding: 30px; border-radius: 20px; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1); margin-bottom: 30px; }
        .section-header { margin-bottom: 20px; }
        .section-header h3 { font-size: 1.2rem; font-weight: 700; margin: 0; }
        
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th { text-align: left; padding: 12px; background: #f8fafc; font-size: 0.75rem; text-transform: uppercase; color: #64748b; }
        td { padding: 15px 12px; border-bottom: 1px solid #f1f5f9; font-size: 0.9rem; }
        
        .btn { padding: 8px 14px; border-radius: 8px; border: none; cursor: pointer; font-weight: 600; text-decoration: none; font-size: 0.8rem; display: inline-flex; align-items: center; gap: 5px; }
        .btn-primary { background: var(--primary); color: white; }
        .btn-danger { background: #fef2f2; color: var(--danger); }
        .btn-warning { background: #fffbeb; color: #f59e0b; }
        .btn-success { background: #f0fdf4; color: #166534; }
        .btn-info { background: #eff6ff; color: var(--info); }
        
        input, select { width: 100%; padding: 12px; margin: 8px 0 15px; border: 1px solid #e2e8f0; border-radius: 10px; }
        
        /* Two Column Layout */
        .two-columns { display: grid; grid-template-columns: 1fr 1fr; gap: 30px; margin-bottom: 30px; }
        .activity-list { list-style: none; }
        .activity-list li { padding: 12px 0; border-bottom: 1px solid #f1f5f9; display: flex; align-items: center; gap: 12px; }
        .activity-list li:last-child { border-bottom: none; }
        .activity-icon { width: 32px; height: 32px; background: #f1f5f9; border-radius: 50%; display: flex; align-items: center; justify-content: center; }
        .activity-detail { flex: 1; }
        .activity-detail strong { display: block; font-size: 0.85rem; }
        .activity-detail small { font-size: 0.7rem; color: #94a3b8; }

        .modal { display: none; position: fixed; inset: 0; background: rgba(15, 23, 42, 0.7); backdrop-filter: blur(4px); justify-content: center; align-items: center; z-index: 2000; }
        .modal-content { background: white; padding: 30px; border-radius: 20px; width: 400px; text-align: center; }
        
        /* Responsive */
        @media (max-width: 768px) {
            .two-columns { grid-template-columns: 1fr; gap: 20px; }
        }
    </style>
</head>
<body>

<aside class="sidebar">
    <div class="sidebar-brand"><i class="fas fa-shield-halved" style="color:var(--primary)"></i> Admin<span>OS</span></div>
    
    <a href="?view=home" class="nav-link <?= $view=='home'?'active':'' ?>"><i class="fas fa-chart-pie"></i> &nbsp; Dashboard</a>
    <a href="?view=manage_admins" class="nav-link <?= $view=='manage_admins'?'active':'' ?>"><i class="fas fa-user-shield"></i> &nbsp; Manage Admins</a>
    <a href="?view=logs" class="nav-link <?= $view=='logs'?'active':'' ?>"><i class="fas fa-list-ul"></i> &nbsp; Activity Logs</a>
    <a href="?view=archived_admins" class="nav-link <?= $view=='archived_admins'?'active':'' ?>"><i class="fas fa-box-archive"></i> &nbsp; Archived Admins</a>

    <a href="../auth/logout.php" class="nav-link" style="margin-top: 50px; color: #fca5a5;"><i class="fas fa-power-off"></i> &nbsp; Logout</a>
</aside>

<main class="main">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:30px;">
        <h1 style="font-weight:800;"><?= ucwords(str_replace('_', ' ', $view)) ?></h1>
        <div style="font-weight:600; color:var(--text-muted)">Super Admin: <?= htmlspecialchars($_SESSION['name']) ?></div>
    </div>

    <?php if($message || isset($_GET['msg'])): ?>
        <div style="background: #dcfce7; color: #166534; padding: 15px; border-radius: 12px; margin-bottom: 25px; font-weight: 600; border: 1px solid #bbf7d0;">
            <i class="fas fa-check-circle"></i> &nbsp; <?= $message ?: $_GET['msg'] ?>
        </div>
    <?php endif; ?>

    <?php if ($view === 'home'): ?>
        <!-- Row 1: Main Stats Cards (4 cards) -->
        <div class="grid-stats">
            <div class="stat-card">
                <h3><i class="fas fa-user-shield"></i> Active Admins</h3>
                <span class="num"><?= $totalAdmins ?></span>
                <small>Active administrators</small>
            </div>
            <div class="stat-card" style="border-bottom-color:var(--danger)">
                <h3><i class="fas fa-archive"></i> Archived Admins</h3>
                <span class="num"><?= $archivedAdmins ?></span>
                <small>Disabled admin accounts</small>
            </div>
            <div class="stat-card" style="border-bottom-color:var(--warning)">
                <h3><i class="fas fa-history"></i> Total Logs</h3>
                <span class="num"><?= $totalLogs ?></span>
                <small>System activity records</small>
            </div>
            <div class="stat-card" style="border-bottom-color:var(--info)">
                <h3><i class="fas fa-users"></i> Regular Users</h3>
                <span class="num"><?= $totalRegularUsers ?></span>
                <small>Active customers</small>
            </div>
        </div>

        <div class="two-columns">
            <!-- Recent Activities Column -->
            <div class="glass-box">
                <div class="section-header">
                    <h3><i class="fas fa-history"></i> Recent Activities</h3>
                </div>
                <?php if(empty($recentActivities)): ?>
                    <p style="color:#94a3b8; text-align: center; padding: 20px;">No recent activities</p>
                <?php else: ?>
                    <ul class="activity-list">
                        <?php foreach($recentActivities as $activity): ?>
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

            <!-- System Overview Column -->
            <div class="glass-box">
                <div class="section-header">
                    <h3><i class="fas fa-chart-line"></i> System Overview</h3>
                </div>
                <div style="text-align: center; padding: 10px;">
                    <div style="margin-bottom: 20px;">
                        <i class="fas fa-microchip" style="font-size: 3rem; color: var(--primary);"></i>
                        <p style="margin-top: 10px; color: #64748b;">TechStore Inventory Management System</p>
                    </div>
                    <hr style="margin: 20px 0; border-color: #e2e8f0;">
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; text-align: left;">
                        <div>
                            <small style="color: #94a3b8;">Total Admins</small>
                            <strong style="display: block; font-size: 1.2rem;"><?= $totalAdmins ?></strong>
                        </div>
                        <div>
                            <small style="color: #94a3b8;">Archived Admins</small>
                            <strong style="display: block; font-size: 1.2rem; color: var(--danger);"><?= $archivedAdmins ?></strong>
                        </div>
                        <div>
                            <small style="color: #94a3b8;">Regular Users</small>
                            <strong style="display: block; font-size: 1.2rem;"><?= $totalRegularUsers ?></strong>
                        </div>
                        <div>
                            <small style="color: #94a3b8;">Activity Logs</small>
                            <strong style="display: block; font-size: 1.2rem;"><?= $totalLogs ?></strong>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Row 3: Quick Actions -->
        <div class="glass-box">
            <div class="section-header">
                <h3><i class="fas fa-bolt"></i> Quick Actions</h3>
            </div>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
                <a href="?view=manage_admins" class="btn btn-primary" style="justify-content: center; padding: 12px;">
                    <i class="fas fa-user-plus"></i> Add New Admin
                </a>
                <a href="?view=logs" class="btn btn-info" style="justify-content: center; padding: 12px; background: #eff6ff; color: #2563eb;">
                    <i class="fas fa-list-ul"></i> View Activity Logs
                </a>
                <a href="?view=archived_admins" class="btn btn-warning" style="justify-content: center; padding: 12px;">
                    <i class="fas fa-trash-restore"></i> Manage Archived
                </a>
            </div>
        </div>

    <?php elseif ($view === 'manage_admins'): ?>
        <div class="glass-box">
            <h3>Register New Admin</h3><br>
            <form method="POST" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 15px;">
                <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                <input type="text" name="firstname" placeholder="First Name" required>
                <input type="text" name="lastname" placeholder="Last Name" required>
                <input type="email" name="email" placeholder="Email" required>
                <input type="password" name="password" placeholder="Password" required>
                <button type="submit" name="add_admin" class="btn btn-primary" style="height:48px; margin-top:8px;">Create Admin</button>
            </form>
        </div>

        <div class="glass-box">
            <h3>Active Administrators</h3>
            <div style="overflow-x: auto;">
                <table style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr>
                            <th style="text-align: left; padding: 12px; background: #f8fafc;">Name</th>
                            <th style="text-align: left; padding: 12px; background: #f8fafc;">Email</th>
                            <th style="text-align: left; padding: 12px; background: #f8fafc;">Security</th>
                            <th style="text-align: right; padding: 12px; background: #f8fafc;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($admins as $a): ?>
                        <tr>
                            <td style="padding: 15px 12px; border-bottom: 1px solid #f1f5f9; font-weight: 600;"><?= htmlspecialchars($a['firstname'].' '.$a['lastname']) ?></td>
                            <td style="padding: 15px 12px; border-bottom: 1px solid #f1f5f9;"><?= htmlspecialchars($a['email']) ?></td>
                            <td style="padding: 15px 12px; border-bottom: 1px solid #f1f5f9;">
                                <button type="button" class="btn btn-warning openResetModal" data-id="<?= $a['id'] ?>" data-name="<?= htmlspecialchars($a['firstname']) ?>">
                                    <i class="fas fa-key"></i> Reset
                                </button>
                            </td>
                            <td style="padding: 15px 12px; border-bottom: 1px solid #f1f5f9; text-align: right;">
                                <button class="btn btn-danger openArchiveModal" data-id="<?= $a['id'] ?>"><i class="fas fa-archive"></i> Archive</button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

    <?php elseif ($view === 'archived_admins'): ?>
        <div class="glass-box">
            <h3>Archived Administrator Accounts</h3>
            <div style="overflow-x: auto;">
                <table style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr>
                            <th style="text-align: left; padding: 12px; background: #f8fafc;">Name</th>
                            <th style="text-align: left; padding: 12px; background: #f8fafc;">Email</th>
                            <th style="text-align: left; padding: 12px; background: #f8fafc;">Status</th>
                            <th style="text-align: right; padding: 12px; background: #f8fafc;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($archived_list as $al): ?>
                        <tr>
                            <td style="padding: 15px 12px; border-bottom: 1px solid #f1f5f9; font-weight: 600;"><?= htmlspecialchars($al['firstname'].' '.$al['lastname']) ?></td>
                            <td style="padding: 15px 12px; border-bottom: 1px solid #f1f5f9;"><?= htmlspecialchars($al['email']) ?></td>
                            <td style="padding: 15px 12px; border-bottom: 1px solid #f1f5f9;">
                                <span style="background: #fef2f2; color: #dc2626; padding: 4px 12px; border-radius: 20px; font-size: 0.7rem; font-weight: 700;">
                                    <i class="fas fa-ban"></i> ARCHIVED
                                </span>
                            </td>
                            <td style="padding: 15px 12px; border-bottom: 1px solid #f1f5f9; text-align: right;">
                                <button class="btn btn-success openRestoreModal" data-id="<?= $al['id'] ?>">
                                    <i class="fas fa-undo"></i> Restore
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

    <?php elseif ($view === 'logs'): ?>
        <div class="glass-box">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 20px;">
                <h3>Activity Logs</h3>
                <span style="font-size: 0.8rem; color: var(--text-muted);">Showing Page <?= $page ?></span>
            </div>
            
            <div style="overflow-x: auto;">
                <table style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr>
                            <th style="text-align: left; padding: 12px; background: #f8fafc;">Timestamp</th>
                            <th style="text-align: left; padding: 12px; background: #f8fafc;">User</th>
                            <th style="text-align: left; padding: 12px; background: #f8fafc;">Action Details</th>
                            <th style="text-align: left; padding: 12px; background: #f8fafc;">Category</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($logs)): ?>
                            <tr><td colspan="4" style="text-align:center; padding: 40px;">No activity logs found on this page.<?php echo '</td>'; ?> noi
                        <?php else: ?>
                            <?php foreach($logs as $l): ?>
                            <tr>
                                <td style="padding: 15px 12px; border-bottom: 1px solid #f1f5f9; font-size: 0.85rem;">
                                    <i class="far fa-clock"></i> <?= date('M d, Y • h:i A', strtotime($l['created_at'])) ?>
                                </td>
                                <td style="padding: 15px 12px; border-bottom: 1px solid #f1f5f9;">
                                    <strong><?= htmlspecialchars(($l['firstname'] ?? 'Sys') . ' ' . ($l['lastname'] ?? '')) ?></strong>
                                </td>
                                <td style="padding: 15px 12px; border-bottom: 1px solid #f1f5f9;"><?= htmlspecialchars($l['action_details']) ?></td>
                                <td style="padding: 15px 12px; border-bottom: 1px solid #f1f5f9;">
                                    <span style="font-size:0.7rem; padding: 4px 10px; border-radius: 20px; font-weight:800; 
                                        background: <?= $l['action_type']=='Security' ? '#fff1f2' : '#eff6ff' ?>; 
                                        color: <?= $l['action_type']=='Security' ? '#be123c' : '#1d4ed8' ?>;">
                                        <?= strtoupper($l['action_type']) ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <?php
                $totalLogsCount = $pdo->query("SELECT COUNT(*) FROM activity_logs")->fetchColumn();
                $totalPages = ceil($totalLogsCount / $limit);
            ?>
            
            <?php if ($totalPages > 1): ?>
                <div style="margin-top: 30px; display: flex; justify-content: center; gap: 8px;">
                    <?php if($page > 1): ?>
                        <a href="?view=logs&page=<?= $page - 1 ?>" class="btn btn-primary" style="padding: 8px 12px;"><i class="fas fa-chevron-left"></i></a>
                    <?php endif; ?>

                    <?php for($p = 1; $p <= $totalPages; $p++): 
                        if ($p >= $page - 2 && $p <= $page + 2):
                    ?>
                        <a href="?view=logs&page=<?= $p ?>" 
                        style="display:inline-block; width: 35px; height: 35px; line-height: 35px; text-align: center; border-radius:8px; 
                                background: <?= $p==$page ? 'var(--primary)' : '#f1f5f9' ?>; 
                                color: <?= $p==$page ? 'white' : 'var(--text-main)' ?>; 
                                text-decoration:none; font-weight:700;">
                            <?= $p ?>
                        </a>
                    <?php 
                        endif;
                    endfor; 
                    ?>

                    <?php if($page < $totalPages): ?>
                        <a href="?view=logs&page=<?= $page + 1 ?>" class="btn btn-primary" style="padding: 8px 12px;"><i class="fas fa-chevron-right"></i></a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</main>

<!-- RESET MODAL -->
<div id="resetModal" class="modal">
    <div class="modal-content">
        <h3>Reset Password</h3>
        <p id="resetAdminName" style="margin:10px 0; color:var(--text-muted);">
            New password for <strong></strong>
        </p>
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

<!-- ARCHIVE MODAL -->
<div id="archiveModal" class="modal">
    <div class="modal-content">
        <i class="fas fa-exclamation-triangle" style="font-size:2rem; color:var(--danger)"></i>
        <h3 style="margin-top:15px;">Archive Admin?</h3>
        <p style="margin:10px 0; color:var(--text-muted);">This user will lose all system access.</p>
        <div style="display:flex; gap:10px; justify-content:center; margin-top:20px;">
            <a id="archiveConfirmBtn" href="#" class="btn btn-danger">Yes, Archive</a>
            <button type="button" class="btn btn-primary" onclick="closeModals()">Cancel</button>
        </div>
    </div>
</div>

<!-- RESTORE MODAL -->
<div id="restoreModal" class="modal">
    <div class="modal-content">
        <h3>Restore Account?</h3>
        <p style="margin:10px 0; color:var(--text-muted);">This will reactivate admin permissions.</p>
        <div style="display:flex; gap:10px; justify-content:center; margin-top:20px;">
            <a id="restoreConfirmBtn" href="#" class="btn btn-success">Yes, Restore</a>
            <button type="button" class="btn btn-primary" onclick="closeModals()">Cancel</button>
        </div>
    </div>
</div>

<script>
    function closeModals() {
        document.querySelectorAll('.modal').forEach(m => m.style.display = 'none');
    }
    
    document.querySelectorAll(".openResetModal").forEach(btn => {
        btn.onclick = () => {
            document.getElementById("resetUserId").value = btn.dataset.id;
            document.getElementById("resetAdminName").querySelector('strong').innerText = btn.dataset.name;
            document.getElementById("resetModal").style.display = "flex";
        }
    });
    
    document.querySelectorAll(".openArchiveModal").forEach(btn => {
        btn.onclick = () => {
            document.getElementById("archiveConfirmBtn").href = "?archive=" + btn.dataset.id;
            document.getElementById("archiveModal").style.display = "flex";
        }
    });

    document.querySelectorAll(".openRestoreModal").forEach(btn => {
        btn.onclick = () => {
            document.getElementById("restoreConfirmBtn").href = "?restore=" + btn.dataset.id;
            document.getElementById("restoreModal").style.display = "flex";
        }
    });

    window.onclick = (event) => { 
        if (event.target.classList.contains('modal')) closeModals(); 
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
