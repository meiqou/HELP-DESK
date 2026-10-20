<?php

namespace App\Security;

use App\Core\Config;

/**
 * Server-side password rules. The strength meter in the browser is only a hint;
 * these checks are what actually decide.
 */
final class PasswordPolicy
{
    private const COMMON = [
        'password', 'password1', 'password123', '123456', '12345678', '123456789', '1234567890',
        'qwerty', 'qwerty123', 'abc123', 'letmein', 'welcome', 'welcome1', 'admin', 'admin123',
        'iloveyou', 'monkey', 'dragon', 'football', 'baseball', 'sunshine', 'princess', 'master',
        'shadow', 'trustno1', 'superman', 'passw0rd', 'p@ssw0rd', 'p@ssword', 'changeme',
        'lasalle', 'usls', 'uslsbacolod', 'stlasalle', 'animo', 'animolasalle', 'bacolod', 'student',
    ];

    /**
     * @return string[] human-readable problems; empty when the password is acceptable
     */
    public static function errors(string $password, string $username = '', string $email = ''): array
    {
        $min = (int) Config::get('security.password_min_length');
        $max = (int) Config::get('security.password_max_length');
        $errors = [];
        $length = mb_strlen($password);

        if ($length < $min) {
            $errors[] = "Use at least {$min} characters.";
        }
        if ($length > $max) {
            $errors[] = "Use no more than {$max} characters.";
        }
        if (!preg_match('/[a-z]/', $password)) {
            $errors[] = 'Include a lowercase letter.';
        }
        if (!preg_match('/[A-Z]/', $password)) {
            $errors[] = 'Include an uppercase letter.';
        }
        if (!preg_match('/\d/', $password)) {
            $errors[] = 'Include a number.';
        }
        if (!preg_match('/[^a-zA-Z\d]/', $password)) {
            $errors[] = 'Include a symbol (for example ! @ # $).';
        }
        if (preg_match('/(.)\1{3,}/', $password)) {
            $errors[] = 'Avoid repeating the same character four or more times.';
        }

        $lower = strtolower($password);
        $lettersOnly = preg_replace('/[^a-z]/', '', $lower);
        if (in_array($lower, self::COMMON, true) || in_array($lettersOnly, self::COMMON, true)) {
            $errors[] = 'This password is too common.';
        }

        if (strlen($username) >= 3 && str_contains($lower, strtolower($username))) {
            $errors[] = 'Do not include your username in your password.';
        }
        $local = strtolower(strstr($email, '@', true) ?: '');
        if (strlen($local) >= 3 && str_contains($lower, $local)) {
            $errors[] = 'Do not include your e-mail name in your password.';
        }

        return $errors;
    }
}
