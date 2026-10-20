<?php
$primaryHref = $user ? home_for($user) : 'register.php';
$primaryLabel = $user ? 'Open my dashboard' : 'Get started';

$categories = [
    ['Wi-Fi and network', 'Connectivity and campus network access.', '<path d="M5 12.55a11 11 0 0 1 14 0"/><path d="M1.42 9a16 16 0 0 1 21.16 0"/><path d="M8.53 16.11a6 6 0 0 1 6.95 0"/><path d="M12 20h.01"/>'],
    ['LMS and portal', 'Canvas, the student portal and enrollment.', '<path d="M22 10 12 5 2 10l10 5 10-5z"/><path d="M6 12v5c3 2 9 2 12 0v-5"/>'],
    ['Computer labs', 'Lab PCs, projectors and printers.', '<rect x="2" y="3" width="20" height="14" rx="2"/><path d="M8 21h8M12 17v4"/>'],
    ['Software', 'Microsoft 365 and campus applications.', '<path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><path d="M3.3 7 12 12l8.7-5M12 22V12"/>'],
    ['Accounts', 'Institutional account access and resets.', '<circle cx="8" cy="15" r="4"/><path d="m10.85 12.15 8.65-8.65M18 5l3 3M15 8l2 2"/>'],
    ['Anything else', 'Not sure where it fits? File it under Other.', '<circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3M12 17h.01"/>'],
];
?>

<!-- Hero -->
<section class="hp-hero">
    <div class="hp-wrap hp-hero-copy">
        <p class="hp-pill"><img src="assets/img/star.svg" alt=""> Information Technology Services &middot; USLS Bacolod</p>
        <h1>IT support for the <span>Lasallian</span> community.</h1>
        <p class="hp-lead">Report campus Wi-Fi, LMS, laboratory, software and account issues in one secure place, and follow every request until it&rsquo;s resolved.</p>
        <div class="hp-actions">
            <a href="<?= e($primaryHref) ?>" class="btn btn-primary btn-lg"><?= e($primaryLabel) ?></a>
            <?php if (!$user): ?>
                <a href="login.php" class="btn btn-secondary btn-lg">Sign in</a>
            <?php endif; ?>
        </div>
    </div>

    <div class="hp-wrap">
        <div class="hp-preview" aria-hidden="true">
            <div class="hp-preview-side">
                <div class="hp-preview-brand"><img src="assets/img/star.svg" alt=""> IT <b>Helpdesk</b></div>
                <span class="is-active">Dashboard</span>
                <span>My tickets</span>
                <span>Security settings</span>
            </div>
            <div class="hp-preview-main">
                <p class="hp-preview-title">Good morning, student</p>
                <div class="hp-preview-stats">
                    <div><small>Open</small><strong>1</strong></div>
                    <div><small>In progress</small><strong>2</strong></div>
                    <div><small>Resolved</small><strong>9</strong></div>
                </div>
                <div class="hp-preview-rows">
                    <div><span>No connection on library 3rd floor</span><em class="badge badge-dot badge-in-progress">In progress</em></div>
                    <div><span>Canvas course page shows 403</span><em class="badge badge-dot badge-open">Open</em></div>
                    <div><span>Room 204 &middot; PC 14 keyboard</span><em class="badge badge-dot badge-resolved">Resolved</em></div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Categories -->
<section class="hp-section">
    <div class="hp-wrap hp-split">
        <div class="hp-split-intro">
            <p class="eyebrow">What we can help with</p>
            <h2>Any campus technology issue, one place to report it.</h2>
            <p class="muted">Pick a category and ITS routes your request to the right technician.</p>
        </div>
        <ul class="hp-cats">
            <?php foreach ($categories as [$title, $text, $paths]): ?>
                <li>
                    <span class="hp-cat-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><?= $paths ?></svg></span>
                    <div><h3><?= e($title) ?></h3><p><?= e($text) ?></p></div>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
</section>

<!-- How it works -->
<section class="hp-section hp-section-line">
    <div class="hp-wrap">
        <div class="hp-head">
            <p class="eyebrow">How it works</p>
            <h2>Three steps from report to resolution.</h2>
        </div>
        <ol class="hp-steps">
            <li><span>01</span><h3>Submit a ticket</h3><p>Choose a category and priority, then describe the problem.</p></li>
            <li><span>02</span><h3>A technician takes it</h3><p>Urgent issues come first. Your ticket moves to In Progress.</p></li>
            <li><span>03</span><h3>Track it to the end</h3><p>Follow the status from your dashboard until it&rsquo;s resolved.</p></li>
        </ol>
    </div>
</section>

<!-- Security -->
<section class="hp-section hp-section-tint">
    <div class="hp-wrap">
        <div class="hp-head">
            <p class="eyebrow">Secure by design</p>
            <h2>Your account is protected at every step.</h2>
        </div>
        <div class="hp-pillars">
            <div>
                <h3>Password hashing</h3>
                <p>Passwords are stored with Argon2id and a secret pepper. Never in plain text.</p>
            </div>
            <div>
                <h3>Authentication</h3>
                <p>Optional two-factor sign-in, automatic lockout and short, hijack-resistant sessions.</p>
            </div>
            <div>
                <h3>Authorization</h3>
                <p>Student, technician and admin roles, checked on every request and fully audited.</p>
            </div>
        </div>
    </div>
</section>

<!-- Call to action -->
<section class="hp-section hp-cta">
    <div class="hp-wrap">
        <img src="assets/img/star.svg" alt="" aria-hidden="true">
        <h2>Having trouble with campus technology?</h2>
        <p class="muted">File a ticket in under a minute. We&rsquo;ll take it from there.</p>
        <a href="<?= e($primaryHref) ?>" class="btn btn-primary btn-lg"><?= $user ? 'Open my dashboard' : 'Create your account' ?></a>
    </div>
</section>
