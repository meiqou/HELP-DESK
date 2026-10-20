<?php

namespace App\Security;

/**
 * Cross-Site Request Forgery protection (synchronizer token pattern).
 * Every state-changing form carries a random per-session token that another
 * website cannot read, so it cannot forge the request.
 */
final class Csrf
{
    public static function token(): string
    {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    public static function field(): string
    {
        return '<input type="hidden" name="csrf_token" value="' . e(self::token()) . '">';
    }

    public static function isValid(mixed $sent): bool
    {
        return is_string($sent) && $sent !== '' && hash_equals(self::token(), $sent);
    }

    /**
     * Stops the request with HTTP 400 if the token is missing or wrong.
     */
    public static function verify(): void
    {
        if (!self::isValid($_POST['csrf_token'] ?? null)) {
            AuditLog::record('csrf_rejected', 'Request to ' . basename($_SERVER['SCRIPT_NAME'] ?? ''));
            http_response_code(400);
            exit('Your session form has expired. Please go back, refresh the page and try again.');
        }
    }
}
