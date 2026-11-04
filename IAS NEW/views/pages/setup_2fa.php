<p class="eyebrow">Required step</p>
<h1>Set up two-factor sign-in</h1>
<p class="subtitle">Every helpdesk account must use an authenticator app. You only need to do this once.</p>

<?php if ($error): ?>
    <div class="alert alert-error" role="alert"><?= e($error) ?></div>
<?php endif; ?>

<ol class="steps">
    <li>
        <p><strong>Install an authenticator app</strong></p>
        <p class="muted small">Google Authenticator, Microsoft Authenticator or Authy.</p>
    </li>
    <li>
        <p><strong>Add this key to the app</strong></p>
        <p class="muted small">Choose &ldquo;Enter a setup key&rdquo;, name it <em>USLS IT Helpdesk</em>, and keep the type as time-based.</p>
        <p class="secret-key mt-1" id="totp-secret"><?= e(trim(chunk_split($secret, 4, ' '))) ?></p>
        <div class="actions mt-1">
            <button type="button" class="btn btn-secondary btn-sm" data-copy="#totp-secret">Copy key</button>
            <a class="btn btn-ghost btn-sm" href="<?= e(App\Security\Totp::uri($secret, $account['username'])) ?>">Open in app</a>
        </div>
    </li>
    <li>
        <p><strong>Enter the 6-digit code it shows</strong></p>
        <form method="POST" class="form-stack mt-1" autocomplete="off">
            <?= csrf_field() ?>
            <div class="field">
                <label for="code" class="sr-only">Authentication code</label>
                <input class="input input-code" type="text" id="code" name="code" inputmode="numeric"
                       autocomplete="one-time-code" maxlength="12" placeholder="000000" required autofocus data-otp>
            </div>
            <button type="submit" class="btn btn-primary btn-block">Verify and continue</button>
        </form>
    </li>
</ol>

<form method="POST" action="logout.php" class="auth-switch">
    <?= csrf_field() ?>
    <button type="submit" class="btn-link">Cancel and sign out</button>
</form>
