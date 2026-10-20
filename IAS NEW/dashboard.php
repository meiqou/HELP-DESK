<?php
require 'includes/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$current_user_id = $_SESSION['user_id'];
$current_username = htmlspecialchars($_SESSION['username'], ENT_QUOTES, 'UTF-8');
$current_user_role = htmlspecialchars($_SESSION['role'], ENT_QUOTES, 'UTF-8');

// If the user is an admin, redirect them to their dedicated command center BEFORE any HTML starts
if ($current_user_role === 'admin') {
    header("Location: admin_dashboard.php");
    exit();
}

// Now include the header safely after all redirects are handled
require 'includes/header.php';

$message = '';
$msgClass = '';

// Handle Submitting a New IT Support Ticket
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'submit_ticket') {
    $category = trim($_POST['category']);
    $subject = trim($_POST['subject']);
    $description = trim($_POST['description']);

    if (!empty($category) && !empty($subject) && !empty($description)) {
        $stmt = $pdo->prepare("INSERT INTO tickets (user_id, category, subject, description, status) VALUES (?, ?, ?, ?, 'Open')");
        $stmt->execute([$current_user_id, $category, $subject, $description]);
        $message = "Support ticket successfully filed with IT Services! Check 'My Tickets' to track status updates.";
        $msgClass = "success";
    } else {
        $message = "All ticket fields are required.";
        $msgClass = "error";
    }
}
?>

<!-- USLS Institutional Portal Custom Styling Overrides -->
<style>
    body {
        background: linear-gradient(135deg, #f0f7f4 0%, #e1ede6 100%);
    }
    .usls-dash-card {
        background: rgba(255, 255, 255, 0.8);
        backdrop-filter: blur(16px);
        -webkit-backdrop-filter: blur(16px);
        border-radius: 20px;
        border: 1px solid rgba(0, 102, 51, 0.15);
        box-shadow: 0 15px 35px rgba(0, 102, 51, 0.08);
        padding: 40px;
        width: 100%;
        max-width: 850px;
    }
    .usls-header-banner {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 25px;
        border-bottom: 2px solid rgba(0, 102, 51, 0.12);
        padding-bottom: 15px;
    }
    .usls-dash-title {
        color: #006633;
        margin: 0;
        text-align: left;
        font-size: 1.8rem;
        font-weight: 800;
    }
    .btn-usls-green {
        background: #006633 !important;
        box-shadow: 0 4px 15px rgba(0, 102, 51, 0.3) !important;
    }
    .btn-usls-green:hover {
        background: #004d26 !important;
    }
    .notice-box {
        background: rgba(212, 160, 23, 0.1);
        border-left: 4px solid #b78103;
        padding: 12px 15px;
        border-radius: 0 8px 8px 0;
        font-size: 13px;
        color: #555;
        margin-bottom: 20px;
        text-align: left;
    }
    .action-button-group {
        display: flex;
        gap: 15px;
        align-items: center;
        flex-wrap: wrap;
        margin-top: 20px;
    }
    .action-button-group .btn {
        width: auto !important;
        flex: 1;
        min-width: 200px;
        text-align: center;
        padding: 12px 20px;
        box-sizing: border-box;
        display: inline-block;
        text-decoration: none;
    }
</style>

<div class="usls-dash-card">
    <!-- Portal Header -->
    <div class="usls-header-banner">
        <div>
            <h2 class="usls-dash-title">USLS IT Helpdesk Portal</h2>
            <p style="margin: 5px 0 0 0; color: #555; font-size: 13px;">Information Technology Services • User Workspace</p>
        </div>
        <div>
            <span class="badge badge-user">Student / Staff Account</span>
        </div>
    </div>
    
    <!-- Notice Banner -->
    <div class="notice-box">
        📌 <strong>Helpdesk Advisory:</strong> Welcome back, <strong><?php echo $current_username; ?></strong>. Please provide accurate details regarding your technical issue to ensure prompt resolution by campus IT engineers.
    </div>
    
    <?php if (!empty($message)) echo "<div class='$msgClass'>$message</div>"; ?>

    <!-- Submit Ticket Form Container -->
    <div style="background: rgba(255, 255, 255, 0.6); padding: 30px; border-radius: 16px; border: 1px solid rgba(0, 102, 51, 0.12); margin-bottom: 25px;">
        <h3 style="margin-top: 0; color: #006633; font-size: 1.15rem; text-align: left;">🛠️ File a New Technical Support Ticket</h3>
        
        <form method="POST">
            <input type="hidden" name="action" value="submit_ticket">
            
            <label style="color: #006633;">Select Category</label>
            <select name="category" required style="margin-bottom: 15px; border-color: rgba(0,102,51,0.2);">
                <option value="">Choose system or hardware category...</option>
                <option value="Wi-Fi / Network">Wi-Fi & Campus Network Infrastructure</option>
                <option value="LMS / Portal">LMS / Animo System / Student Portal</option>
                <option value="Hardware / Lab PC">Hardware / Computer Lab Malfunction</option>
                <option value="Software / Licensing">Software / Microsoft 365 Licensing</option>
                <option value="Account / Password">Institutional Account Reset & Security</option>
            </select>

            <label style="color: #006633;">Subject / Issue Summary</label>
            <input type="text" name="subject" placeholder="e.g., Unable to connect to campus Wi-Fi near St. La Salle Hall" required style="border-color: rgba(0,102,51,0.2);">
            
            <label style="color: #006633;">Detailed Description</label>
            <textarea name="description" placeholder="Include error codes, exact building/room number, or device specifications..." rows="4" style="width: 100%; padding: 12px; margin-bottom: 15px; border: 1px solid rgba(0, 102, 51, 0.2); background: rgba(255, 255, 255, 0.8); border-radius: 10px; outline: none; font-family: inherit; color: #333; resize: vertical;" required></textarea>

            <!-- Buttons -->
            <div class="action-button-group">
                <button type="submit" class="btn btn-usls-green">Submit Ticket</button>
                <a href="my_tickets.php" class="btn" style="background: #27ae60;">View My Tickets History →</a>
            </div>
        </form>
    </div>
    
    <div style="margin-top: 35px; text-align: center;">
        <a href="logout.php" class="btn btn-danger" style="max-width: 160px; display: inline-block;">Log Out</a>
    </div>
</div>

<?php require 'includes/footer.php'; ?>