<?php
/**
 * Shared page layout. Variables: $content (rendered page), $layout, $pageTitle,
 * optional $publicPage (landing page) and $mainClass.
 *
 * Layouts:
 *   shell  : signed-in pages with the sidebar
 *   public : visitors and the landing page, with the top bar and site footer
 *   auth   : split sign-in screen
 */

$navUser = current_user();
$currentPage = basename($_SERVER['SCRIPT_NAME'] ?? '');
if ($layout === 'app') {
    $layout = ($navUser && empty($publicPage)) ? 'shell' : 'public';
}
$flashes = App\Core\Session::takeFlashes();
$initial = $navUser ? strtoupper(substr($navUser['username'], 0, 1)) : '';

$navGroups = [];
if ($navUser) {
    if (can('ticket.create')) {
        $navGroups['Workspace'] = ['dashboard.php' => ['Dashboard', 'home'], 'my_tickets.php' => ['My tickets', 'ticket']];
    }
    if (can('ticket.manage')) {
        $navGroups['Support desk'] = ['admin_dashboard.php' => ['Overview', 'grid'], 'admin_tickets.php' => ['Ticket queue', 'inbox']];
    }
    $admin = [];
    if (can('user.manage')) {
        $admin['manage_users.php'] = ['Users', 'users'];
    }
    if (can('audit.view')) {
        $admin['audit_log.php'] = ['Audit log', 'file'];
    }
    if ($admin) {
        $navGroups['Administration'] = $admin;
    }
    $navGroups['Account'] = ['account.php' => ['Security settings', 'shield']];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($pageTitle) ? e($pageTitle) . ' · ' : '' ?>USLS IT Helpdesk</title>
    <link rel="icon" href="assets/img/star.svg" type="image/svg+xml">
    <link rel="stylesheet" href="assets/css/app.css">
    <script src="assets/js/app.js" defer></script>
</head>
<body class="layout-<?= e($layout) ?>">

<?php if ($layout === 'shell'): ?>
<div class="shell">
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-brand"><?= brand_lockup() ?></div>

        <?php if (can('ticket.create')): ?>
            <a href="dashboard.php#new-ticket" class="btn btn-primary sidebar-cta"><?= icon('plus') ?> New ticket</a>
        <?php endif; ?>

        <nav class="sidebar-nav" aria-label="Main">
            <?php foreach ($navGroups as $group => $links): ?>
                <p class="sidebar-label"><?= e($group) ?></p>
                <?php foreach ($links as $href => [$label, $iconName]): ?>
                    <a href="<?= e($href) ?>" class="side-link<?= $currentPage === $href ? ' is-active' : '' ?>"<?= $currentPage === $href ? ' aria-current="page"' : '' ?>>
                        <?= icon($iconName) ?><span><?= e($label) ?></span>
                    </a>
                <?php endforeach; ?>
            <?php endforeach; ?>
        </nav>

        <div class="sidebar-foot">
            <div class="sidebar-user">
                <span class="avatar avatar-lg" aria-hidden="true"><?= e($initial) ?></span>
                <span class="sidebar-user-text">
                    <strong><?= e($navUser['username']) ?></strong>
                    <small><?= e(App\Auth\Rbac::label($navUser['role'])) ?></small>
                </span>
            </div>
            <form method="POST" action="logout.php">
                <?= csrf_field() ?>
                <button type="submit" class="side-link side-logout"><?= icon('logout') ?><span>Sign out</span></button>
            </form>
        </div>
    </aside>
    <div class="sidebar-backdrop" data-sidebar-close></div>

    <div class="shell-main">
        <header class="shell-top">
            <button class="icon-btn shell-menu" type="button" aria-controls="sidebar" aria-expanded="false" data-sidebar-toggle>
                <?= icon('menu') ?><span class="sr-only">Open navigation</span>
            </button>
            <p class="shell-crumb"><span>IT Helpdesk</span><span aria-hidden="true">/</span><strong><?= e($pageTitle ?? '') ?></strong></p>
            <div class="shell-top-right">
                <span class="shell-date"><?= icon('clock') ?><?= e(date('l, F j')) ?></span>
                <a href="account.php" class="avatar" title="Security settings"><?= e($initial) ?></a>
            </div>
        </header>
        <main class="shell-content">
            <?php foreach ($flashes as $f): ?>
                <div class="alert alert-<?= e($f['type']) ?>" role="status"><?= e($f['message']) ?></div>
            <?php endforeach; ?>
            <?= $content ?>
        </main>
        <footer class="shell-footer">
            <span>&copy; <?= date('Y') ?> University of St. La Salle &middot; Information Technology Services</span>
            <span class="footer-motto">Religio &middot; Mores &middot; Cultura</span>
        </footer>
    </div>
</div>

<?php elseif ($layout === 'public'): ?>
<header class="topbar">
    <div class="topbar-inner">
        <?= brand_lockup() ?>
        <button class="nav-toggle" type="button" aria-controls="primary-nav" aria-expanded="false">
            <span class="sr-only">Toggle navigation</span>
            <span class="nav-toggle-bar"></span>
        </button>
        <nav id="primary-nav" class="nav">
            <?php if ($navUser): ?>
                <a href="<?= e(home_for($navUser)) ?>" class="btn btn-primary btn-sm">Open dashboard</a>
            <?php else: ?>
                <a href="login.php" class="nav-link">Sign in</a>
                <a href="register.php" class="btn btn-primary btn-sm">Create account</a>
            <?php endif; ?>
        </nav>
    </div>
</header>
<main class="<?= e($mainClass ?? 'container') ?>">
    <?php foreach ($flashes as $f): ?>
        <div class="alert alert-<?= e($f['type']) ?>" role="status"><?= e($f['message']) ?></div>
    <?php endforeach; ?>
    <?= $content ?>
</main>
<footer class="site-footer">
    <div class="site-footer-inner">
        <div class="site-footer-brand">
            <?= brand_lockup('brand-inverse', 'star-white.svg') ?>
            <p>The official support portal of Information Technology Services for the USLS community in Bacolod City.</p>
            <p class="footer-motto">Religio &middot; Mores &middot; Cultura</p>
        </div>
        <div class="site-footer-col">
            <h4>Get help</h4>
            <?php if (!$navUser): ?>
                <a href="register.php">Create an account</a>
                <a href="login.php">Sign in</a>
                <a href="dashboard.php#new-ticket">Submit a ticket</a>
                <a href="my_tickets.php">Track my tickets</a>
            <?php elseif (can('ticket.create')): ?>
                <a href="dashboard.php">My dashboard</a>
                <a href="dashboard.php#new-ticket">Submit a ticket</a>
                <a href="my_tickets.php">Track my tickets</a>
            <?php else: ?>
                <a href="admin_dashboard.php">Support desk overview</a>
                <a href="admin_tickets.php">Ticket queue</a>
            <?php endif; ?>
        </div>
        <div class="site-footer-col">
            <h4>Your account</h4>
            <a href="account.php">Security settings</a>
            <a href="account.php#two-factor">Two-factor sign-in</a>
            <a href="account.php#password">Change password</a>
        </div>
    </div>
    <div class="site-footer-bottom">
        <span>&copy; <?= date('Y') ?> University of St. La Salle &middot; Information Technology Services</span>
        <span>ITS will never ask for your password.</span>
    </div>
</footer>

<?php else: ?>
<div class="auth">
    <aside class="auth-visual" aria-hidden="true">
        <div class="auth-visual-copy">
            <p class="auth-visual-brand"><img src="assets/img/star-white.svg" alt=""><span>IT <b>Helpdesk</b></span></p>
            <p class="auth-visual-motto">Religio &middot; Mores &middot; Cultura</p>
            <p class="auth-visual-text">Information Technology Services</p>
        </div>
    </aside>
    <main class="auth-panel">
        <div class="auth-box">
            <?= brand_lockup('auth-brand') ?>
            <?php foreach ($flashes as $f): ?>
                <div class="alert alert-<?= e($f['type']) ?>" role="status"><?= e($f['message']) ?></div>
            <?php endforeach; ?>
            <?= $content ?>
            <p class="auth-footnote">&copy; <?= date('Y') ?> University of St. La Salle &middot; ITS</p>
        </div>
    </main>
</div>
<?php endif; ?>
</body>
</html>
