<?php
require dirname(__DIR__) . '/includes/config.php';

$message = "";
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'])) {
        die("CSRF token validation failed!");
    }
    $fn = $_POST['firstname'];
    $ln = $_POST['lastname'];
    $em = $_POST['email'];
    $pw = password_hash($_POST['password'], PASSWORD_BCRYPT);
    $role_id = 3; // Regular User ID

    try {
        $stmt = $pdo->prepare("INSERT INTO users (firstname, lastname, email, password, role_id) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$fn, $ln, $em, $pw, $role_id]);
        $message = "Registration successful! <a href='login.php'>Login here</a>";
    } catch (Exception $e) {
        $message = "Error: Email is already registered.";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Register - Gadget Store</title>
    <style>
        body { font-family: sans-serif; display: flex; justify-content: center; background: #f4f4f4; padding-top: 50px; }
        .reg-card { background: white; padding: 20px; border-radius: 8px; width: 350px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        input { width: 100%; padding: 10px; margin: 8px 0; box-sizing: border-box; }
        button { width: 100%; padding: 10px; background: #28a745; color: white; border: none; cursor: pointer; }
    </style>
</head>
<body>
    <div class="reg-card">
        <h2>Create Account</h2>
        <p style="color:green"><?= $message ?></p>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
            <input type="text" name="firstname" placeholder="First Name" required>
            <input type="text" name="lastname" placeholder="Last Name" required>
            <input type="email" name="email" placeholder="Email (Username)" required>
            <input type="password" name="password" placeholder="Password" required>
            <button type="submit">Register</button>
        </form>
        <p><a href="login.php">Back to Login</a></p>
    </div>
</body>
</html>