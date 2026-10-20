<?php
require 'includes/db.php';

$username = 'admin';
$email = 'admin@usls.edu.ph';
$password = 'admin123';
$role = 'admin';

// 1. Delete any existing user/admin with this username or email to avoid conflicts
$stmt = $pdo->prepare("DELETE FROM users WHERE username = ? OR email = ?");
$stmt->execute([$username, $email]);

// 2. Generate a fresh, valid password hash using your local PHP server environment
$hashed_password = password_hash($password, PASSWORD_DEFAULT);

// 3. Insert the fresh admin account
$stmt = $pdo->prepare("INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, ?)");
$stmt->execute([$username, $email, $hashed_password, $role]);

echo "<div style='font-family: Arial; text-align: center; margin-top: 50px;'>
        <h2 style='color: #006633;'>Admin Account Setup Successful!</h2>
        <p>Your admin credentials have been freshly generated and saved to the database.</p>
        <p><strong>Username:</strong> admin<br><strong>Password:</strong> admin123</p>
        <br><a href='login.php' style='padding: 12px 25px; background: #006633; color: white; text-decoration: none; border-radius: 8px; font-weight: bold;'>Proceed to Login Portal</a>
      </div>";
?>