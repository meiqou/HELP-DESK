<?php
require 'includes/db.php';

$username = 'admin';
$email = 'admin@secureapp.com';
$password = 'admin123';
$role = 'admin';

// Securely hash the password
$hashed_password = password_hash($password, PASSWORD_DEFAULT);

try {
    // Delete existing admin user if they already exist to avoid conflicts
    $stmt = $pdo->prepare("DELETE FROM users WHERE username = ? OR email = ?");
    $stmt->execute([$username, $email]);

    // Insert the fresh admin account
    $stmt = $pdo->prepare("INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, ?)");
    $stmt->execute([$username, $email, $hashed_password, $role]);
    
    echo "<div style='font-family: Arial; text-align: center; margin-top: 50px;'>
            <h2 style='color: #27ae60;'>Admin Account Created Successfully!</h2>
            <p>You can now log in using these credentials:</p>
            <p><strong>Username:</strong> admin<br><strong>Password:</strong> admin123</p>
            <br><a href='login.php' style='padding: 10px 20px; background: #3498db; color: white; text-decoration: none; border-radius: 6px;'>Go to Login</a>
          </div>";

} catch (\PDOException $e) {
    echo "<div style='font-family: Arial; text-align: center; margin-top: 50px;'>
            <h2 style='color: #c0392b;'>Error:</h2>
            <p>" . $e->getMessage() . "</p>
          </div>";
}
?>