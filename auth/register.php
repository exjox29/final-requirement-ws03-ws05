<?php
require dirname(__DIR__) . '/includes/config.php';

$message = "";
$error = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'])) {
        die("CSRF token validation failed!");
    }
    $fn = trim($_POST['firstname']);
    $ln = trim($_POST['lastname']);
    $em = trim($_POST['email']);
    $pw = password_hash($_POST['password'], PASSWORD_BCRYPT);
    $role_id = 3; // Regular User ID

    // Basic validation
    if (empty($fn) || empty($ln) || empty($em) || empty($_POST['password'])) {
        $error = "Please fill in all fields.";
    } elseif (!filter_var($em, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } elseif (strlen($_POST['password']) < 8) {
        $error = "Password must be at least 8 characters.";
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO users (firstname, lastname, email, password, role_id, status) VALUES (?, ?, ?, ?, ?, 'active')");
            $stmt->execute([$fn, $ln, $em, $pw, $role_id]);
            $message = "Registration successful!";
            // Clear form after successful registration
            $fn = $ln = $em = "";
        } catch (Exception $e) {
            $error = "Email address is already registered.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - TechStore Inventory</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        :root {
            --primary: #2563eb;
            --primary-hover: #1d4ed8;
            --dark: #0f172a;
            --slate: #64748b;
            --white: #ffffff;
            --error-bg: #fef2f2;
            --error-text: #dc2626;
            --success-bg: #ecfdf5;
            --success-text: #047857;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: radial-gradient(circle at top left, #1e293b, #0f172a);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--dark);
            padding: 20px;
        }

        .register-container {
            width: 100%;
            max-width: 500px;
        }

        .register-card {
            background: rgba(255, 255, 255, 0.98);
            padding: 40px;
            border-radius: 24px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .brand-section {
            text-align: center;
            margin-bottom: 30px;
        }

        .brand-logo {
            width: 60px;
            height: 60px;
            background: var(--primary);
            color: white;
            border-radius: 15px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 1.8rem;
            margin-bottom: 15px;
            box-shadow: 0 10px 15px -3px rgba(37, 99, 235, 0.3);
        }

        h2 {
            font-weight: 800;
            font-size: 1.6rem;
            color: var(--dark);
            letter-spacing: -0.5px;
        }

        p.subtitle {
            color: var(--slate);
            font-size: 0.9rem;
            margin-top: 5px;
        }

        .error-box {
            background: var(--error-bg);
            color: var(--error-text);
            padding: 12px;
            border-radius: 12px;
            font-size: 0.85rem;
            margin-bottom: 20px;
            border: 1px solid #fee2e2;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .success-box {
            background: var(--success-bg);
            color: var(--success-text);
            padding: 12px;
            border-radius: 12px;
            font-size: 0.85rem;
            margin-bottom: 20px;
            border: 1px solid #a7f3d0;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .success-box a {
            color: var(--success-text);
            font-weight: 700;
            text-decoration: underline;
        }

        .form-group {
            margin-bottom: 20px;
            position: relative;
        }

        label {
            display: block;
            font-size: 0.85rem;
            font-weight: 600;
            margin-bottom: 8px;
            color: #475569;
        }

        input {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            font-size: 1rem;
            font-family: inherit;
            transition: all 0.3s ease;
            outline: none;
            background: #f8fafc;
        }

        input:focus {
            border-color: var(--primary);
            background: white;
            box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.1);
        }

        .name-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }

        button {
            width: 100%;
            padding: 14px;
            background: var(--primary);
            color: white;
            border: none;
            border-radius: 12px;
            font-weight: 700;
            font-size: 1rem;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            margin-top: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        button:hover {
            background: var(--primary-hover);
            transform: translateY(-2px);
            box-shadow: 0 10px 15px -3px rgba(37, 99, 235, 0.3);
        }

        button:active {
            transform: translateY(0);
        }

        .login-link {
            text-align: center;
            margin-top: 20px;
            padding-top: 15px;
            border-top: 1px solid #e2e8f0;
        }

        .login-link p {
            color: #64748b;
            font-size: 0.85rem;
        }

        .login-link a {
            color: var(--primary);
            text-decoration: none;
            font-weight: 600;
            transition: 0.2s;
        }

        .login-link a:hover {
            text-decoration: underline;
        }

        /* Password hint */
        .password-hint {
            font-size: 0.7rem;
            color: #94a3b8;
            margin-top: 5px;
        }

        .password-hint i {
            margin-right: 4px;
        }
    </style>
</head>
<body>

<div class="register-container">
    <div class="register-card">
        <div class="brand-section">
            <div class="brand-logo">
                <i class="fa-solid fa-microchip"></i>
            </div>
            <h2>Create Account</h2>
            <p class="subtitle">Join TechStore today</p>
        </div>

        <?php if($error): ?>
            <div class="error-box">
                <i class="fa-solid fa-circle-exclamation"></i>
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <?php if($message): ?>
            <div class="success-box">
                <i class="fa-solid fa-circle-check"></i>
                <?= htmlspecialchars($message) ?> <a href="login.php">Login here</a>
            </div>
        <?php endif; ?>

        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
            
            <div class="name-row">
                <div class="form-group">
                    <label for="firstname">First Name</label>
                    <input type="text" id="firstname" name="firstname" placeholder="Enter your first name" value="<?= htmlspecialchars($fn ?? '') ?>" required>
                </div>
                <div class="form-group">
                    <label for="lastname">Last Name</label>
                    <input type="text" id="lastname" name="lastname" placeholder="Enter your last name" value="<?= htmlspecialchars($ln ?? '') ?>" required>
                </div>
            </div>
            
            <div class="form-group">
                <label for="email">Email Address</label>
                <input type="email" id="email" name="email" placeholder="e.g. customer@techstore.com" value="<?= htmlspecialchars($em ?? '') ?>" required>
            </div>
            
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" placeholder="Create a password (min. 8 characters)" required>
                <div class="password-hint">
                    <i class="fa-solid fa-info-circle"></i> Password must be at least 8 characters
                </div>
            </div>
            
            <button type="submit">
                <i class="fa-solid fa-user-plus"></i>
                <span>Create Account</span>
            </button>
        </form>

        <div class="login-link">
            <p>Already have an account? <a href="login.php">Login here</a></p>
        </div>
    </div>
</div>

</body>
</html>
