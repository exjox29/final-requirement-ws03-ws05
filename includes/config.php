<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$host = 'localhost';
$db   = 'inventory_db';
$user = 'root';
$pass = '@Ambulance_System2026'; 
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

define('BASE_PATH', dirname(__DIR__));
define('BASE_URL', 'http://localhost/final-requirement-itws03-itws05');

define('UPLOAD_PATH', BASE_PATH . '/uploads/');
define('UPLOAD_URL', BASE_URL . '/uploads/');


function generate_csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf_token($token) {
    if (isset($_SESSION['csrf_token']) && $token && hash_equals($_SESSION['csrf_token'], $token)) {
        return true;
    }
    return false;
}

function logActivity($pdo, $user_id, $details, $type) {
    try {
        $stmt = $pdo->prepare("INSERT INTO activity_logs (user_id, action_details, action_type) VALUES (?, ?, ?)");
        $stmt->execute([$user_id, $details, $type]);
    } catch (Exception $e) {
        error_log("Activity log failed: " . $e->getMessage());
    }
}

function redirect($path) {
    header("Location: " . BASE_URL . $path);
    exit;
}


function setRememberMe($user_id, $role, $name) {
    $token = bin2hex(random_bytes(32));
    $expiry = time() + (86400 * 30); // 30 days
    
    global $pdo;
    $stmt = $pdo->prepare("INSERT INTO user_tokens (user_id, token, expiry) VALUES (?, ?, ?)");
    $stmt->execute([$user_id, $token, date('Y-m-d H:i:s', $expiry)]);
    
    setcookie('remember_me', $token, $expiry, '/');
    setcookie('remember_user_id', $user_id, $expiry, '/');
    setcookie('remember_role', $role, $expiry, '/');
    setcookie('remember_name', $name, $expiry, '/');
}

function checkRememberMe() {
    if (isset($_SESSION['user_id'])) {
        return true;
    }
    
    if (!isset($_COOKIE['remember_me']) || !isset($_COOKIE['remember_user_id'])) {
        return false;
    }
    
    $token = $_COOKIE['remember_me'];
    $user_id = $_COOKIE['remember_user_id'];
    
    global $pdo;
    
    $stmt = $pdo->prepare("SELECT * FROM user_tokens WHERE user_id = ? AND token = ? AND expiry > NOW()");
    $stmt->execute([$user_id, $token]);
    $token_data = $stmt->fetch();
    
    if ($token_data) {
        $stmt = $pdo->prepare("SELECT users.*, roles.role_name FROM users 
                               JOIN roles ON users.role_id = roles.id 
                               WHERE users.id = ? AND users.status = 'active'");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();
        
        if ($user) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['role']    = $user['role_name'];
            $_SESSION['name']    = $user['firstname'];
            return true;
        }
    }
    
    return false;
}

function clearRememberMe() {
    if (isset($_COOKIE['remember_me'])) {
        global $pdo;
        
        $stmt = $pdo->prepare("DELETE FROM user_tokens WHERE token = ?");
        $stmt->execute([$_COOKIE['remember_me']]);
        
        setcookie('remember_me', '', time() - 3600, '/');
        setcookie('remember_user_id', '', time() - 3600, '/');
        setcookie('remember_role', '', time() - 3600, '/');
        setcookie('remember_name', '', time() - 3600, '/');
    }
}

function checkRoleAccess($required_role) {
    checkRememberMe();
    
    if (!isset($_SESSION['role']) || $_SESSION['role'] !== $required_role) {
        redirect('/auth/login.php');
        exit;
    }
}

function isLoggedIn() {
    checkRememberMe();
    return isset($_SESSION['user_id']);
}

function getCurrentUserId() {
    return $_SESSION['user_id'] ?? null;
}

function getCurrentUserRole() {
    return $_SESSION['role'] ?? null;
}
?>
