<div class="container-narrow">
    <section class="card card-brand">
        <div class="card-header">
            <p class="eyebrow">Security check</p>
            <h1>Confirm your password</h1>
            <p class="muted mt-1">You&rsquo;re about to access a sensitive area. For your protection, please re-enter your password to continue.</p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-error" role="alert"><?= e($error) ?></div>
        <?php endif; ?>

        <form method="POST" class="form-stack">
            <?= csrf_field() ?>
            <input type="text" name="username" value="<?= e($user['username']) ?>" autocomplete="username" hidden>
            <div class="field">
                <label for="password">Password</label>
                <div class="password-wrap">
                    <input class="input" type="password" id="password" name="password" autocomplete="current-password" maxlength="128" required autofocus>
                    <button type="button" class="password-toggle" data-target="password" aria-pressed="false">Show</button>
                </div>
            </div>
            <div class="actions">
                <button type="submit" class="btn btn-primary">Confirm</button>
                <a href="<?= e(home_for($user)) ?>" class="btn btn-ghost">Cancel</a>
            </div>
        </form>
    </section>
</div>
