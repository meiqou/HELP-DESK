<h1>Forgot your password?</h1>
<p class="subtitle">We’ll send a one-time code to the e-mail address on your account.</p>

<?php if ($error): ?>
    <div class="alert alert-error" role="alert"><?= e($error) ?></div>
<?php endif; ?>
<?php if ($notice): ?>
    <div class="alert alert-info" role="status"><?= e($notice) ?></div>
<?php endif; ?>
<?php if ($errors): ?>
    <div class="alert alert-error" role="alert">
        Please fix the following:
        <ul>
            <?php foreach (array_unique($errors) as $err): ?>
                <li><?= e($err) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<?php if ($resetStep): ?>
    <p class="hint">Enter the 6-digit code sent to <?= e(App\Auth\EmailCodes::mask($email)) ?>. The code expires after 10 minutes.</p>
    <form method="POST" class="form-stack">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="reset_password">

        <div class="field">
            <label for="code">E-mail code</label>
            <input class="input" type="text" id="code" name="code" inputmode="numeric" autocomplete="one-time-code"
                   pattern="[0-9]{6}" maxlength="6" required autofocus>
        </div>

        <div class="field">
            <label for="new_password">New password</label>
            <div class="password-wrap">
                <input class="input" type="password" id="new_password" name="new_password" autocomplete="new-password"
                       minlength="<?= (int) $sec['password_min_length'] ?>" maxlength="<?= (int) $sec['password_max_length'] ?>" required>
                <button type="button" class="password-toggle" data-target="new_password" aria-pressed="false">Show</button>
            </div>
            <p class="hint">Use at least <?= (int) $sec['password_min_length'] ?> characters with uppercase and lowercase letters, a number, and a symbol.</p>
        </div>

        <div class="field">
            <label for="confirm_password">Confirm new password</label>
            <input class="input" type="password" id="confirm_password" name="confirm_password" autocomplete="new-password"
                   maxlength="<?= (int) $sec['password_max_length'] ?>" required>
        </div>

        <button type="submit" class="btn btn-primary btn-block">Reset password</button>
    </form>

    <div class="auth-switch">
        <form method="POST">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="resend_code">
            <button type="submit" class="btn btn-secondary">Send another code</button>
        </form>
        <form method="POST" class="mt-2">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="change_email">
            <button type="submit" class="btn btn-secondary">Use a different e-mail</button>
        </form>
    </div>
<?php else: ?>
    <form method="POST" class="form-stack">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="request_code">

        <div class="field">
            <label for="email">E-mail address</label>
            <input class="input" type="email" id="email" name="email" value="<?= e($email) ?>"
                   autocomplete="email" maxlength="254" required autofocus>
        </div>

        <button type="submit" class="btn btn-primary btn-block">Send reset code</button>
    </form>
<?php endif; ?>

<p class="auth-switch"><a href="login.php">Back to sign in</a></p>
