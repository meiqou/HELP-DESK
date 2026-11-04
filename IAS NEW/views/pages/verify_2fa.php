<p class="eyebrow">Step 2 of 2</p>
<h1>Verify it&rsquo;s you</h1>
<?php if ($byEmail): ?>
    <p class="subtitle">Enter the 6-digit code e-mailed to <strong><?= e($maskedEmail) ?></strong>. It expires in 10 minutes. Check your spam folder if you don&rsquo;t see it.</p>
<?php else: ?>
    <p class="subtitle">Enter the 6-digit code from your authenticator app for <strong><?= e($account['username']) ?></strong>.</p>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-error" role="alert"><?= e($error) ?></div>
<?php endif; ?>
<?php if ($notice): ?>
    <div class="alert alert-success" role="status"><?= e($notice) ?></div>
<?php endif; ?>

<form method="POST" class="form-stack" autocomplete="off">
    <?= csrf_field() ?>
    <div class="field">
        <label for="code">Authentication code</label>
        <input class="input input-code" type="text" id="code" name="code" inputmode="numeric"
               autocomplete="one-time-code" maxlength="13" placeholder="000000" required autofocus>
        <p class="hint">Lost your device? Enter one of your recovery codes (format XXXXXX-XXXXXX) instead.</p>
    </div>
    <button type="submit" class="btn btn-primary btn-block">Verify and sign in</button>
</form>

<?php if ($byEmail): ?>
    <form method="POST" class="auth-switch">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="resend">
        <button type="submit" class="btn-link" data-no-lock>Send a new code</button>
    </form>
<?php endif; ?>

<form method="POST" action="logout.php" class="auth-switch">
    <?= csrf_field() ?>
    <button type="submit" class="btn-link">Cancel and use a different account</button>
</form>
