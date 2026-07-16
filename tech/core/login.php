<?php
require_once __DIR__ . '/database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. Auto-Redirect if already logged in and has access
if (isset($_SESSION['authenticated']) && $_SESSION['authenticated'] === true) {
    $role = $_SESSION['role'] ?? '';
    if ($role === 'Admin' || strpos($role, 'Technician') !== false) {
        header("Location: ../index.php");
        exit();
    }
}

$error = null;

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['username']) && isset($_POST['password'])) {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    
    try {
        $conn_auth = Database::users();
        $stmt = $conn_auth->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user) {
            $verified = false;
            // Check PPP if sequence key exists
            if (!empty($user['ppp_sequence_key'])) {
                $verified = password_verify($password . $user['ppp_sequence_key'], $user['password']);
            }
            // Fallback to standard password
            if (!$verified) {
                $verified = password_verify($password, $user['password']);
            }
            
            if ($verified) {
                session_regenerate_id(true);
                $_SESSION['authenticated'] = true;
                $_SESSION['username'] = $user['username'];
                $_SESSION['display_name'] = $user['display_name'] ?: $user['username'];
                $_SESSION['role'] = $user['role'] ?? 'Operator';
                
                // Authorize Tech Access
                if ($_SESSION['role'] === 'Admin' || strpos($_SESSION['role'], 'Technician') !== false) {
                    header("Location: ../index.php");
                    exit();
                } else {
                    $error = "Access Denied: You do not have Technician privileges.";
                    session_destroy();
                }
            } else {
                $error = "Invalid username or password";
            }
        } else {
            $error = "Invalid username or password";
        }
    } catch (PDOException $e) {
        $error = "System Error: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Technician Login | System</title>
    <!-- Borrowing styles from orders to keep UI consistent -->
    <link rel="stylesheet" href="../../orders/assets/styles/style.css">
    <link rel="stylesheet" href="../../orders/assets/styles/login.css">
    <link rel="icon" type="image/png" href="../../orders/assets/icon/smart-home-sensor-wifi-black-outline-25276_1024.png">
</head>
<body class="login-body">
    <div class="login-card">
        <div class="login-logo">
            <img src="../../orders/assets/icon/smart-home-sensor-wifi-black-outline-25276_1024.png" alt="Logo">
        </div>
        <div class="login-header">
            <h1>Technician Portal</h1>
            <p>Secure login for hardware testing and inventory.</p>
        </div>

        <?php if ($error): ?>
            <div class="login-error" style="background: #fee2e2; color: #991b1b; padding: 12px; border-radius: 8px; margin-bottom: 20px; text-align: center; font-size: 0.9rem; font-weight: bold; border: 1px solid #f87171;">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="" id="login-form">
            <div class="login-form-group">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" class="login-input" placeholder="tech" required value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
            </div>
            <div class="login-form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" class="login-input" placeholder="••••••••" required>
            </div>
            <button type="submit" class="btn-login">🔒 Sign In</button>
        </form>
        <div class="login-footer" style="text-align: center; margin-top: 20px;">
            <small>&copy; <?= date('Y') ?> System | Technician Operations</small>
        </div>
    </div>
</body>
</html>
