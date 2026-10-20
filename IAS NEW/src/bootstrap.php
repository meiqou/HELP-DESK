<?php
/**
 * Application bootstrap. Every page in public/ requires this file first.
 *
 * Layers:
 *   public/        thin page controllers (the only folder the web server exposes)
 *   src/Core       configuration, database, session, view rendering
 *   src/Security   hashing, policy, CSRF, rate limiting, encryption, TOTP, audit log, headers
 *   src/Auth       authentication flow and role-based access control
 *   src/Repositories  all SQL lives here
 *   views/         HTML templates (no business logic)
 */

declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__));

// Never show PHP errors (paths, SQL, stack traces) to visitors.
ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

// PSR-4 style autoloader: App\Security\Csrf => src/Security/Csrf.php
spl_autoload_register(static function (string $class): void {
    if (str_starts_with($class, 'App\\')) {
        $file = __DIR__ . '/' . str_replace('\\', '/', substr($class, 4)) . '.php';
        if (is_file($file)) {
            require $file;
        }
    }
});

App\Core\Config::load(APP_ROOT . '/config/config.php');
date_default_timezone_set(App\Core\Config::get('app.timezone'));

require __DIR__ . '/helpers.php';

if (PHP_SAPI !== 'cli') {
    App\Security\SecurityHeaders::send();
    App\Core\Session::start();
}
