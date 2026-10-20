<?php

namespace App\Auth;

use App\Core\Config;
use App\Repositories\UserRepository;
use App\Security\AuditLog;
use App\Security\PasswordHasher;
use App\Security\PasswordPolicy;
use App\Security\RateLimiter;
use PDOException;

/**
 * Self-service account creation for students and staff.
 * New accounts always get the lowest role; only an administrator can raise it.
 */
final class Registration
{
    /**
     * @return string[] validation errors; an empty array means the account was created
     */
    public static function register(string $username, string $email, string $password, string $confirm): array
    {
        $s = Config::get('security');

        if (RateLimiter::attemptsFromIp('register', $s['registration_window'], false) >= $s['max_registrations_per_ip']) {
            AuditLog::record('register_rate_limited', 'Too many registrations from this IP');
            return ['Too many accounts have been created from this network. Please try again later.'];
        }

        $errors = [];
        if (!preg_match('/^[A-Za-z0-9._-]{3,30}$/', $username)) {
            $errors[] = 'Username must be 3 to 30 characters using letters, numbers, dots, dashes or underscores.';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 254) {
            $errors[] = 'Enter a valid e-mail address.';
        } elseif ($s['allowed_email_domain'] !== '' && !str_ends_with($email, '@' . strtolower($s['allowed_email_domain']))) {
            $errors[] = 'Use your institutional e-mail address (@' . $s['allowed_email_domain'] . ').';
        }
        if (!hash_equals($password, $confirm)) {
            $errors[] = 'Passwords do not match.';
        }
        $errors = array_merge($errors, PasswordPolicy::errors($password, $username, $email));
        if ($errors) {
            return array_values(array_unique($errors));
        }

        try {
            $id = UserRepository::create($username, $email, PasswordHasher::hash($password), 'user');
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') {   // unique key: username or e-mail already used
                RateLimiter::record('register', $username, false);
                return ['That username or e-mail address is already registered.'];
            }
            error_log('Registration failed: ' . $e->getMessage());
            return ['Something went wrong. Please try again.'];
        }

        RateLimiter::record('register', $username, true);
        AuditLog::record('register', 'New account created', $id, $username);
        return [];
    }
}
