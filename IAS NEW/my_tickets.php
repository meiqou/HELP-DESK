<?php
require 'includes/db.php';
require 'includes/header.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$current_user_id = $_SESSION['user_id'];
$current_username = htmlspecialchars($_SESSION['username'], ENT_QUOTES, 'UTF-8');

if (isset($_GET['delete_id'])) {
    $ticket_id = $_GET['delete_id'];
    $stmt = $pdo->prepare("DELETE FROM tickets WHERE id = ? AND user_id = ?");
    $stmt->execute([$ticket_id, $current_user_id]);
    header("Location: my_tickets.php");
    exit();
}

$stmt = $pdo->prepare("SELECT * FROM tickets WHERE user_id = ? ORDER BY created_at DESC");
$stmt->execute([$current_user_id]);
$tickets = $stmt->fetchAll();
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
        max-width: 900px;
    }
</style>

<div class="usls-card">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; border-bottom: 2px solid rgba(0, 102, 51, 0.12); padding-bottom: 15px;">
        <div>
            <h2 style="margin: 0; text-align: left; color: #006633; font-size: 1.8rem;">📋 My Tickets History</h2>
            <p style="margin: 5px 0 0 0; color: #555; font-size: 13px;">Historical log of support requests filed by <strong><?php echo $current_username; ?></strong></p>
        </div>
        <a href="dashboard.php" class="btn" style="background: #006633; max-width: 160px; margin: 0; padding: 10px;">+ File New Ticket</a>
    </div>

    <?php if (empty($tickets)): ?>
        <div style="text-align: center; padding: 40px; background: rgba(0,102,51,0.03); border-radius: 14px; border: 1px dashed rgba(0,102,51,0.2);">
            <p style="color: #666; font-style: italic; margin-bottom: 15px;">You haven't filed any support requests yet.</p>
            <a href="dashboard.php" class="btn" style="background: #006633; max-width: 200px; margin: 0 auto;">File Your First Ticket</a>
        </div>
    <?php else: ?>
        <div style="display: flex; flex-direction: column; gap: 15px;">
            <?php foreach ($tickets as $ticket): 
                $statusColor = "#b78103"; // Open (Gold)
                if ($ticket['status'] === 'In Progress') $statusColor = "#2980b9"; // Blue
                if ($ticket['status'] === 'Resolved') $statusColor = "#006633"; // Green
            ?>
                <div style="background: rgba(255, 255, 255, 0.7); padding: 20px; border-radius: 14px; border: 1px solid rgba(0, 102, 51, 0.15); text-align: left;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                        <span style="font-size: 12px; font-weight: 700; background: rgba(0,102,51,0.1); color: #006633; padding: 3px 10px; border-radius: 6px;"><?php echo htmlspecialchars($ticket['category'], ENT_QUOTES, 'UTF-8'); ?></span>
                        <span style="font-size: 12px; font-weight: 700; background: <?php echo $statusColor; ?>15; color: <?php echo $statusColor; ?>; padding: 3px 12px; border-radius: 20px;">● <?php echo $ticket['status']; ?></span>
                    </div>
                    
                    <h4 style="margin: 8px 0; font-size: 16px; color: #006633;"><?php echo htmlspecialchars($ticket['subject'], ENT_QUOTES, 'UTF-8'); ?></h4>
                    <p style="margin: 0 0 12px 0; color: #444; white-space: pre-wrap; font-size: 14px;"><?php echo htmlspecialchars($ticket['description'], ENT_QUOTES, 'UTF-8'); ?></p>
                    
                    <div style="display: flex; justify-content: space-between; align-items: center; font-size: 12px; border-top: 1px solid rgba(0,102,51,0.1); padding-top: 10px; color: #666;">
                        <span>Ticket ID #<?php echo $ticket['id']; ?> | Filed on: <?php echo $ticket['created_at']; ?></span>
                        <a href="my_tickets.php?delete_id=<?php echo $ticket['id']; ?>" onclick="return confirm('Cancel this support request?');" style="color: #e74c3c; font-weight: 600;">Cancel Request</a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <div style="margin-top: 35px; text-align: center;">
        <a href="dashboard.php" class="btn" style="background: #006633; max-width: 220px;">← Back to Support Dashboard</a>
    </div>
</div>

<?php require 'includes/footer.php'; ?>