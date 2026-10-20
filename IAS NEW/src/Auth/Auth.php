<?php

namespace App\Auth;

use App\Core\Config;
use App\Core\Session;
use App\Core\View;
use App\Repositories\UserRepository;
use App\Security\AuditLog;
use App\Security\Encryptor;
use App\Security\PasswordHasher;
use App\Security\RateLimiter;
use App\Security\Totp;

/**
 * Authentication (who you are) and the page guards that enforce authorization.
 */
final class Auth
{
    private static array|false|null $user = false;

    /* ------------------------------------------------------------------
     * Sign-in flow
     * --------------------------------------------------------------- */

    /**
     * Step 1: username (or e-mail) and password.
     *
     * @return array{status: string, user?: array}
     *   status is one of: empty, locked, invalid, inactive, two_factor, success
     */
    public static function attempt(string $login, string $password): array
    {
        $login = mb_substr(mb_scrub($login, 'UTF-8'), 0, 254);
        if ($login === '' || $password === '') {
            return ['status' => 'empty'];
        }

        $account = UserRepository::findByLogin($login);
        // Failures always count against the real username, so switching between
        // username and e-mail does not give an attacker extra attempts.
        $identifier = $account['username'] ?? $login;
        $userId = $account ? (int) $account['id'] : null;

        if (RateLimiter::isLoginLocked($identifier)) {
            AuditLog::record('login_blocked', 'Attempt while locked out', $userId, $identifier);
            return ['status' => 'locked'];
        }

        if (!$account) {
            PasswordHasher::dummyVerify($password);   // same response time as a real account
            return self::failed($identifier, null, 'Unknown username');
        }

        [$valid, $needsRehash] = PasswordHasher::verify($password, $account['password']);
        if (!$valid) {
            return self::failed($identifier, $userId, 'Wrong password');
        }

        if (!$account['is_active']) {
            // Answer exactly like a wrong password, so the response never confirms the password.
            RateLimiter::record('login', $identifier, false);
            AuditLog::record('login_inactive', 'Sign-in attempt to a deactivated account', $userId, $identifier);
            return ['status' => 'invalid'];
        }

        if ($needsRehash) {
            UserRepository::updatePasswordHash($userId, PasswordHasher::hash($password), false);
            AuditLog::record('password_rehashed', 'Password hash upgraded to the current algorithm', $userId, $identifier);
        }
        RateLimiter::record('login', $identifier, true);

        if ($account['totp_enabled']) {
            Session::renew(['pending_2fa' => [
                'user_id'         => $userId,
                'session_version' => (int) $account['session_version'],
                'expires'         => time() + (int) Config::get('security.two_factor_timeout'),
                'fingerprint'     => Session::fingerprint(),
            ]]);
            AuditLog::record('login_password_ok_2fa_pending', '', $userId, $identifier);
            return ['status' => 'two_factor', 'user' => $account];
        }

        self::login($account);
        AuditLog::record('login_success', '', $userId, $identifier);
        return ['status' => 'success', 'user' => $account];
    }

    private static function failed(string $identifier, ?int $userId, string $reason): array
    {
        RateLimiter::record('login', $identifier, false);
        AuditLog::record('login_failed', $reason, $userId, $identifier);
        if (RateLimiter::isLoginLocked($identifier)) {
            AuditLog::record('account_locked', 'Temporarily locked after repeated failures', $userId, $identifier);
        }
        return ['status' => 'invalid'];
    }

    /** The account waiting for its second factor, or null if none / expired. */
    public static function pendingTwoFactorUser(): ?array
    {
        $pending = $_SESSION['pending_2fa'] ?? null;
        if (!$pending || time() > $pending['expires']) {
            return null;
        }
        $user = UserRepository::find((int) $pending['user_id']);
        // A password change, role change or "sign out everywhere" also cancels a half-finished
        // sign-in, and the second step must come from the same browser as the first.
        if (!$user || !$user['is_active'] || !$user['totp_enabled']
            || (int) $user['session_version'] !== (int) ($pending['session_version'] ?? -1)
            || !hash_equals($pending['fingerprint'] ?? '', Session::fingerprint())) {
            return null;
        }
        return $user;
    }

    /**
     * Step 2: authenticator code or one-time recovery code.
     *
     * @return array{status: string, recovery?: bool, user?: array}
     *   status is one of: expired, invalid, locked, success
     */
    public static function verifySecondFactor(string $code): array
    {
        $user = self::pendingTwoFactorUser();
        if (!$user) {
            return ['status' => 'expired'];
        }
        $id = (int) $user['id'];
        $s = Config::get('security');

        // Check the lockout first. Otherwise an attacker who knows the password could open many
        // half-finished sign-ins and keep guessing codes in each of them after the account locks.
        if (RateLimiter::isLoginLocked($user['username'])
            || RateLimiter::failures('2fa', $user['username'], $s['lockout_window']) >= $s['max_login_failures_per_account']) {
            AuditLog::record('login_blocked', 'Two-factor attempt while locked out', $id, $user['username']);
            return ['status' => 'locked'];
        }

        $secret = Encryptor::decrypt($user['totp_secret']);
        $lastStep = $user['totp_last_step'] !== null ? (int) $user['totp_last_step'] : null;
        $step = $secret ? Totp::verify($secret, $code, $lastStep) : null;
        // Claim the time step atomically: two simultaneous requests cannot both use one code.
        if ($step !== null && !UserRepository::claimTotpStep($id, $step)) {
            $step = null;
        }
        $usedRecovery = $step === null && strlen(trim($code)) > Totp::DIGITS && RecoveryCodes::consume($id, $code);

        if ($step !== null || $usedRecovery) {
            RateLimiter::record('2fa', $user['username'], true);
            self::login($user);
            AuditLog::record($usedRecovery ? 'login_success_recovery_code' : 'login_success_2fa', '', $id, $user['username']);
            return ['status' => 'success', 'recovery' => $usedRecovery, 'user' => $user];
        }

        RateLimiter::record('2fa', $user['username'], false);
        AuditLog::record('2fa_failed', 'Invalid verification code', $id, $user['username']);

        if (RateLimiter::failures('2fa', $user['username'], $s['lockout_window']) >= $s['max_login_failures_per_account']) {
            // Too many wrong codes: lock the account and discard the half-finished sign-in.
            RateLimiter::lockAccount($user['username']);
            AuditLog::record('account_locked', 'Too many invalid two-factor codes', $id, $user['username']);
            return ['status' => 'locked'];
        }
        return ['status' => 'invalid'];
    }

    /**
     * Creates the authenticated session. A new session ID is issued (session fixation defence).
     */
    public static function login(array $user): void
    {
        $now = time();
        Session::renew([
            'user_id'         => (int) $user['id'],
            'username'        => $user['username'],
            'role'            => $user['role'],
            'session_version' => (int) $user['session_version'],
            'login_time'      => $now,
            'last_activity'   => $now,
            'regenerated_at'  => $now,
            'reauth_at'       => $now,
            'fingerprint'     => Session::fingerprint(),
        ]);
        UserRepository::recordLogin((int) $user['id'], client_ip());
        self::$user = false;
    }

    public static function logout(string $message = 'You have been signed out.'): void
    {
        if (isset($_SESSION['user_id'])) {
            AuditLog::record('logout');
        }
        Session::destroy($message);
        self::$user = false;
    }

    /* ------------------------------------------------------------------
     * Current user
     * --------------------------------------------------------------- */

    /**
     * Reloads the signed-in user from the database on every request, so a role
     * change, deactivation or deletion takes effect immediately.
     */
    public static function user(): ?array
    {
        if (self::$user !== false) {
            return self::$user;
        }
        self::$user = null;

        if (!isset($_SESSION['user_id'])) {
            return null;
        }

        $row = UserRepository::find((int) $_SESSION['user_id']);
        if (!$row || !$row['is_active'] || (int) $row['session_version'] !== (int) ($_SESSION['session_version'] ?? -1)) {
            Session::destroy('Your session is no longer valid. Please sign in again.', 'session_revoked');
            return null;
        }

        $_SESSION['role'] = $row['role'];
        $_SESSION['username'] = $row['username'];
        unset($row['password'], $row['totp_secret']);   // never keep secrets in memory longer than needed
        return self::$user = $row;
    }

    public static function can(string $permission): bool
    {
        return Rbac::allows(self::user()['role'] ?? null, $permission);
    }

    /* ------------------------------------------------------------------
     * Password checks for sensitive actions
     * --------------------------------------------------------------- */

    public static function checkPassword(int $userId, string $password): bool
    {
        return PasswordHasher::verify($password, UserRepository::passwordHash($userId))[0];
    }

    /**
     * Step-up authentication. Returns 'ok', 'invalid' or 'signed_out' (too many wrong attempts).
     */
    public static function confirmPassword(array $user, string $password): string
    {
        $result = self::verifyCurrentPassword($user, $password, 'reauth_failed');
        if ($result === 'ok') {
            $_SESSION['reauth_at'] = time();
            AuditLog::record('reauth_success');
        }
        return $result;
    }

    /**
     * Password check for every sensitive action by a signed-in user (step-up, password change,
     * two-factor changes). Wrong guesses are rate limited and, after too many, the session ends.
     * Returns 'ok', 'invalid' or 'signed_out'.
     */
    public static function verifyCurrentPassword(array $user, string $password, string $failureEvent): string
    {
        if (self::checkPassword((int) $user['id'], $password)) {
            RateLimiter::record('reauth', $user['username'], true);
            return 'ok';
        }

        RateLimiter::record('reauth', $user['username'], false);
        AuditLog::record($failureEvent);
        $s = Config::get('security');
        if (RateLimiter::failures('reauth', $user['username'], $s['lockout_window']) >= $s['max_login_failures_per_account']) {
            // Someone at an unattended computer is guessing the password.
            AuditLog::record('session_ended_reauth_failures');
            Session::destroy('You were signed out after too many incorrect password confirmations.');
            return 'signed_out';
        }
        return 'invalid';
    }

    /** Signs out every other device while keeping the current session valid. */
    public static function signOutOtherSessions(int $userId): void
    {
        $_SESSION['session_version'] = UserRepository::bumpSessionVersion($userId);
        session_regenerate_id(true);
        self::$user = false;
    }

    /* ------------------------------------------------------------------
     * Page guards
     * --------------------------------------------------------------- */

    public static function requireLogin(): array
    {
        $user = self::user();
        if ($user === null) {
            if (empty($_SESSION['flash'])) {
                Session::flash('info', 'Please sign in to continue.');
            }
            redirect('login.php');
        }
        return $user;
    }

    public static function requirePermission(string $permission): array
    {
        $user = self::requireLogin();
        if (!Rbac::allows($user['role'], $permission)) {
            AuditLog::record('access_denied', "Missing permission {$permission} on " . basename($_SERVER['SCRIPT_NAME'] ?? ''));
            View::render('error', [
                'pageTitle' => 'Access denied',
                'code'      => 403,
                'heading'   => 'Access denied',
                'message'   => 'Your account does not have permission to view this page. This attempt has been logged.',
                'homeHref'  => Rbac::homeFor($user['role']),
            ], 'app', 403);
            exit();
        }
        return $user;
    }

    /**
     * Sensitive pages require the password to have been confirmed recently.
     */
    public static function requireRecentAuth(string $returnTo): void
    {
        if (time() - ($_SESSION['reauth_at'] ?? 0) > (int) Config::get('security.reauth_window')) {
            redirect('confirm_password.php?next=' . rawurlencode($returnTo));
        }
    }
}
