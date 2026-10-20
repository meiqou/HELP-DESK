<?php
/**
 * Creates (or promotes) an administrator account.
 *
 * Command line only. It cannot be opened from a browser, so nobody can use it
 * to reset the admin password remotely.
 *
 *   php setup/create_admin.php
 *
 * Non-interactive use: set ADMIN_USERNAME, ADMIN_EMAIL and ADMIN_PASSWORD.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit();
}

$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['HTTP_USER_AGENT'] = 'cli';

require dirname(__DIR__) . '/src/bootstrap.php';

use App\Repositories\UserRepository;
use App\Security\AuditLog;
use App\Security\PasswordHasher;
use App\Security\PasswordPolicy;

function ask(string $prompt): string
{
    echo $prompt;
    return trim((string) fgets(STDIN));
}

echo PHP_EOL . 'USLS IT Helpdesk: create administrator' . PHP_EOL . str_repeat('-', 40) . PHP_EOL;

$username = getenv('ADMIN_USERNAME') ?: ask('Username: ');
$email = strtolower(getenv('ADMIN_EMAIL') ?: ask('E-mail: '));

if (!preg_match('/^[A-Za-z0-9._-]{3,30}$/', $username) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    fwrite(STDERR, 'Invalid username or e-mail.' . PHP_EOL);
    exit(1);
}

$password = getenv('ADMIN_PASSWORD') ?: ask('Password: ');
if ($problems = PasswordPolicy::errors($password, $username, $email)) {
    fwrite(STDERR, 'Password rejected:' . PHP_EOL . ' - ' . implode(PHP_EOL . ' - ', $problems) . PHP_EOL);
    exit(1);
}

// Look the account up by username only; an e-mail that belongs to someone else is an error,
// never a reason to promote that other account.
$existing = UserRepository::findByUsername($username);
$emailOwner = UserRepository::findByEmail($email);
if ($emailOwner && (!$existing || (int) $emailOwner['id'] !== (int) $existing['id'])) {
    fwrite(STDERR, "The e-mail {$email} already belongs to the account '{$emailOwner['username']}'." . PHP_EOL);
    exit(1);
}

if ($existing) {
    $id = (int) $existing['id'];
    UserRepository::updatePasswordHash($id, PasswordHasher::hash($password), true);
    UserRepository::setRole($id, 'admin');
    UserRepository::setActive($id, true);
    AuditLog::record('admin_promoted_cli', $username, $id, $username);
    echo "Existing account '{$username}' is now an administrator with the new password." . PHP_EOL;
} else {
    $id = UserRepository::create($username, $email, PasswordHasher::hash($password), 'admin');
    AuditLog::record('admin_created_cli', $username, $id, $username);
    echo "Administrator '{$username}' created. Sign in at login.php" . PHP_EOL;
}
