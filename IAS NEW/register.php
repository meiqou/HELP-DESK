<?php
require 'includes/db.php';
require 'includes/header.php';

$message = '';
$msgClass = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    if (empty($username) || empty($email) || empty($password)) {
        $message = "All registration fields are required.";
        $msgClass = "error";
    } else {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO users (username, email, password) VALUES (?, ?, ?)");
        
        try {
            $stmt->execute([$username, $email, $hashed_password]);
            $message = "Account created successfully! <a href='login.php' style='color: #006633; font-weight: 700;'>Sign in here</a>";
            $msgClass = "success";
        } catch (\PDOException $e) {
            $message = "Registration Error: Username or email already registered in the system.";
            $msgClass = "error";
        }
    }
}
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
</style>

<div class="usls-auth-card">
    <div style="text-align: center; margin-bottom: 25px;">
        <span style="background: rgba(0,102,51,0.1); color: #006633; padding: 5px 12px; border-radius: 20px; font-size: 11px; font-weight: 700; text-transform: uppercase;">New User Registration</span>
        <h2 class="usls-title" style="margin-top: 10px;">Create Portal Account</h2>
        <p style="color: #666; font-size: 13px; margin: 0;">For USLS Students, Faculty, and Staff</p>
    </div>

    <?php if (!empty($message)) echo "<div class='$msgClass'>$message</div>"; ?>
    
    <form method="POST">
        <label style="color: #006633;">Username</label>
        <input type="text" name="username" placeholder="Choose a username" required style="border-color: rgba(0,102,51,0.2);">
        
        <label style="color: #006633;">Institutional Email</label>
        <input type="email" name="email" placeholder="name@usls.edu.ph" required style="border-color: rgba(0,102,51,0.2);">
        
        <label style="color: #006633;">Password</label>
        <input type="password" name="password" placeholder="Create a secure password" required style="border-color: rgba(0,102,51,0.2);">
        
        <button type="submit" class="btn" style="background: #006633; margin-top: 10px;">Register Account</button>
    </form>
    
    <p style="margin-top: 25px; font-size: 13px;">Already have an account? <a href="login.php" style="color: #006633; font-weight: 700;">Sign In</a></p>
</div>

<?php require 'includes/footer.php'; ?>