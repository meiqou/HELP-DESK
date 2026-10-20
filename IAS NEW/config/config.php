<?php
/**
 * Application configuration.
 *
 * Every value can be overridden with an environment variable, so credentials
 * never have to be committed and the test suite can shorten the timeouts.
 */

$env = static fn (string $key, $default) => getenv($key) !== false && getenv($key) !== '' ? getenv($key) : $default;

return [
    'app' => [
        'name'     => 'USLS IT Helpdesk',
        'timezone' => 'Asia/Manila',
    ],

    'db' => [
        'host'    => $env('DB_HOST', 'localhost'),
        'port'    => $env('DB_PORT', '3306'),
        'name'    => $env('DB_NAME', 'secure_system'),
        'user'    => $env('DB_USER', 'root'),
        'pass'    => $env('DB_PASS', ''),
        'charset' => 'utf8mb4',
    ],

    'session' => [
        'name'             => 'USLS_HD_SID',
        'idle_timeout'     => (int) $env('SESSION_IDLE_TIMEOUT', 15 * 60),     // sign out after 15 minutes idle
        'absolute_timeout' => (int) $env('SESSION_ABSOLUTE_TIMEOUT', 8 * 3600), // force sign-in again after 8 hours
        'regenerate_every' => 10 * 60,                                         // rotate the session ID every 10 minutes
    ],

    'security' => [
        // Brute-force protection
        'max_login_failures_per_account' => 5,
        // Per network address. Kept generous because a whole campus can share one public IP (NAT).
        'max_login_failures_per_ip'      => (int) $env('MAX_LOGIN_FAILURES_PER_IP', 50),
        'lockout_window'                 => 15 * 60,
        'max_registrations_per_ip'       => (int) $env('MAX_REGISTRATIONS_PER_IP', 5),
        'registration_window'            => 3600,

        // Step-up authentication for sensitive admin pages
        'reauth_window' => (int) $env('REAUTH_WINDOW', 10 * 60),

        // The second sign-in step must be completed within this many seconds
        'two_factor_timeout' => 5 * 60,

        // Restrict self-registration to one e-mail domain, e.g. 'usls.edu.ph' (empty = any domain)
        'allowed_email_domain' => $env('ALLOWED_EMAIL_DOMAIN', ''),

        // Password policy
        'password_min_length' => 12,
        'password_max_length' => 128,
    ],
];
