<?php
require 'includes/db.php';

$username = 'admin';
$password = 'admin123';

echo "<h3>Login Diagnostic Test</h3>";

// 1. Check if user exists
$stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
$stmt->execute([$username]);
$user = $stmt->fetch();

if (!$user) {
    echo "<p style='color:red;'>❌ Error: User '$username' not found in the database!</p>";
    exit();
} else {
    echo "<p style='color:green;'>✅ User found! ID: {$user['id']}, Role: {$user['role']}</p>";
}

// 2. Check password column length / hash truncation
echo "<p>Stored Hash in DB: <code>" . htmlspecialchars($user['password']) . "</code> (Length: " . strlen($user['password']) . ")</p>";

if (strlen($user['password']) < 60) {
    echo "<p style='color:red;'>⚠️ WARNING: Your password hash is too short! It was likely truncated because your database column is not VARCHAR(255).</p>";
}

// 3. Test password verification
if (password_verify($password, $user['password'])) {
    echo "<p style='color:green; font-weight:bold;'>🎉 Success! password_verify() works perfectly. The password matches!</p>";
} else {
    echo "<p style='color:red; font-weight:bold;'>❌ Failure: password_verify() failed. The hash does not match the password 'admin123'.</p>";
}
?>