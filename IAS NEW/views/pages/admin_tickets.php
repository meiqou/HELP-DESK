<div class="page-header">
    <div>
        <p class="eyebrow">Ticket queue</p>
        <h1>Support tickets</h1>
        <p class="muted"><?= $total ?> ticket<?= $total === 1 ? '' : 's' ?> &middot; sorted by status and priority</p>
    </div>
    <a href="admin_tickets.php?mine=1" class="btn btn-secondary"><?= icon('inbox') ?> Assigned to me</a>
</div>

<div class="grid grid-4">
    <?= stat_card('Open', $queue['open'], 'clock', 'gold', 'Waiting for a technician') ?>
    <?= stat_card('In progress', $queue['in_progress'], 'flame', 'blue', 'Currently being handled') ?>
    <?= stat_card('Unassigned', $queue['unassigned'], 'users', 'gray', 'Not yet claimed') ?>
    <?= stat_card('Urgent pending', $queue['urgent'], 'alert', 'red', 'High or critical priority') ?>
</div>

<section class="card card-flush">
    <form method="GET" class="toolbar">
        <input class="input" type="search" name="q" value="<?= e($q) ?>" placeholder="Search subject, user or category" aria-label="Search tickets">
        <select class="select" name="status" aria-label="Status">
            <option value="">All statuses</option>
            <?php foreach (App\Domain\Ticket::STATUSES as $s): ?>
                <option value="<?= e($s) ?>"<?= $status === $s ? ' selected' : '' ?>><?= e($s) ?></option>
            <?php endforeach; ?>
        </select>
        <select class="select" name="priority" aria-label="Priority">
            <option value="">All priorities</option>
            <?php foreach (App\Domain\Ticket::PRIORITIES as $p): ?>
                <option value="<?= e($p) ?>"<?= $priority === $p ? ' selected' : '' ?>><?= e($p) ?></option>
            <?php endforeach; ?>
        </select>
        <select class="select" name="mine" aria-label="Assignment">
            <option value="">Everyone&rsquo;s</option>
            <option value="1"<?= $mine ? ' selected' : '' ?>>Assigned to me</option>
        </select>
        <button type="submit" class="btn btn-secondary btn-sm" data-no-lock>Filter</button>
        <?php if ($baseQuery): ?><a href="admin_tickets.php" class="small">Clear</a><?php endif; ?>
    </form>

    <?php if (!$tickets): ?>
        <div class="empty-state">
            <h2>Nothing here</h2>
            <p class="muted">No tickets match these filters.</p>
        </div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>Request</th>
                        <th>Requester</th>
                        <th>Status</th>
                        <th>Update</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($tickets as $t): ?>
                        <tr>
                            <td>
                                <p class="cell-sub"><span class="mono">#<?= (int) $t['id'] ?></span> &middot; <?= e($t['category']) ?></p>
                                <p class="cell-title"><?= e($t['subject']) ?></p>
                                <details class="disclosure mt-1">
                                    <summary>Details</summary>
                                    <p class="cell-body"><?= e($t['description']) ?></p>
                                </details>
                            </td>
                            <td>
                                <p class="cell-title"><?= e($t['username']) ?></p>
                                <p class="cell-sub nowrap"><?= e(format_date($t['created_at'])) ?></p>
                                <p class="cell-sub">
                                    <?php if ($t['assignee']): ?>
                                        Assigned to <?= e($t['assignee']) ?>
                                    <?php else: ?>
                                        Unassigned
                                    <?php endif; ?>
                                </p>
                            </td>
                            <td>
                                <span class="badge badge-dot <?= e(badge_class($t['status'])) ?>"><?= e($t['status']) ?></span>
                                <p class="mt-1"><span class="badge <?= e(badge_class($t['priority'])) ?>"><?= e($t['priority']) ?></span></p>
                            </td>
                            <td>
                                <form method="POST" action="<?= e($formAction) ?>" class="inline-form">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="update">
                                    <input type="hidden" name="ticket_id" value="<?= (int) $t['id'] ?>">
                                    <select class="select select-sm" name="status" aria-label="Status for ticket <?= (int) $t['id'] ?>">
                                        <?php foreach (App\Domain\Ticket::STATUSES as $s): ?>
                                            <option value="<?= e($s) ?>"<?= $t['status'] === $s ? ' selected' : '' ?>><?= e($s) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <select class="select select-sm" name="priority" aria-label="Priority for ticket <?= (int) $t['id'] ?>">
                                        <?php foreach (App\Domain\Ticket::PRIORITIES as $p): ?>
                                            <option value="<?= e($p) ?>"<?= $t['priority'] === $p ? ' selected' : '' ?>><?= e($p) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button type="submit" class="btn btn-primary btn-sm">Save</button>
                                </form>
                                <?php if ((int) $t['assigned_to'] !== (int) $user['id']): ?>
                                    <form method="POST" action="<?= e($formAction) ?>" class="mt-1">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="claim">
                                        <input type="hidden" name="ticket_id" value="<?= (int) $t['id'] ?>">
                                        <button type="submit" class="btn-link">Assign to me</button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php if ($pages > 1): ?>
            <div class="pagination">
                <span>Page <?= $page ?> of <?= $pages ?></span>
                <span class="actions">
                    <?php if ($page > 1): ?><a class="btn btn-secondary btn-sm" href="?<?= e(http_build_query($baseQuery + ['page' => $page - 1])) ?>">Previous</a><?php endif; ?>
                    <?php if ($page < $pages): ?><a class="btn btn-secondary btn-sm" href="?<?= e(http_build_query($baseQuery + ['page' => $page + 1])) ?>">Next</a><?php endif; ?>
                </span>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</section>
