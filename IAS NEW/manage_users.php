<?php
require 'includes/db.php';
require 'includes/header.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo "<div class='glass-card' style='text-align: center;'><h2>Access Denied</h2><a href='dashboard.php' class='btn'>Dashboard</a></div>";
    require 'includes/footer.php';
    exit();
}

if (isset($_GET['delete_id']) && $_GET['delete_id'] != $_SESSION['user_id']) {
    $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
    $stmt->execute([$_GET['delete_id']]);
    header("Location: manage_users.php");
    exit();
}

$users = $pdo->query("SELECT id, username, email, role, created_at FROM users ORDER BY id DESC")->fetchAll();
?>

<div class="glass-card" style="max-width: 900px;">
    <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid rgba(255,255,255,0.4); padding-bottom: 15px; margin-bottom: 20px;">
        <h2 style="margin: 0; text-align: left;">👥 User Accounts Management</h2>
        <a href="admin_dashboard.php" class="btn" style="max-width: 150px; margin: 0; background: #7f8c8d;">← Admin Panel</a>
    </div>

    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Username</th>
                <th>Email</th>
                <th>Role</th>
                <th>Registered</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($users as $u): 
                $bClass = ($u['role'] === 'admin') ? 'badge-admin' : 'badge-user';
            ?>
            <tr>
                <td>#<?php echo $u['id']; ?></td>
                <td><strong><?php echo htmlspecialchars($u['username'], ENT_QUOTES, 'UTF-8'); ?></strong></td>
                <td><?php echo htmlspecialchars($u['email'], ENT_QUOTES, 'UTF-8'); ?></td>
                <td><span class="badge <?php echo $bClass; ?>"><?php echo ucfirst($u['role']); ?></span></td>
                <td><?php echo $u['created_at']; ?></td>
                <td>
                    <?php if ($u['id'] != $_SESSION['user_id']): ?>
                        <a href="manage_users.php?delete_id=<?php echo $u['id']; ?>" onclick="return confirm('Delete this user?');" style="color: #e74c3c;">Delete</a>
                    <?php else: ?>
                        <span style="color: #888; font-style: italic; font-size: 12px;">Active User</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div style="margin-top: 30px; text-align: center;">
        <a href="admin_dashboard.php" class="btn" style="max-width: 200px;">Back to Admin Panel</a>
    </div>
</div>

<?php require 'includes/footer.php'; ?>