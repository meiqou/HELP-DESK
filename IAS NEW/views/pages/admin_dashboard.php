<div class="page-header">
    <div>
        <p class="welcome-date"><?= e(date('l, F j, Y')) ?></p>
        <h1><?= e(greeting()) ?>, <?= e($user['username']) ?></h1>
        <p class="muted"><?= e(App\Auth\Rbac::label($user['role'])) ?> &middot; last sign-in <?= e(format_date($user['last_login_at'])) ?></p>
    </div>
    <a href="admin_tickets.php" class="btn btn-primary"><?= icon('inbox') ?> Open ticket queue</a>
</div>

<div class="grid grid-4">
    <?= stat_card('Open', $ticketCounts['Open'], 'clock', 'gold', 'Waiting for a technician') ?>
    <?= stat_card('In progress', $ticketCounts['In Progress'], 'flame', 'blue', 'Currently being handled') ?>
    <?= stat_card('Resolved', $ticketCounts['Resolved'], 'check', 'green', 'Closed requests') ?>
    <?= stat_card('Urgent pending', $urgent, 'alert', 'red', 'High or critical priority') ?>
</div>

<?php if ($security !== null): ?>
    <div class="section">
        <div class="section-title"><h2>Security at a glance</h2><a href="audit_log.php" class="small">Open audit log</a></div>
        <div class="grid grid-4">
            <?= stat_card('Accounts', $security['users'], 'users', 'gray') ?>
            <?= stat_card('Two-factor enabled', $security['mfa'], 'shield', 'green') ?>
            <?= stat_card('Failed sign-ins (24h)', $security['failed_24h'], 'lock', 'gold') ?>
            <?= stat_card('Lockouts (24h)', $security['locked_24h'], 'key', 'red') ?>
        </div>
    </div>
<?php endif; ?>

<div class="grid grid-sidebar section">
    <div class="grid grid-2">
        <a class="link-card" href="admin_tickets.php">
            <h3>Ticket queue</h3>
            <p>Review requests from students and staff and move them through Open, In Progress and Resolved.</p>
            <span class="arrow">Open queue &rarr;</span>
        </a>
        <?php if (can('user.manage')): ?>
            <a class="link-card" href="manage_users.php">
                <h3>User accounts</h3>
                <p>Assign roles, deactivate accounts, reset two-factor and clear lockouts.</p>
                <span class="arrow">Manage users &rarr;</span>
            </a>
        <?php endif; ?>
        <a class="link-card" href="account.php">
            <h3>Account security</h3>
            <p>Change your password, set up two-factor authentication and sign out other sessions.</p>
            <span class="arrow">Security settings &rarr;</span>
        </a>
        <?php if (can('audit.view')): ?>
            <a class="link-card" href="audit_log.php">
                <h3>Audit log</h3>
                <p>A tamper-evident trail of sign-ins, permission changes and blocked requests.</p>
                <span class="arrow">View log &rarr;</span>
            </a>
        <?php endif; ?>
    </div>

    <?php if ($security !== null): ?>
        <aside class="card">
            <div class="section-title">
                <h2>Recent alerts</h2>
                <a href="audit_log.php" class="small">View all</a>
            </div>
            <?php if (!$events): ?>
                <p class="muted small">No security alerts. All quiet.</p>
            <?php else: ?>
                <ul class="list-plain">
                    <?php foreach ($events as $ev): ?>
                        <li>
                            <span>
                                <span class="cell-title"><?= e(str_replace('_', ' ', ucfirst($ev['event']))) ?></span><br>
                                <span class="muted small"><?= e($ev['username'] ?? 'unknown') ?> &middot; <?= e($ev['ip']) ?></span>
                            </span>
                            <span class="muted small nowrap"><?= e(date('M j, g:i A', strtotime($ev['created_at']))) ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </aside>
    <?php endif; ?>
</div>
