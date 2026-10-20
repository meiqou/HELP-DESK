<div class="page-header">
    <div>
        <p class="eyebrow">Ticket history</p>
        <h1>My tickets</h1>
        <p class="muted">Every request you have filed and where it stands.</p>
    </div>
    <a href="dashboard.php#new-ticket" class="btn btn-primary"><?= icon('plus') ?> New ticket</a>
</div>

<div class="grid grid-4">
    <?= stat_card('All requests', array_sum($allCounts), 'ticket', 'gray') ?>
    <?= stat_card('Open', $allCounts['Open'], 'clock', 'gold') ?>
    <?= stat_card('In progress', $allCounts['In Progress'], 'flame', 'blue') ?>
    <?= stat_card('Resolved', $allCounts['Resolved'], 'check', 'green') ?>
</div>

<section class="card card-flush">
    <form method="GET" class="toolbar">
        <input class="input" type="search" placeholder="Search your tickets" aria-label="Search your tickets" data-filter=".ticket">
        <select class="select" name="status" aria-label="Filter by status">
            <option value="">All statuses</option>
            <?php foreach (App\Domain\Ticket::STATUSES as $s): ?>
                <option value="<?= e($s) ?>"<?= $filter === $s ? ' selected' : '' ?>><?= e($s) ?></option>
            <?php endforeach; ?>
        </select>
        <button type="submit" class="btn btn-secondary btn-sm" data-no-lock>Apply</button>
    </form>

    <?php if (!$tickets): ?>
        <div class="empty-state">
            <h2>No tickets yet</h2>
            <p class="muted"><?= $filter ? 'No tickets match this status.' : 'When you file a support request it will appear here.' ?></p>
            <a href="dashboard.php" class="btn btn-primary">File a ticket</a>
        </div>
    <?php else: ?>
        <ul class="ticket-list">
            <?php foreach ($tickets as $t): ?>
                <li class="ticket" data-search="<?= e('#' . $t['id'] . ' ' . $t['category'] . ' ' . $t['subject'] . ' ' . $t['description'] . ' ' . $t['status'] . ' ' . $t['priority']) ?>">
                    <div>
                        <div class="ticket-meta">
                            <span class="mono">#<?= (int) $t['id'] ?></span>
                            <span>&middot;</span>
                            <span><?= e($t['category']) ?></span>
                            <span>&middot;</span>
                            <span><?= e(format_date($t['created_at'])) ?></span>
                        </div>
                        <p class="ticket-title"><?= e($t['subject']) ?></p>
                        <p class="ticket-body"><?= e($t['description']) ?></p>
                    </div>
                    <div class="ticket-side">
                        <span class="badge badge-dot <?= e(badge_class($t['status'])) ?>"><?= e($t['status']) ?></span>
                        <span class="badge <?= e(badge_class($t['priority'])) ?>"><?= e($t['priority']) ?> priority</span>
                        <?php if ($t['status'] === 'Open'): ?>
                            <form method="POST" data-confirm="Withdraw this ticket? This cannot be undone.">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="cancel">
                                <input type="hidden" name="ticket_id" value="<?= (int) $t['id'] ?>">
                                <button type="submit" class="btn-link danger">Withdraw</button>
                            </form>
                        <?php endif; ?>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>
