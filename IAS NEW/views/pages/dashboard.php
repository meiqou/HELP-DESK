<div class="page-header">
    <div>
        <p class="welcome-date"><?= e(date('l, F j, Y')) ?></p>
        <h1><?= e(greeting()) ?>, <?= e($user['username']) ?></h1>
        <p class="muted">Here is an overview of your support requests.</p>
    </div>
    <a href="my_tickets.php" class="btn btn-secondary"><?= icon('list') ?> View all tickets</a>
</div>

<div class="grid grid-4">
    <?= stat_card('Total requests', $totalTickets, 'ticket', 'gray') ?>
    <?= stat_card('Open', $counts['Open'], 'clock', 'gold', 'Waiting for a technician') ?>
    <?= stat_card('In progress', $counts['In Progress'], 'flame', 'blue', 'Being worked on') ?>
    <?= stat_card('Resolved', $counts['Resolved'], 'check', 'green', 'Completed requests') ?>
</div>

<div class="grid grid-sidebar section">
    <section class="card" id="new-ticket">
        <div class="card-header">
            <h2>File a new ticket</h2>
            <p class="muted small">Tell us what went wrong. The more detail you give, the faster we can fix it.</p>
        </div>

        <?php if ($errors): ?>
            <div class="alert alert-error" role="alert">
                <ul><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul>
            </div>
        <?php endif; ?>

        <form method="POST" action="dashboard.php" class="form-stack">
            <?= csrf_field() ?>
            <div class="field-row">
                <div class="field">
                    <label for="category">Category</label>
                    <select class="select" id="category" name="category" required>
                        <option value="">Select a category</option>
                        <?php foreach (App\Domain\Ticket::CATEGORIES as $value => $label): ?>
                            <option value="<?= e($value) ?>"<?= $old['category'] === $value ? ' selected' : '' ?>><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field">
                    <label for="priority">Priority</label>
                    <select class="select" id="priority" name="priority" required>
                        <?php foreach (App\Domain\Ticket::PRIORITIES as $p): ?>
                            <option value="<?= e($p) ?>"<?= $old['priority'] === $p ? ' selected' : '' ?>><?= e($p) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="field">
                <label for="subject">Subject</label>
                <input class="input" type="text" id="subject" name="subject" value="<?= e($old['subject']) ?>"
                       minlength="5" maxlength="150" placeholder="e.g. Cannot connect to Wi-Fi in the library" required>
            </div>
            <div class="field">
                <label for="description">Description</label>
                <textarea class="textarea" id="description" name="description" minlength="10" maxlength="5000" rows="6"
                          placeholder="What happened, where (building and room), when it started, and what you have already tried." required><?= e($old['description']) ?></textarea>
                <p class="hint">Never include your password. IT Services will never ask for it.</p>
            </div>
            <div class="actions mt-1">
                <button type="submit" class="btn btn-primary">Submit ticket</button>
            </div>
        </form>
    </section>

    <div class="stack">
        <section class="card">
            <div class="section-title">
                <h2>Recent requests</h2>
                <a href="my_tickets.php" class="small">View all</a>
            </div>
            <?php if (!$recent): ?>
                <p class="muted small">You haven&rsquo;t filed any tickets yet. Your requests will appear here.</p>
            <?php else: ?>
                <ul class="request-list">
                    <?php foreach ($recent as $t): ?>
                        <li>
                            <div>
                                <p class="request-title"><?= e($t['subject']) ?></p>
                                <p class="request-meta">#<?= (int) $t['id'] ?> &middot; <?= e(date('M j', strtotime($t['created_at']))) ?></p>
                            </div>
                            <span class="badge badge-dot <?= e(badge_class($t['status'])) ?>"><?= e($t['status']) ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>

        <section class="card card-note">
            <h2><?= icon('info') ?> Tips for a faster fix</h2>
            <ul class="tips mt-2">
                <li><?= icon('check') ?><span>Include the building, room number and the device you are using.</span></li>
                <li><?= icon('check') ?><span>Copy any error message exactly as it appears on screen.</span></li>
                <li><?= icon('check') ?><span>Use <strong>High</strong> or <strong>Critical</strong> only when classes or work are blocked.</span></li>
            </ul>
        </section>
    </div>
</div>
