<p class="eyebrow">Required step</p>
<h1>Set up two-factor sign-in</h1>
<p class="subtitle">Every helpdesk account needs a second step at sign-in. Choose how you want to get your code.</p>

<?php if ($error): ?>
    <div class="alert alert-error" role="alert"><?= e($error) ?></div>
<?php endif; ?>
<?php if ($notice): ?>
    <div class="alert alert-success" role="status"><?= e($notice) ?></div>
<?php endif; ?>

<?php if ($emailAvailable): ?>
    <nav class="method-tabs" aria-label="Two-factor method">
        <a href="setup_2fa.php" class="method-tab<?= $method === 'app' ? ' is-active' : '' ?>"<?= $method === 'app' ? ' aria-current="page"' : '' ?>>
            <strong>Authenticator app</strong><small>Scan a QR code. Recommended.</small>
        </a>
        <a href="setup_2fa.php?method=email" class="method-tab<?= $method === 'email' ? ' is-active' : '' ?>"<?= $method === 'email' ? ' aria-current="page"' : '' ?>>
            <strong>E-mail</strong><small>Get a code in your inbox.</small>
        </a>
    </nav>
<?php endif; ?>

<?php if ($method === 'email'): ?>
    <ol class="steps">
        <li>
            <p><strong>Send a code to your e-mail</strong></p>
            <p class="muted small">It will go to <strong><?= e($maskedEmail) ?></strong>, the address on your account.</p>
            <form method="POST" action="setup_2fa.php?method=email" class="mt-1">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="send_email">
                <button type="submit" class="btn btn-secondary btn-sm" data-no-lock><?= $emailSent ? 'Send a new code' : 'Send code' ?></button>
            </form>
        </li>
        <li>
            <p><strong>Enter the 6-digit code</strong></p>
            <form method="POST" action="setup_2fa.php?method=email" class="form-stack mt-1" autocomplete="off">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="verify_email">
                <div class="field">
                    <label for="code" class="sr-only">Code from your e-mail</label>
                    <input class="input input-code" type="text" id="code" name="code" inputmode="numeric"
                           autocomplete="one-time-code" maxlength="12" placeholder="000000" required data-otp<?= $emailSent ? ' autofocus' : ' disabled' ?>>
                </div>
                <button type="submit" class="btn btn-primary btn-block"<?= $emailSent ? '' : ' disabled' ?>>Verify and continue</button>
            </form>
        </li>
    </ol>
<?php else: ?>
    <ol class="steps">
        <li>
            <p><strong>Install an authenticator app</strong></p>
            <p class="muted small">Google Authenticator, Microsoft Authenticator or Authy.</p>
        </li>
        <li>
            <p><strong>Scan this QR code with the app</strong></p>
            <div class="qr-box mt-1" data-qr="<?= e(App\Security\Totp::uri($secret, $account['username'])) ?>" role="img" aria-label="QR code for your authenticator app"></div>
            <details class="disclosure mt-1">
                <summary>Can&rsquo;t scan it? Enter a key instead</summary>
                <p class="muted small mt-1">Choose &ldquo;Enter a setup key&rdquo;, name it <em>USLS IT Helpdesk</em>, and keep the type as time-based.</p>
                <p class="secret-key mt-1" id="totp-secret"><?= e(trim(chunk_split($secret, 4, ' '))) ?></p>
                <div class="actions mt-1">
                    <button type="button" class="btn btn-secondary btn-sm" data-copy="#totp-secret">Copy key</button>
                    <a class="btn btn-ghost btn-sm" href="<?= e(App\Security\Totp::uri($secret, $account['username'])) ?>">Open in app</a>
                </div>
            </details>
        </li>
        <li>
            <p><strong>Enter the 6-digit code it shows</strong></p>
            <form method="POST" action="setup_2fa.php" class="form-stack mt-1" autocomplete="off">
                <?= csrf_field() ?>
                <div class="field">
                    <label for="code" class="sr-only">Authentication code</label>
                    <input class="input input-code" type="text" id="code" name="code" inputmode="numeric"
                           autocomplete="one-time-code" maxlength="12" placeholder="000000" required data-otp>
                </div>
                <button type="submit" class="btn btn-primary btn-block">Verify and continue</button>
            </form>
        </li>
    </ol>
<?php endif; ?>

<form method="POST" action="logout.php" class="auth-switch">
    <?= csrf_field() ?>
    <button type="submit" class="btn-link">Cancel and sign out</button>
</form>
