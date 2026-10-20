<h1>Welcome back</h1>
<p class="subtitle">Sign in with your helpdesk account to file and track support requests.</p>

<?php if ($error): ?>
    <div class="alert alert-error" role="alert"><?= e($error) ?></div>
<?php endif; ?>

<form method="POST" class="form-stack" autocomplete="on" novalidate>
    <?= csrf_field() ?>

    <div class="field">
        <label for="username">Username or e-mail</label>
        <input class="input" type="text" id="username" name="username" value="<?= e($username) ?>"
               autocomplete="username" maxlength="254" required autofocus>
    </div>

    <div class="field">
        <label for="password">Password</label>
        <div class="password-wrap">
            <input class="input" type="password" id="password" name="password"
                   autocomplete="current-password" maxlength="128" required>
            <button type="button" class="password-toggle" data-target="password" aria-pressed="false">Show</button>
        </div>
    </div>

    <button type="submit" class="btn btn-primary btn-block">Sign in</button>
</form>

<p class="auth-switch">New to the helpdesk? <a href="register.php">Create an account</a></p>

<p class="auth-secure-note">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg>
    Protected by secure sessions and brute-force lockout
</p>
