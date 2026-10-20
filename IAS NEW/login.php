<?php
require 'includes/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit();
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        session_regenerate_id(true);
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];

        header("Location: dashboard.php");
        exit();
    } else {
        $error = "Invalid username or password. Please verify your institutional credentials.";
    }
}

require 'includes/header.php';
?>

<style>
    body { background: linear-gradient(135deg, #f0f7f4 0%, #e1ede6 100%); }
    .usls-auth-card {
        background: rgba(255, 255, 255, 0.85);
        backdrop-filter: blur(16px);
        border-radius: 20px;
        border: 1px solid rgba(0, 102, 51, 0.15);
        box-shadow: 0 15px 35px rgba(0, 102, 51, 0.08);
        padding: 40px;
        width: 100%;
        max-width: 450px;
    }
    .usls-title { color: #006633; font-weight: 800; text-align: center; margin-bottom: 5px; }
    .btn-usls-green { background: #006633 !important; box-shadow: 0 4px 15px rgba(0, 102, 51, 0.3); }
    .btn-usls-green:hover { background: #004d26 !important; }
</style>

<div class="usls-auth-card">
    <div style="text-align: center; margin-bottom: 25px;">
        <span style="background: rgba(0,102,51,0.1); color: #006633; padding: 5px 12px; border-radius: 20px; font-size: 11px; font-weight: 700; text-transform: uppercase;">Secure Authentication</span>
        <h2 class="usls-title" style="margin-top: 10px;">Sign In Portal</h2>
        <p style="color: #666; font-size: 13px; margin: 0;">USLS Information Technology Services</p>
    </div>

    <?php if (!empty($error)) echo "<div class='error'>$error</div>"; ?>
    
    <form method="POST">
        <label style="color: #006633;">Username / Student ID</label>
        <input type="text" name="username" placeholder="Enter your username" required style="border-color: rgba(0,102,51,0.2);">
        
        <label style="color: #006633;">Password</label>
        <input type="password" name="password" placeholder="Enter your secure password" required style="border-color: rgba(0,102,51,0.2);">
        
        <button type="submit" class="btn btn-usls-green" style="margin-top: 10px;">Authenticate Session</button>
    </form>
    
    <p style="margin-top: 25px; font-size: 13px;">Don't have an account? <a href="register.php" style="color: #006633; font-weight: 700;">Register here</a></p>
</div>

<?php require 'includes/footer.php'; ?>