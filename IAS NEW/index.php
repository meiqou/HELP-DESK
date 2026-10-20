<?php
require 'includes/header.php';
?>

<!-- Custom USLS Institutional Landing Overrides -->
<style>
    /* Lasallian Color Accents injected specifically for index page */
    body {
        background: linear-gradient(135deg, #f0f7f4 0%, #e1ede6 100%);
    }
    .usls-hero-card {
        background: rgba(255, 255, 255, 0.75);
        backdrop-filter: blur(16px);
        -webkit-backdrop-filter: blur(16px);
        border-radius: 20px;
        border: 1px solid rgba(0, 102, 51, 0.15);
        box-shadow: 0 15px 35px rgba(0, 102, 51, 0.08);
        padding: 50px 40px;
        width: 100%;
        max-width: 850px;
        text-align: center;
    }
    .usls-title {
        color: #006633; /* Official La Salle Green */
        font-size: 2.2rem;
        font-weight: 800;
        margin-bottom: 10px;
        letter-spacing: -0.5px;
    }
    .usls-subtitle {
        color: #555;
        font-size: 1.05rem;
        margin-bottom: 30px;
        line-height: 1.6;
    }
    .motto-badge {
        display: inline-block;
        background: rgba(212, 160, 23, 0.15);
        color: #b78103; /* Lasallian Gold Accent */
        border: 1px solid rgba(212, 160, 23, 0.3);
        padding: 6px 16px;
        border-radius: 30px;
        font-size: 12px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 1px;
        margin-bottom: 20px;
    }
    .stats-container {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 15px;
        margin: 30px 0;
        text-align: left;
    }
    .stat-box {
        background: rgba(255, 255, 255, 0.6);
        border: 1px solid rgba(0, 102, 51, 0.1);
        padding: 18px;
        border-radius: 12px;
    }
    .stat-box h4 {
        margin: 0 0 5px 0;
        color: #006633;
        font-size: 15px;
    }
    .stat-box p {
        margin: 0;
        font-size: 13px;
        color: #666;
        text-align: left;
    }
    .btn-usls-green {
        background: #006633 !important;
        box-shadow: 0 4px 15px rgba(0, 102, 51, 0.3) !important;
    }
    .btn-usls-green:hover {
        background: #004d26 !important;
    }
</style>

<div class="usls-hero-card">
    <div class="motto-badge">Religio • Mores • Cultura</div>
    
    <h2 class="usls-title">University of St. La Salle</h2>
    <p style="font-weight: 600; color: #333; margin-top: -5px; margin-bottom: 15px; font-size: 1.1rem;">Information Technology Services • Helpdesk Portal</p>
    
    <p class="usls-subtitle">
        Providing secure, reliable, and responsive digital support services for the entire USLS community. Students and faculty can file service tickets, track campus network resolutions, and manage technical accounts under institutional security protocols.
    </p>

    <!-- Quick Features / Institutional Highlights -->
    <div class="stats-container">
        <div class="stat-box">
            <h4>🔒 Secure Authentication</h4>
            <p>Protected by salted password hashes and stateful session token controls.</p>
        </div>
        <div class="stat-box">
            <h4>🛠️ Ticketing Workflow</h4>
            <p>Instant dispatch for Wi-Fi, LMS, portal, and hardware lab requests.</p>
        </div>
        <div class="stat-box">
            <h4>👑 Role Authorization</h4>
            <p>Granular separation for student workspace and admin operations.</p>
        </div>
    </div>

    <!-- Action Buttons -->
    <div style="display: flex; gap: 15px; justify-content: center; margin-top: 30px;">
        <?php if (isset($_SESSION['user_id'])): ?>
            <a href="dashboard.php" class="btn btn-usls-green" style="max-width: 240px;">Go to Support Dashboard</a>
        <?php else: ?>
            <a href="login.php" class="btn btn-usls-green" style="max-width: 160px;">Sign In Portal</a>
            <a href="register.php" class="btn" style="max-width: 160px; background: #27ae60;">Register Account</a>
        <?php endif; ?>
    </div>
</div>

<?php require 'includes/footer.php'; ?>