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
     *   status is one of: empty, locked, invalid, two_factor, enrol, success
     *   plus attempts_left (invalid) or retry_after in seconds (locked)
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
            return ['status' => 'locked', 'retry_after' => RateLimiter::cooldownSeconds($identifier)];
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
            return self::failed($identifier, $userId, 'Sign-in attempt to a deactivated account', 'login_inactive');
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
            if ($account['two_factor_method'] === 'email') {
                $_SESSION['pending_2fa']['email_status'] = EmailCodes::send($account, 'login');
            }
            return ['status' => 'two_factor', 'user' => $account];
        }

        if (Config::get('security.require_two_factor')) {
            // Two-factor is mandatory. The account stays signed out until an authenticator app
            // is linked, so a half-finished setup cannot reach any page or action.
            Session::renew(['pending_enrolment' => [
                'user_id'         => $userId,
                'session_version' => (int) $account['session_version'],
                'expires'         => time() + (int) Config::get('security.enrolment_timeout'),
                'fingerprint'     => Session::fingerprint(),
                'secret'          => Totp::generateSecret(),
            ]]);
            AuditLog::record('login_password_ok_enrolment_required', '', $userId, $identifier);
            return ['status' => 'enrol', 'user' => $account];
        }

        self::login($account);
        AuditLog::record('login_success', '', $userId, $identifier);
        return ['status' => 'success', 'user' => $account];
    }

    private static function failed(string $identifier, ?int $userId, string $reason, string $event = 'login_failed'): array
    {
        RateLimiter::record('login', $identifier, false);
        AuditLog::record($event, $reason, $userId, $identifier);
        if (RateLimiter::isLoginLocked($identifier)) {
            AuditLog::record('account_locked', 'Temporarily locked after repeated failures', $userId, $identifier);
            return ['status' => 'locked', 'retry_after' => RateLimiter::cooldownSeconds($identifier)];
        }
        return ['status' => 'invalid', 'attempts_left' => RateLimiter::loginAttemptsLeft($identifier)];
    }

    /** The account that must link an authenticator app before it can sign in, or null. */
    public static function pendingEnrolmentUser(): ?array
    {
        $pending = $_SESSION['pending_enrolment'] ?? null;
        if (!$pending || time() > $pending['expires']) {
            return null;
        }
        $user = UserRepository::find((int) $pending['user_id']);
        if (!$user || !$user['is_active'] || $user['totp_enabled']
            || (int) $user['session_version'] !== (int) $pending['session_version']
            || !hash_equals($pending['fingerprint'], Session::fingerprint())) {
            return null;
        }
        return $user;
    }

    /**
     * Finishes mandatory two-factor setup: proves the app works, then signs the user in.
     *
     * @return string one of: expired, invalid, success
     */
    public static function completeEnrolment(string $code): string
    {
        $user = self::pendingEnrolmentUser();
        if (!$user) {
            return 'expired';
        }
        $id = (int) $user['id'];
        $secret = $_SESSION['pending_enrolment']['secret'];
        $step = Totp::verify($secret, $code);
        if ($step === null) {
            AuditLog::record('2fa_setup_failed', 'Wrong code during required setup', $id, $user['username']);
            return 'invalid';
        }
        if (!UserRepository::enableTotp($id, Encryptor::encrypt($secret), $step)) {
            return 'expired';   // another browser finished setup first
        }
        $codes = RecoveryCodes::regenerate($id);
        self::login($user);
        $_SESSION['show_recovery_codes'] = $codes;
        AuditLog::record('2fa_enabled', 'Required setup completed', $id, $user['username']);
        AuditLog::record('login_success_2fa', '', $id, $user['username']);
        return 'success';
    }

    /** E-mails a setup code to the account's address, during required setup. */
    public static function sendEnrolmentEmail(): string
    {
        $user = self::pendingEnrolmentUser();
        if (!$user) {
            return 'expired';
        }
        $status = EmailCodes::send($user, 'enrol');
        if ($status === 'sent') {
            $_SESSION['pending_enrolment']['email_sent'] = true;
        }
        return $status;
    }

    /**
     * Finishes required setup with an e-mailed code, which proves the address really is the user's.
     *
     * @return array{status: string, attempts_left?: int, retry_after?: int}
     *   status is one of: expired, locked, invalid, success
     */
    public static function completeEmailEnrolment(string $code): array
    {
        $user = self::pendingEnrolmentUser();
        if (!$user) {
            return ['status' => 'expired'];
        }
        $id = (int) $user['id'];
        $s = Config::get('security');
        if (self::secondFactorLocked($user['username'])) {
            return ['status' => 'locked', 'retry_after' => (int) $s['lockout_window']];
        }
        if (!EmailCodes::verify($id, 'enrol', $code)) {
            return self::secondFactorFailed($id, $user['username']);
        }
        if (!UserRepository::enableEmailTwoFactor($id)) {
            return ['status' => 'expired'];   // another browser finished setup first
        }
        RateLimiter::record('2fa', $user['username'], true);
        $codes = RecoveryCodes::regenerate($id);
        self::login($user);
        $_SESSION['show_recovery_codes'] = $codes;
        AuditLog::record('2fa_enabled', 'Required setup completed with e-mail codes', $id, $user['username']);
        AuditLog::record('login_success_2fa', '', $id, $user['username']);
        return ['status' => 'success'];
    }

    /** True while the password step or the second-factor step is locked for this account. */
    private static function secondFactorLocked(string $username): bool
    {
        $s = Config::get('security');
        return RateLimiter::isLoginLocked($username)
            || RateLimiter::failures('2fa', $username, $s['lockout_window']) >= $s['max_login_failures_per_account'];
    }

    /** Counts a wrong code, locking the account when the limit is reached. */
    private static function secondFactorFailed(int $id, string $username): array
    {
        $s = Config::get('security');
        RateLimiter::record('2fa', $username, false);
        AuditLog::record('2fa_failed', 'Invalid verification code', $id, $username);

        if (RateLimiter::failures('2fa', $username, $s['lockout_window']) >= $s['max_login_failures_per_account']) {
            // Too many wrong codes: lock the account and discard the half-finished sign-in.
            RateLimiter::lockAccount($username);
            AuditLog::record('account_locked', 'Too many invalid two-factor codes', $id, $username);
            return ['status' => 'locked', 'retry_after' => RateLimiter::cooldownSeconds($username)];
        }
        return ['status' => 'invalid', 'attempts_left' => $s['max_login_failures_per_account'] - RateLimiter::failures('2fa', $username, $s['lockout_window'])];
    }

    /** Sends a fresh code during the second sign-in step. */
    public static function resendLoginCode(): string
    {
        $user = self::pendingTwoFactorUser();
        if (!$user || $user['two_factor_method'] !== 'email' || self::secondFactorLocked($user['username'])) {
            return 'failed';
        }
        return EmailCodes::send($user, 'login');
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
     * Step 2: authenticator code, e-mailed code or one-time recovery code.
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
        if (self::secondFactorLocked($user['username'])) {
            AuditLog::record('login_blocked', 'Two-factor attempt while locked out', $id, $user['username']);
            return ['status' => 'locked', 'retry_after' => (int) $s['lockout_window']];
        }

        $valid = self::primaryCodeIsValid($user, $code, 'login');
        $usedRecovery = !$valid && strlen(trim($code)) > Totp::DIGITS && RecoveryCodes::consume($id, $code);

        if ($valid || $usedRecovery) {
            RateLimiter::record('2fa', $user['username'], true);
            self::login($user);
            AuditLog::record($usedRecovery ? 'login_success_recovery_code' : 'login_success_2fa', '', $id, $user['username']);
            return ['status' => 'success', 'recovery' => $usedRecovery, 'user' => $user];
        }

        return self::secondFactorFailed($id, $user['username']);
    }

    /**
     * Checks a code against the account's chosen method, and uses it up.
     * $row must be the full users row, including the encrypted secret.
     */
    private static function primaryCodeIsValid(array $row, string $code, string $purpose): bool
    {
        $id = (int) $row['id'];
        if ($row['two_factor_method'] === 'email') {
            return EmailCodes::verify($id, $purpose, $code);
        }
        $secret = Encryptor::decrypt($row['totp_secret']);
        $lastStep = $row['totp_last_step'] !== null ? (int) $row['totp_last_step'] : null;
        $step = $secret ? Totp::verify($secret, $code, $lastStep) : null;
        // Claim the time step atomically: two simultaneous requests cannot both use one code.
        return $step !== null && UserRepository::claimTotpStep($id, $step);
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

    /** Wrong current-password guesses left before the session is ended. */
    public static function reauthAttemptsLeft(array $user): int
    {
        $s = Config::get('security');
        return max(0, $s['max_login_failures_per_account'] - RateLimiter::failures('reauth', $user['username'], $s['lockout_window']));
    }

    /**
     * Second-factor check for a signed-in user (authenticator code or recovery code).
     * Returns 'ok', 'invalid' or 'signed_out' (too many wrong codes: the account locks).
     */
    public static function verifyCurrentCode(array $user, string $code): string
    {
        $row = UserRepository::find((int) $user['id']);
        if (self::primaryCodeIsValid($row, $code, 'account')
            || (strlen(trim($code)) > Totp::DIGITS && RecoveryCodes::consume((int) $row['id'], $code))) {
            RateLimiter::record('2fa', $user['username'], true);
            return 'ok';
        }

        RateLimiter::record('2fa', $user['username'], false);
        AuditLog::record('2fa_failed', 'Invalid code on the account page');
        $s = Config::get('security');
        if (RateLimiter::failures('2fa', $user['username'], $s['lockout_window']) >= $s['max_login_failures_per_account']) {
            RateLimiter::lockAccount($user['username']);
            AuditLog::record('account_locked', 'Too many invalid two-factor codes');
            Session::destroy('Too many invalid codes. Your account is temporarily locked.');
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
