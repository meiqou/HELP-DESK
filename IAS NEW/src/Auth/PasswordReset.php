<?php

namespace App\Auth;

use App\Core\Config;
use App\Core\Database;
use App\Repositories\UserRepository;
use App\Security\AuditLog;
use App\Security\PasswordHasher;
use App\Security\PasswordPolicy;
use App\Security\RateLimiter;
use Throwable;

final class PasswordReset
{
    private const CODE_PURPOSE = 'reset';
    private const REQUEST_ACTION = 'pw_request';
    private const VERIFY_ACTION = 'pw_verify';
    private const REQUESTS_PER_HOUR = 10;

    /**
     * Sends a code when the address belongs to an account. The caller always
     * shows the same response, whether or not an account exists.
     */
    public static function requestCode(string $email): void
    {
        $email = strtolower(trim($email));
        if (RateLimiter::attemptsFromIp(self::REQUEST_ACTION, 3600, false) >= self::REQUESTS_PER_HOUR) {
            return;
        }

        RateLimiter::record(self::REQUEST_ACTION, $email, true);
        $user = UserRepository::findByEmail($email);
        if ($user !== null) {
            EmailCodes::send($user, self::CODE_PURPOSE);
        }
    }

    /** @return array{status: string, errors: string[]} */
    public static function complete(string $email, string $code, string $password, string $confirmation): array
    {
        $email = strtolower(trim($email));
        $errors = [];
        if (!hash_equals($password, $confirmation)) {
            $errors[] = 'The new passwords do not match.';
        }
        // Do not include account-specific words in errors; this page must not
        // reveal whether an e-mail address belongs to an account.
        $errors = array_merge($errors, PasswordPolicy::errors($password));
        if ($errors) {
            return ['status' => 'invalid_input', 'errors' => $errors];
        }

        $security = Config::get('security');
        $window = (int) $security['lockout_window'];
        if (self::isLimited($email, $window)) {
            return ['status' => 'limited', 'errors' => []];
        }

        $user = UserRepository::findByEmail($email);
        if ($user === null) {
            return self::failedCode($email, $window);
        }

        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            if (!EmailCodes::verify((int) $user['id'], self::CODE_PURPOSE, $code)) {
                $pdo->rollBack();
                return self::failedCode($email, $window);
            }

            UserRepository::updatePasswordHash((int) $user['id'], PasswordHasher::hash($password), true);
            UserRepository::bumpSessionVersion((int) $user['id']);
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }

        RateLimiter::record(self::VERIFY_ACTION, $email, true);
        AuditLog::record('password_reset', 'Password reset using an e-mail code', (int) $user['id'], $user['username']);
        return ['status' => 'success', 'errors' => []];
    }

    private static function failedCode(string $email, int $window): array
    {
        RateLimiter::record(self::VERIFY_ACTION, $email, false);
        return ['status' => self::isLimited($email, $window) ? 'limited' : 'invalid_code', 'errors' => []];
    }

    private static function isLimited(string $email, int $window): bool
    {
        $security = Config::get('security');
        return RateLimiter::failures(self::VERIFY_ACTION, $email, $window) >= (int) $security['max_login_failures_per_account']
            || RateLimiter::attemptsFromIp(self::VERIFY_ACTION, $window) >= (int) $security['max_login_failures_per_ip'];
    }
}
