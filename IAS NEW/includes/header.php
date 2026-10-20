<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>USLS IT Support Helpdesk</title>
    <style>
        * { box-sizing: border-box; }
        body {
            background: linear-gradient(135deg, #f0f7f4 0%, #e1ede6 100%);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            color: #2c3e50;
        }
        /* Navigation Bar */
        .glass-nav {
            background: rgba(255, 255, 255, 0.75);
            backdrop-filter: blur(14px);
            -webkit-backdrop-filter: blur(14px);
            border-bottom: 1px solid rgba(0, 102, 51, 0.15);
            padding: 12px 40px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 4px 20px rgba(0, 102, 51, 0.04);
            position: sticky;
            top: 0;
            z-index: 1000;
        }
        .glass-nav a {
            color: #006633;
            text-decoration: none;
            margin-left: 25px;
            font-weight: 600;
            font-size: 14px;
            transition: color 0.3s ease;
        }
        .glass-nav a:hover { color: #004d26; text-decoration: underline; }
        
        /* Logo Styling */
        .logo-img {
            height: 36px; /* Adjust height to fit cleanly in the navbar */
            width: auto;
            display: block;
            transition: opacity 0.2s ease;
        }
        .logo-img:hover {
            opacity: 0.85;
        }
        
        /* Main Layout */
        .main-content {
            flex: 1;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 50px 20px;
        }
        .glass-card {
            background: rgba(255, 255, 255, 0.85);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border-radius: 20px;
            border: 1px solid rgba(0, 102, 51, 0.15);
            box-shadow: 0 12px 40px 0 rgba(0, 102, 51, 0.08);
            padding: 40px;
            width: 100%;
            max-width: 500px;
        }
        
        /* Typography & Forms */
        h2 { color: #006633; margin-top: 0; text-align: center; font-size: 24px; font-weight: 700; }
        label { display: block; margin-bottom: 6px; font-size: 13px; font-weight: 600; color: #006633; text-align: left; }
        input, select, textarea {
            width: 100%;
            padding: 12px 15px;
            margin-bottom: 15px;
            border: 1px solid rgba(0, 102, 51, 0.2);
            background: rgba(255, 255, 255, 0.8);
            border-radius: 10px;
            outline: none;
            color: #333;
            font-size: 14px;
            transition: all 0.3s ease;
            font-family: inherit;
        }
        input:focus, select:focus, textarea:focus { 
            border-color: #006633; 
            background: rgba(255, 255, 255, 1); 
            box-shadow: 0 0 0 3px rgba(0, 102, 51, 0.15);
        }
        
        /* Buttons */
        .btn {
            width: 100%;
            padding: 12px;
            background: #006633;
            color: white;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            font-weight: 600;
            font-size: 14px;
            text-align: center;
            display: inline-block;
            text-decoration: none;
            box-shadow: 0 4px 15px rgba(0, 102, 51, 0.3);
            transition: all 0.3s ease;
        }
        .btn:hover { background: #004d26; transform: translateY(-1px); box-shadow: 0 6px 20px rgba(0, 102, 51, 0.4); }
        .btn-danger { background: #e74c3c; box-shadow: 0 4px 15px rgba(231, 76, 60, 0.3); }
        .btn-danger:hover { background: #c0392b; box-shadow: 0 6px 20px rgba(231, 76, 60, 0.4); }
        .btn-success { background: #27ae60; box-shadow: 0 4px 15px rgba(39, 174, 96, 0.3); }
        .btn-success:hover { background: #219653; }

        /* Badges & Alerts */
        .badge {
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            display: inline-block;
        }
        .badge-admin { background: rgba(212, 160, 23, 0.15); color: #b78103; border: 1px solid rgba(212, 160, 23, 0.3); }
        .badge-user { background: rgba(0, 102, 51, 0.15); color: #006633; border: 1px solid rgba(0, 102, 51, 0.3); }
        
        .error { color: #c0392b; background: rgba(231, 76, 60, 0.1); border: 1px solid rgba(231, 76, 60, 0.2); padding: 12px; border-radius: 8px; margin-bottom: 20px; text-align: center; font-size: 13px; font-weight: 500; }
        .success { color: #006633; background: rgba(0, 102, 51, 0.1); border: 1px solid rgba(0, 102, 51, 0.2); padding: 12px; border-radius: 8px; margin-bottom: 20px; text-align: center; font-size: 13px; font-weight: 500; }
        
        /* Tables */
        table { width: 100%; border-collapse: collapse; margin-top: 15px; border-radius: 10px; overflow: hidden; }
        th, td { padding: 14px 16px; text-align: left; border-bottom: 1px solid rgba(0, 102, 51, 0.15); font-size: 14px; }
        th { background: rgba(0, 102, 51, 0.1); font-weight: 600; color: #006633; }
        tr:hover { background: rgba(0, 102, 51, 0.04); }
        
        p { text-align: center; font-size: 14px; color: #555; }
        a { color: #006633; text-decoration: none; font-weight: 600; }
        a:hover { text-decoration: underline; }
    </style>
</head>
<body>

<div class="glass-nav">
    <a href="index.php">
        <img src="logo.svg" alt="University of St. La Salle" class="logo-img">
    </a>
    <div>
        <a href="index.php">Home</a>
        <?php if (isset($_SESSION['user_id'])): ?>
            <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                <!-- Admin Navigation -->
                <a href="admin_dashboard.php" style="color: #006633; font-weight: bold;">Admin Command Hub</a>
            <?php else: ?>
                <!-- Regular User Navigation -->
                <a href="dashboard.php">Support Dashboard</a>
                <a href="my_tickets.php">My Tickets</a>
            <?php endif; ?>
            <a href="logout.php" style="color: #e74c3c;">Logout</a>
        <?php else: ?>
            <a href="login.php">Login</a>
            <a href="register.php">Register</a>
        <?php endif; ?>
    </div>
</div>
<div class="main-content">