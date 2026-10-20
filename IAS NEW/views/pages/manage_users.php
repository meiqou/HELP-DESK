<div class="page-header">
    <div>
        <p class="eyebrow">Administration</p>
        <h1>User accounts</h1>
        <p class="muted"><?= count($users) ?> accounts &middot; changes take effect immediately and are recorded in the audit log</p>
    </div>
</div>

<div class="grid grid-4">
    <?= stat_card('Accounts', count($users), 'users', 'gray') ?>
    <?= stat_card('Staff with privileges', count(array_filter($users, fn($u) => $u['role'] !== 'user')), 'shield', 'gold', 'Technicians and administrators') ?>
    <?= stat_card('Two-factor enabled', count(array_filter($users, fn($u) => (bool) $u['totp_enabled'])), 'lock', 'green') ?>
    <?= stat_card('Deactivated', count(array_filter($users, fn($u) => !$u['is_active'])), 'alert', 'red') ?>
</div>

<section class="card card-flush">
    <div class="toolbar">
        <input class="input" type="search" placeholder="Filter by name, e-mail or role" aria-label="Filter users" data-filter=".user-row">
    </div>
    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th>User</th>
                    <th>Role</th>
                    <th>Security</th>
                    <th>Activity</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $u):
                    $self = (int) $u['id'] === (int) $admin['id'];
                    $locked = $u['locked'];
                ?>
                    <tr class="user-row" data-search="<?= e($u['username'] . ' ' . $u['email'] . ' ' . App\Auth\Rbac::label($u['role']) . ' ' . ($u['is_active'] ? 'active' : 'deactivated')) ?>">
                        <td>
                            <p class="cell-title"><?= e($u['username']) ?><?= $self ? ' <span class="muted small">(you)</span>' : '' ?></p>
                            <p class="cell-sub"><?= e($u['email']) ?></p>
                        </td>
                        <td>
                            <?php if ($self): ?>
                                <span class="badge <?= e(badge_class($u['role'])) ?>"><?= e(App\Auth\Rbac::label($u['role'])) ?></span>
                            <?php else: ?>
                                <form method="POST" class="inline-form" data-confirm="Change this user's role? They will be signed out.">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="role">
                                    <input type="hidden" name="user_id" value="<?= (int) $u['id'] ?>">
                                    <select class="select select-sm" name="role" aria-label="Role for <?= e($u['username']) ?>">
                                        <?php foreach (App\Auth\Rbac::ROLES as $value => $label): ?>
                                            <option value="<?= e($value) ?>"<?= $u['role'] === $value ? ' selected' : '' ?>><?= e($label) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button type="submit" class="btn btn-secondary btn-sm">Save</button>
                                </form>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="row-actions">
                                <span class="badge <?= $u['is_active'] ? 'badge-on' : 'badge-inactive' ?>"><?= $u['is_active'] ? 'Active' : 'Deactivated' ?></span>
                                <span class="badge <?= $u['totp_enabled'] ? 'badge-on' : 'badge-off' ?>">2FA <?= $u['totp_enabled'] ? 'on' : 'off' ?></span>
                                <?php if ($locked): ?><span class="badge badge-critical">Locked</span><?php endif; ?>
                            </div>
                        </td>
                        <td>
                            <p class="cell-sub nowrap">Joined <?= e(date('M j, Y', strtotime($u['created_at']))) ?></p>
                            <p class="cell-sub nowrap">Last sign-in <?= e($u['last_login_at'] ? date('M j, g:i A', strtotime($u['last_login_at'])) : 'never') ?></p>
                            <p class="cell-sub"><?= (int) $u['ticket_count'] ?> ticket<?= (int) $u['ticket_count'] === 1 ? '' : 's' ?></p>
                        </td>
                        <td>
                            <?php if ($self): ?>
                                <a href="account.php" class="small">Manage in Account</a>
                            <?php else: ?>
                                <div class="row-actions">
                                    <?php if ($locked): ?>
                                        <form method="POST"><?= csrf_field() ?><input type="hidden" name="action" value="unlock"><input type="hidden" name="user_id" value="<?= (int) $u['id'] ?>"><button type="submit" class="btn-link">Unlock</button></form>
                                    <?php endif; ?>
                                    <form method="POST" data-confirm="<?= $u['is_active'] ? 'Deactivate this account and sign the user out?' : 'Reactivate this account?' ?>">
                                        <?= csrf_field() ?><input type="hidden" name="action" value="toggle_active"><input type="hidden" name="user_id" value="<?= (int) $u['id'] ?>">
                                        <button type="submit" class="btn-link"><?= $u['is_active'] ? 'Deactivate' : 'Reactivate' ?></button>
                                    </form>
                                    <form method="POST" data-confirm="Sign this user out of every device?">
                                        <?= csrf_field() ?><input type="hidden" name="action" value="revoke_sessions"><input type="hidden" name="user_id" value="<?= (int) $u['id'] ?>">
                                        <button type="submit" class="btn-link">Sign out</button>
                                    </form>
                                    <?php if ($u['totp_enabled']): ?>
                                        <form method="POST" data-confirm="Reset two-factor authentication for this user?">
                                            <?= csrf_field() ?><input type="hidden" name="action" value="reset_2fa"><input type="hidden" name="user_id" value="<?= (int) $u['id'] ?>">
                                            <button type="submit" class="btn-link">Reset 2FA</button>
                                        </form>
                                    <?php endif; ?>
                                    <form method="POST" data-confirm="Permanently delete <?= e($u['username']) ?> and all of their tickets?">
                                        <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="user_id" value="<?= (int) $u['id'] ?>">
                                        <button type="submit" class="btn-link danger">Delete</button>
                                    </form>
                                </div>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
