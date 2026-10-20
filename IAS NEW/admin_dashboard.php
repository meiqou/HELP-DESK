<?php
require 'includes/db.php';
require 'includes/header.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo "<div class='glass-card' style='text-align: center; max-width: 500px;'>
            <h2 style='color: #c0392b;'>Access Denied</h2>
            <p>Admin clearance required to view this institutional hub.</p>
            <a href='dashboard.php' class='btn' style='max-width: 200px; margin: 20px auto 0;'>Return to Dashboard</a>
          </div>";
    require 'includes/footer.php';
    exit();
}

$current_username = htmlspecialchars($_SESSION['username'], ENT_QUOTES, 'UTF-8');
$totalUsers = $pdo->query("SELECT COUNT(*) as total FROM users")->fetch()['total'];
$totalTickets = $pdo->query("SELECT COUNT(*) as total FROM tickets")->fetch()['total'];
?>

<style>
    body { background: linear-gradient(135deg, #f0f7f4 0%, #e1ede6 100%); }
    .usls-card {
        background: rgba(255, 255, 255, 0.85);
        backdrop-filter: blur(16px);
        border-radius: 20px;
        border: 1px solid rgba(0, 102, 51, 0.15);
        box-shadow: 0 15px 35px rgba(0, 102, 51, 0.08);
        padding: 40px;
        width: 100%;
        max-width: 850px;
    }
</style>

<div class="usls-card">
    <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid rgba(0, 102, 51, 0.12); padding-bottom: 15px; margin-bottom: 25px;">
        <div>
            <h2 style="margin: 0; text-align: left; color: #006633; font-size: 1.8rem;">👑 IT Admin Command Hub</h2>
            <p style="margin: 5px 0 0 0; color: #555; font-size: 13px;">Authorized Administrator: <strong><?php echo $current_username; ?></strong></p>
        </div>
        <div>
            <span class="badge badge-admin">Admin Privileges Active</span>
        </div>
    </div>

    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 20px; margin-bottom: 30px;">
        <div style="background: rgba(255, 255, 255, 0.7); padding: 20px; border-radius: 14px; border: 1px solid rgba(0, 102, 51, 0.15); text-align: center;">
            <span style="font-size: 13px; color: #555; font-weight: 600;">Total Registered Accounts</span>
            <h3 style="margin: 10px 0 0 0; font-size: 28px; color: #006633;"><?php echo $totalUsers; ?></h3>
        </div>
        <div style="background: rgba(255, 255, 255, 0.7); padding: 20px; border-radius: 14px; border: 1px solid rgba(0, 102, 51, 0.15); text-align: center;">
            <span style="font-size: 13px; color: #555; font-weight: 600;">Total Helpdesk Tickets</span>
            <h3 style="margin: 10px 0 0 0; font-size: 28px; color: #006633;"><?php echo $totalTickets; ?></h3>
        </div>
    </div>

    <div style="display: flex; flex-direction: column; gap: 15px;">
        <div style="background: rgba(0, 102, 51, 0.05); border: 1px solid rgba(0, 102, 51, 0.2); padding: 20px; border-radius: 14px; display: flex; justify-content: space-between; align-items: center;">
            <div>
                <h4 style="margin: 0 0 5px 0; color: #006633; font-size: 16px;">Manage Support Tickets</h4>
                <p style="margin: 0; font-size: 13px; color: #555; text-align: left;">Review student/faculty service issues and update resolution statuses.</p>
            </div>
            <a href="admin_tickets.php" class="btn" style="background: #006633; max-width: 160px; margin: 0;">View Tickets</a>
        </div>

        <div style="background: rgba(212, 160, 23, 0.08); border: 1px solid rgba(212, 160, 23, 0.25); padding: 20px; border-radius: 14px; display: flex; justify-content: space-between; align-items: center;">
            <div>
                <h4 style="margin: 0 0 5px 0; color: #b78103; font-size: 16px;">User Accounts Audit</h4>
                <p style="margin: 0; font-size: 13px; color: #555; text-align: left;">Audit user roles, inspect credentials, or remove accounts.</p>
            </div>
            <a href="manage_users.php" class="btn" style="background: #b78103; max-width: 160px; margin: 0;">Manage Users</a>
        </div>
    </div>

    <div style="margin-top: 35px; text-align: center;">
        <a href="dashboard.php" class="btn" style="background: #006633; max-width: 200px; display: inline-block; margin-right: 10px;">User Workspace</a>
        <a href="logout.php" class="btn btn-danger" style="max-width: 180px; display: inline-block;">Log Out</a>
    </div>
</div>

<?php require 'includes/footer.php'; ?>