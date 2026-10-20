<div class="page-header">
    <div>
        <p class="eyebrow">Administration</p>
        <h1>Audit log</h1>
        <p class="muted"><?= number_format($total) ?> event<?= $total === 1 ? '' : 's' ?> &middot; each entry is chained with an HMAC so tampering can be detected</p>
    </div>
    <form method="POST">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="verify">
        <button type="submit" class="btn btn-secondary"><?= icon('shield') ?> Verify integrity</button>
    </form>
</div>

<div class="grid grid-3">
    <?= stat_card('Events recorded', number_format($summary['total']), 'file', 'gray') ?>
    <?= stat_card('Last 24 hours', number_format($summary['day']), 'clock', 'green') ?>
    <?= stat_card('Security warnings (24h)', number_format($summary['warnings']), 'alert', 'red', 'Failed sign-ins, lockouts, blocked requests') ?>
</div>

<?php if ($integrityChecked): ?>
    <?php if ($integrity === null): ?>
        <div class="alert alert-success">Integrity verified. No entries have been altered or removed from the chain.</div>
    <?php else: ?>
        <div class="alert alert-error">Integrity check failed at entry #<?= (int) $integrity ?>. This entry, or one before it, was modified or deleted outside the application.</div>
    <?php endif; ?>
<?php endif; ?>

<section class="card card-flush">
    <form method="GET" class="toolbar">
        <input class="input" type="search" name="q" value="<?= e($q) ?>" placeholder="Search user, IP or details" aria-label="Search">
        <select class="select" name="event" aria-label="Event type">
            <option value="">All events</option>
            <?php foreach ($events as $ev): ?>
                <option value="<?= e($ev) ?>"<?= $eventFilter === $ev ? ' selected' : '' ?>><?= e(str_replace('_', ' ', $ev)) ?></option>
            <?php endforeach; ?>
        </select>
        <button type="submit" class="btn btn-secondary btn-sm" data-no-lock>Filter</button>
        <?php if ($baseQuery): ?><a href="audit_log.php" class="small">Clear</a><?php endif; ?>
    </form>

    <?php if (!$rows): ?>
        <div class="empty-state"><h2>No events</h2><p class="muted">Nothing matches these filters.</p></div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr><th>Time</th><th>Event</th><th>User</th><th>Details</th><th>IP address</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $r): ?>
                        <tr>
                            <td class="nowrap small"><?= e(date('M j, Y g:i:s A', strtotime($r['created_at']))) ?></td>
                            <td><span class="badge <?= in_array($r['event'], $warningEvents, true) ? 'badge-critical' : 'badge-normal' ?>"><?= e(str_replace('_', ' ', $r['event'])) ?></span></td>
                            <td><?= e($r['username'] ?? 'Unknown') ?></td>
                            <td class="small"><?= e($r['details'] ?: 'No details') ?></td>
                            <td class="mono"><?= e($r['ip']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php if ($pages > 1): ?>
            <div class="pagination">
                <span>Page <?= $page ?> of <?= $pages ?></span>
                <span class="actions">
                    <?php if ($page > 1): ?><a class="btn btn-secondary btn-sm" href="?<?= e(http_build_query($baseQuery + ['page' => $page - 1])) ?>">Newer</a><?php endif; ?>
                    <?php if ($page < $pages): ?><a class="btn btn-secondary btn-sm" href="?<?= e(http_build_query($baseQuery + ['page' => $page + 1])) ?>">Older</a><?php endif; ?>
                </span>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</section>
