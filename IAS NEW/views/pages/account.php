<div class="page-header">
    <div>
        <p class="eyebrow">Account</p>
        <h1>Security settings</h1>
        <p class="muted">Manage how you sign in to the helpdesk.</p>
    </div>
    <a href="#two-factor" class="btn btn-secondary"><?= icon('shield') ?> Two-factor sign-in</a>
</div>

<div class="grid grid-3">
    <?= stat_card('Two-factor', $totpEnabled ? 'On' : 'Off', 'shield', $totpEnabled ? 'green' : 'gold', $totpEnabled ? 'Your account has two layers of protection' : 'Turn it on for stronger protection') ?>
    <?= stat_card('Password last changed', $user['password_changed_at'] ? date('M j, Y', strtotime($user['password_changed_at'])) : 'Never', 'key', 'gray') ?>
    <?= stat_card('Session timeout', $idleMinutes . ' min', 'clock', 'blue', 'Signed out automatically when idle') ?>
</div>

<div class="grid grid-sidebar">
    <div>
        <!-- Change password -->
        <section class="card" id="password">
            <div class="card-header">
                <h2>Password</h2>
                <p class="muted small">Last changed <?= e(format_date($user['password_changed_at'])) ?>. Changing it signs out your other devices.</p>
            </div>
            <?= $section === 'password' ? form_errors($errors) : '' ?>
            <form method="POST" class="form-stack">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="change_password">
                <input type="text" name="username" value="<?= e($user['username']) ?>" autocomplete="username" hidden>
                <div class="field">
                    <label for="current_password">Current password</label>
                    <input class="input" type="password" id="current_password" name="current_password" autocomplete="current-password" maxlength="128" required>
                </div>
                <div class="field">
                    <label for="new_password">New password</label>
                    <div class="password-wrap">
                        <input class="input" type="password" id="new_password" name="new_password" autocomplete="new-password"
                               minlength="<?= (int) $sec['password_min_length'] ?>" maxlength="<?= (int) $sec['password_max_length'] ?>" required data-strength data-min="<?= (int) $sec['password_min_length'] ?>">
                        <button type="button" class="password-toggle" data-target="new_password" aria-pressed="false">Show</button>
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
                    </ul>
                </div>
                <div class="field">
                    <label for="confirm_password">Confirm new password</label>
                    <input class="input" type="password" id="confirm_password" name="confirm_password" autocomplete="new-password" maxlength="128" required data-match="new_password">
                </div>
                <div><button type="submit" class="btn btn-primary">Update password</button></div>
            </form>
        </section>

        <!-- Two-factor authentication -->
        <section class="card" id="two-factor">
            <div class="card-header">
                <div class="section-title">
                    <h2>Two-factor authentication</h2>
                    <span class="badge <?= $totpEnabled ? 'badge-on' : 'badge-off' ?>"><?= $totpEnabled ? 'On' : 'Off' ?></span>
                </div>
                <p class="muted small">Require a 6-digit code from an authenticator app (Google Authenticator, Microsoft Authenticator, Authy) in addition to your password.</p>
            </div>
            <?= $section === '2fa' ? form_errors($errors) : '' ?>

            <?php if ($newCodes): ?>
                <div class="alert alert-warning">
                    <strong>Save your recovery codes now.</strong> Each code can be used once if you lose your phone. They will not be shown again.
                </div>
                <div class="codes" id="recovery-codes">
                    <?php foreach ($newCodes as $code): ?><span><?= e($code) ?></span><?php endforeach; ?>
                </div>
                <div class="actions mt-2">
                    <button type="button" class="btn btn-secondary btn-sm" data-copy="#recovery-codes">Copy codes</button>
                </div>
                <hr class="divider">
            <?php endif; ?>

            <?php if ($totpEnabled): ?>
                <p class="small">Recovery codes remaining: <strong><?= (int) $remainingCodes ?></strong> of 8</p>
                <div class="grid grid-2 mt-3">
                    <form method="POST" class="form-stack">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="2fa_new_codes">
                        <div class="field">
                            <label for="pw_codes">Password</label>
                            <input class="input" type="password" id="pw_codes" name="current_password" autocomplete="current-password" required>
                        </div>
                        <button type="submit" class="btn btn-secondary">Generate new recovery codes</button>
                    </form>
                    <form method="POST" class="form-stack" data-confirm="Turn off two-factor authentication?">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="2fa_disable">
                        <div class="field">
                            <label for="pw_disable">Password</label>
                            <input class="input" type="password" id="pw_disable" name="current_password" autocomplete="current-password" required>
                        </div>
                        <button type="submit" class="btn btn-danger">Turn off two-factor</button>
                    </form>
                </div>
            <?php elseif ($pendingSecret): ?>
                <ol class="steps">
                    <li>
                        <p><strong>Add this account to your authenticator app.</strong></p>
                        <p class="muted small">Choose &ldquo;Enter a setup key&rdquo;, use <em>USLS IT Helpdesk</em> as the name, and paste this key (time-based):</p>
                        <p class="secret-key mt-1" id="totp-secret"><?= e(trim(chunk_split($pendingSecret, 4, ' '))) ?></p>
                        <div class="actions mt-1">
                            <button type="button" class="btn btn-secondary btn-sm" data-copy="#totp-secret">Copy key</button>
                            <a class="btn btn-ghost btn-sm" href="<?= e(App\Security\Totp::uri($pendingSecret, $user['username'])) ?>">Open in authenticator app</a>
                        </div>
                    </li>
                    <li>
                        <p><strong>Enter the 6-digit code it shows.</strong></p>
                        <form method="POST" class="form-stack mt-1">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="2fa_enable">
                            <div class="field">
                                <label for="code" class="sr-only">Verification code</label>
                                <input class="input input-code" type="text" id="code" name="code" inputmode="numeric" autocomplete="one-time-code" maxlength="12" placeholder="000000" required data-otp>
                            </div>
                            <div class="actions">
                                <button type="submit" class="btn btn-primary">Verify and turn on</button>
                            </div>
                        </form>
                        <form method="POST" class="mt-1">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="2fa_cancel">
                            <button type="submit" class="btn-link">Cancel setup</button>
                        </form>
                    </li>
                </ol>
            <?php else: ?>
                <form method="POST" class="form-stack">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="2fa_start">
                    <div class="field">
                        <label for="pw_setup">Confirm your password to begin</label>
                        <input class="input" type="password" id="pw_setup" name="current_password" autocomplete="current-password" maxlength="128" required>
                    </div>
                    <div><button type="submit" class="btn btn-primary">Set up two-factor</button></div>
                </form>
            <?php endif; ?>
        </section>

        <!-- Sessions -->
        <section class="card" id="sessions">
            <div class="card-header">
                <h2>Sessions</h2>
                <p class="muted small">Sessions end after <?= $idleMinutes ?> minutes of inactivity. Lost a device or used a shared computer? Sign out everywhere else.</p>
            </div>
            <form method="POST" data-confirm="Sign out of every other device?">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="revoke_sessions">
                <button type="submit" class="btn btn-secondary">Sign out other sessions</button>
            </form>
        </section>
    </div>

    <aside>
        <section class="card">
            <h2>Profile</h2>
            <ul class="list-plain mt-2">
                <li><span class="muted">Username</span><span><?= e($user['username']) ?></span></li>
                <li><span class="muted">E-mail</span><span><?= e($user['email']) ?></span></li>
                <li><span class="muted">Role</span><span class="badge <?= e(badge_class($user['role'])) ?>"><?= e(App\Auth\Rbac::label($user['role'])) ?></span></li>
                <li><span class="muted">Member since</span><span><?= e(date('M j, Y', strtotime($user['created_at']))) ?></span></li>
            </ul>
        </section>
        <section class="card">
            <h2>Recent activity</h2>
            <?php if (!$activity): ?>
                <p class="muted small mt-2">No activity recorded yet.</p>
            <?php else: ?>
                <ul class="list-plain mt-2">
                    <?php foreach ($activity as $a): ?>
                        <li>
                            <span>
                                <span class="<?= $a['event'] === 'login_failed' ? 'badge badge-critical' : 'cell-title' ?>"><?= e(ucfirst(str_replace('_', ' ', $a['event']))) ?></span><br>
                                <span class="muted small"><?= e($a['ip']) ?></span>
                            </span>
                            <span class="muted small nowrap"><?= e(date('M j, g:i A', strtotime($a['created_at']))) ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
            <p class="hint mt-2">Don&rsquo;t recognise something? Change your password and sign out other sessions.</p>
        </section>
    </aside>
</div>
