<?php
require dirname(__DIR__) . '/src/bootstrap.php';

use App\Auth\Auth;
use App\Auth\RecoveryCodes;
use App\Core\Config;
use App\Repositories\UserRepository;
use App\Security\AuditLog;
use App\Security\Encryptor;
use App\Security\PasswordHasher;
use App\Security\PasswordPolicy;
use App\Security\Totp;

$user = Auth::requireLogin();
$userId = (int) $user['id'];
$errors = [];
$section = '';

if (is_post()) {
    verify_csrf();
    $current = post_raw('current_password');

    // Every password check on this page is rate limited; too many wrong guesses end the session.
    $passwordOk = static function (string $failureEvent) use ($user, $current): bool {
        $result = Auth::verifyCurrentPassword($user, $current, $failureEvent);
        if ($result === 'signed_out') {
            redirect('login.php');
        }
        return $result === 'ok';
    };

    switch (post_string('action')) {
        case 'change_password':
            $section = 'password';
            $new = post_raw('new_password');

            if (!$passwordOk('password_change_failed')) {
                $errors[] = 'Your current password is incorrect.';
                break;
            }
            if (!hash_equals($new, post_raw('confirm_password'))) {
                $errors[] = 'The new passwords do not match.';
            }
            if (hash_equals($current, $new)) {
                $errors[] = 'Choose a password different from your current one.';
            }
            $errors = array_merge($errors, PasswordPolicy::errors($new, $user['username'], $user['email']));
            if ($errors) {
                break;
            }
            UserRepository::updatePasswordHash($userId, PasswordHasher::hash($new), true);
            Auth::signOutOtherSessions($userId);
            AuditLog::record('password_changed', 'Other sessions signed out');
            flash('success', 'Your password was changed and all other devices were signed out.');
            redirect('account.php');

        case '2fa_start':
            // Setting up a second factor requires the password, so a hijacked or unattended
            // session cannot enrol the attacker's own authenticator.
            $section = '2fa';
            if ($user['totp_enabled']) {
                $errors[] = 'Two-factor authentication is already on. Turn it off first to use a different device.';
                break;
            }
            if (!$passwordOk('2fa_setup_failed')) {
                $errors[] = 'Your password is incorrect.';
                break;
            }
            $_SESSION['pending_totp_secret'] = Totp::generateSecret();
            redirect('account.php#two-factor');

        case '2fa_enable':
            $section = '2fa';
            $secret = $_SESSION['pending_totp_secret'] ?? null;
            if ($user['totp_enabled'] || $secret === null) {
                $errors[] = 'Start the setup again.';
                unset($_SESSION['pending_totp_secret']);
                break;
            }
            $step = Totp::verify($secret, post_string('code'));
            if ($step === null) {
                $errors[] = 'That code did not match. Make sure your device clock is correct and try again.';
                break;
            }
            UserRepository::enableTotp($userId, Encryptor::encrypt($secret), $step);
            unset($_SESSION['pending_totp_secret']);
            $_SESSION['show_recovery_codes'] = RecoveryCodes::regenerate($userId);
            AuditLog::record('2fa_enabled');
            flash('success', 'Two-factor authentication is now on.');
            redirect('account.php#two-factor');

        case '2fa_cancel':
            unset($_SESSION['pending_totp_secret']);
            redirect('account.php#two-factor');

        case '2fa_disable':
            $section = '2fa';
            if (!$passwordOk('2fa_disable_failed')) {
                $errors[] = 'Your password is incorrect.';
                break;
            }
            UserRepository::disableTotp($userId);
            AuditLog::record('2fa_disabled');
            flash('warning', 'Two-factor authentication has been turned off. Your account is now protected by your password only.');
            redirect('account.php#two-factor');

        case '2fa_new_codes':
            $section = '2fa';
            if (!$passwordOk('2fa_new_codes_failed')) {
                $errors[] = 'Your password is incorrect.';
                break;
            }
            $_SESSION['show_recovery_codes'] = RecoveryCodes::regenerate($userId);
            AuditLog::record('2fa_recovery_codes_regenerated');
            flash('success', 'New recovery codes generated. Your old codes no longer work.');
            redirect('account.php#two-factor');

        case 'revoke_sessions':
            Auth::signOutOtherSessions($userId);
            AuditLog::record('sessions_revoked', 'Signed out all other sessions');
            flash('success', 'All other sessions have been signed out.');
            redirect('account.php#sessions');
    }
}

$newCodes = $_SESSION['show_recovery_codes'] ?? null;
unset($_SESSION['show_recovery_codes']);   // recovery codes are shown exactly once

$fresh = UserRepository::find($userId);

view('account', [
    'pageTitle'      => 'Security settings',
    'user'           => $user,
    'errors'         => $errors,
    'section'        => $section,
    'totpEnabled'    => (bool) $fresh['totp_enabled'],
    'pendingSecret'  => $_SESSION['pending_totp_secret'] ?? null,
    'newCodes'       => $newCodes,
    'remainingCodes' => RecoveryCodes::remaining($userId),
    'activity'       => AuditLog::recentForUser($userId),
    'idleMinutes'    => (int) (Config::get('session.idle_timeout') / 60),
    'sec'            => Config::get('security'),
]);
