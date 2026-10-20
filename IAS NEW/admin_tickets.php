<?php
require 'includes/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Strict Admin Authorization Guard
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

$message = '';
$msgClass = '';

// Handle Status Update Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ticket_id'], $_POST['status'])) {
    $ticket_id = intval($_POST['ticket_id']);
    $new_status = trim($_POST['status']);

    $allowed_statuses = ['Open', 'In Progress', 'Resolved'];
    
    if (in_array($new_status, $allowed_statuses)) {
        try {
            $stmt = $pdo->prepare("UPDATE tickets SET status = ? WHERE id = ?");
            $stmt->execute([$new_status, $ticket_id]);
            
            header("Location: admin_tickets.php?success=1");
            exit();
        } catch (\PDOException $e) {
            $message = "Database Error: Could not update ticket status.";
            $msgClass = "error";
        }
    } else {
        $message = "Invalid status selection.";
        $msgClass = "error";
    }
}

if (isset($_GET['success']) && $_GET['success'] == 1) {
    $message = "Ticket status successfully updated!";
    $msgClass = "success";
}

require 'includes/header.php';

// Fetch all tickets along with the owner's username
$stmt = $pdo->query("SELECT tickets.*, users.username FROM tickets JOIN users ON tickets.user_id = users.id ORDER BY tickets.created_at DESC");
$tickets = $stmt->fetchAll();

// Calculate quick metrics for the top UI bar
$totalCount = count($tickets);
$openCount = count(array_filter($tickets, fn($t) => $t['status'] === 'Open'));
$progressCount = count(array_filter($tickets, fn($t) => $t['status'] === 'In Progress'));
$resolvedCount = count(array_filter($tickets, fn($t) => $t['status'] === 'Resolved'));
?>

<style>
    body { background: linear-gradient(135deg, #f0f7f4 0%, #e1ede6 100%); }
    .usls-card {
        background: rgba(255, 255, 255, 0.9);
        backdrop-filter: blur(20px);
        -webkit-backdrop-filter: blur(20px);
        border-radius: 24px;
        border: 1px solid rgba(0, 102, 51, 0.12);
        box-shadow: 0 20px 40px rgba(0, 102, 51, 0.08);
        padding: 40px;
        width: 100%;
        max-width: 1100px;
    }
    .metric-pills {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        gap: 15px;
        margin-bottom: 25px;
    }
    .metric-pill {
        background: rgba(255, 255, 255, 0.7);
        border: 1px solid rgba(0, 102, 51, 0.1);
        padding: 15px 20px;
        border-radius: 14px;
        display: flex;
        flex-direction: column;
        transition: transform 0.2s ease;
    }
    .metric-pill:hover { transform: translateY(-2px); }
    .metric-pill span { font-size: 12px; font-weight: 600; color: #555; text-transform: uppercase; letter-spacing: 0.5px; }
    .metric-pill h3 { margin: 5px 0 0 0; font-size: 22px; color: #006633; }
    
    .search-filter-bar {
        display: flex;
        gap: 15px;
        margin-bottom: 25px;
        flex-wrap: wrap;
    }
    .search-input {
        flex: 1;
        min-width: 250px;
        margin: 0 !important;
        background: rgba(255, 255, 255, 0.9) !important;
    }
    .ticket-row {
        transition: background 0.2s ease;
    }
    .ticket-row:hover {
        background: rgba(0, 102, 51, 0.03) !important;
    }
</style>

<div class="usls-card">
    <!-- Header Banner -->
    <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid rgba(0, 102, 51, 0.12); padding-bottom: 18px; margin-bottom: 25px;">
        <div>
            <h2 style="margin: 0; text-align: left; color: #006633; font-size: 1.8rem;">🛠️ Helpdesk Ticket Management</h2>
            <p style="margin: 5px 0 0 0; color: #666; font-size: 13px;">Review and process campus technical support requests</p>
        </div>
        <a href="admin_dashboard.php" class="btn" style="max-width: 150px; margin: 0; background: #2c3e50; padding: 10px 16px;">← Admin Hub</a>
    </div>

    <!-- Metrics Overview Pills -->
    <div class="metric-pills">
        <div class="metric-pill">
            <span>Total Tickets</span>
            <h3><?php echo $totalCount; ?></h3>
        </div>
        <div class="metric-pill" style="border-left: 4px solid #b78103;">
            <span>Open Status</span>
            <h3><?php echo $openCount; ?></h3>
        </div>
        <div class="metric-pill" style="border-left: 4px solid #2980b9;">
            <span>In Progress</span>
            <h3><?php echo $progressCount; ?></h3>
        </div>
        <div class="metric-pill" style="border-left: 4px solid #006633;">
            <span>Resolved</span>
            <h3><?php echo $resolvedCount; ?></h3>
        </div>
    </div>

    <?php if (!empty($message)) echo "<div class='$msgClass'>$message</div>"; ?>

    <!-- Search Bar UI -->
    <div class="search-filter-bar">
        <input type="text" id="ticketSearch" class="search-input" placeholder="🔍 Search tickets by username, category, or keyword..." onkeyup="filterTickets()">
    </div>

    <?php if (empty($tickets)): ?>
        <div style="text-align: center; padding: 50px; background: rgba(0,102,51,0.02); border-radius: 16px; border: 1px dashed rgba(0,102,51,0.15);">
            <p style="color: #666; font-style: italic; margin: 0;">No support tickets have been filed in the system yet.</p>
        </div>
    <?php else: ?>
        <div style="overflow-x: auto; border-radius: 14px; border: 1px solid rgba(0, 102, 51, 0.1);">
            <table id="ticketsTable" style="margin: 0;">
                <thead>
                    <tr>
                        <th style="padding: 16px;">ID</th>
                        <th style="padding: 16px;">Submitted By</th>
                        <th style="padding: 16px;">Support Request Details</th>
                        <th style="padding: 16px;">Current Status</th>
                        <th style="padding: 16px;">Update Status Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($tickets as $t): 
                        $statusColor = "#b78103"; // Open
                        if ($t['status'] === 'In Progress') $statusColor = "#2980b9";
                        if ($t['status'] === 'Resolved') $statusColor = "#006633";
                    ?>
                    <tr class="ticket-row">
                        <td style="padding: 16px; vertical-align: top;"><strong>#<?php echo $t['id']; ?></strong></td>
                        <td style="padding: 16px; vertical-align: top;">
                            <span style="font-weight: 600; color: #2c3e50;"><?php echo htmlspecialchars($t['username'], ENT_QUOTES, 'UTF-8'); ?></span>
                        </td>
                        <td style="padding: 16px; vertical-align: top; max-width: 380px;">
                            <span style="font-size: 11px; background: rgba(0,102,51,0.08); color: #006633; padding: 3px 8px; border-radius: 6px; font-weight: 700;"><?php echo htmlspecialchars($t['category'], ENT_QUOTES, 'UTF-8'); ?></span><br>
                            <strong style="font-size: 14px; color: #111; display: block; margin-top: 6px;"><?php echo htmlspecialchars($t['subject'], ENT_QUOTES, 'UTF-8'); ?></strong>
                            <p style="margin: 6px 0 0 0; font-size: 13px; color: #444; white-space: pre-wrap; text-align: left; background: rgba(255,255,255,0.7); padding: 10px; border-radius: 8px; border: 1px solid rgba(0,102,51,0.08);"><?php echo htmlspecialchars($t['description'], ENT_QUOTES, 'UTF-8'); ?></p>
                            <span style="font-size: 11px; color: #888; display: inline-block; margin-top: 6px;">🕒 Filed: <?php echo $t['created_at']; ?></span>
                        </td>
                        <td style="padding: 16px; vertical-align: top;">
                            <span style="font-size: 12px; font-weight: 700; background: <?php echo $statusColor; ?>15; color: <?php echo $statusColor; ?>; padding: 6px 12px; border-radius: 20px; white-space: nowrap; display: inline-block; border: 1px solid <?php echo $statusColor; ?>30;">
                                ● <?php echo $t['status']; ?>
                            </span>
                        </td>
                        <td style="padding: 16px; vertical-align: top;">
                            <form method="POST" style="display: flex; gap: 8px; margin: 0; align-items: center;">
                                <input type="hidden" name="ticket_id" value="<?php echo $t['id']; ?>">
                                <select name="status" style="margin: 0; padding: 8px; font-size: 13px; border-color: rgba(0,102,51,0.2); width: 130px; background: white;">
                                    <option value="Open" <?php if($t['status']=='Open') echo 'selected'; ?>>Open</option>
                                    <option value="In Progress" <?php if($t['status']=='In Progress') echo 'selected'; ?>>In Progress</option>
                                    <option value="Resolved" <?php if($t['status']=='Resolved') echo 'selected'; ?>>Resolved</option>
                                </select>
                                <button type="submit" class="btn" style="background: #006633; padding: 8px 14px; font-size: 13px; width: auto; box-shadow: none;">Save</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <div style="margin-top: 40px; text-align: center;">
        <a href="admin_dashboard.php" class="btn" style="background: #006633; max-width: 240px; margin: 0 auto;">Back to Admin Command Hub</a>
    </div>
</div>

<!-- JavaScript Live Search Filter -->
<script>
function filterTickets() {
    let input = document.getElementById('ticketSearch').value.toLowerCase();
    let table = document.getElementById('ticketsTable');
    let tr = table.getElementsByTagName('tr');

    for (let i = 1; i < tr.length; i++) {
        let rowText = tr[i].textContent || tr[i].innerText;
        if (rowText.toLowerCase().indexOf(input) > -1) {
            tr[i].style.display = "";
        } else {
            tr[i].style.display = "none";
        }
    }
}
</script>

<?php require 'includes/footer.php'; ?>