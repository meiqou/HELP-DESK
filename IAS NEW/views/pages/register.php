<h1>Create your account</h1>
<p class="subtitle">For USLS students, faculty and staff.</p>

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

<form method="POST" class="form-stack">
    <?= csrf_field() ?>

    <div class="field-row">
        <div class="field">
            <label for="first_name">First name</label>
            <input class="input" type="text" id="first_name" name="first_name" value="<?= e($firstName) ?>"
                   autocomplete="given-name" maxlength="100" required autofocus>
        </div>
        <div class="field">
            <label for="last_name">Last name</label>
            <input class="input" type="text" id="last_name" name="last_name" value="<?= e($lastName) ?>"
                   autocomplete="family-name" maxlength="100" required>
        </div>
    </div>

    <div class="field">
        <label for="username">Username</label>
        <input class="input" type="text" id="username" name="username" value="<?= e($username) ?>"
               autocomplete="username" minlength="3" maxlength="30" pattern="[A-Za-z0-9._\-]{3,30}" required>
    </div>

    <div class="field">
        <label for="email">E-mail address</label>
        <input class="input" type="email" id="email" name="email" value="<?= e($email) ?>"
               autocomplete="email" maxlength="254" placeholder="name@usls.edu.ph" required>
    </div>

    <div class="field">
        <label for="password">Password</label>
        <div class="password-wrap">
            <input class="input" type="password" id="password" name="password" autocomplete="new-password"
                   minlength="<?= (int) $sec['password_min_length'] ?>" maxlength="<?= (int) $sec['password_max_length'] ?>" required data-strength data-min="<?= (int) $sec['password_min_length'] ?>" data-common="<?= e(json_encode(App\Security\PasswordPolicy::COMMON)) ?>">
            <button type="button" class="password-toggle" data-target="password" aria-pressed="false">Show</button>
        </div>
        <div class="meter" aria-live="polite">
            <div class="meter-track"><div class="meter-fill"></div></div>
            <p class="meter-label">Enter a password</p>
        </div>
        <ul class="checklist">
            <li data-rule="length"><?= (int) $sec['password_min_length'] ?>+ characters</li>
            <li data-rule="upper">Uppercase letter</li>
            <li data-rule="lower">Lowercase letter</li>
            <li data-rule="number">Number</li>
            <li data-rule="symbol">Symbol</li>
            <li data-rule="common">Not a common password</li>
        </ul>
        <p class="hint">Common passwords such as password123, qwerty123 and admin123 are not allowed. Type your password; copy and paste is turned off.</p>
    </div>

    <div class="field">
        <label for="password_confirm">Confirm password</label>
        <input class="input" type="password" id="password_confirm" name="password_confirm"
               autocomplete="new-password" maxlength="<?= (int) $sec['password_max_length'] ?>" required data-match="password">
    </div>

    <button type="submit" class="btn btn-primary btn-block">Create account</button>
</form>

<p class="auth-switch">Already have an account? <a href="login.php">Sign in</a></p>
