<?php
/**
 * Automated security and feature test suite.
 *
 *   php tests/run.php
 *
 * What it does:
 *   1. Creates a separate, throwaway database (secure_system_test) from database/schema.sql.
 *   2. Starts its own PHP web server on a free port with shortened timeouts.
 *   3. Runs unit tests on the security classes and end-to-end tests over real HTTP.
 *   4. Prints a PASS/FAIL report and exits with code 1 if anything failed.
 *
 * Your real database is never touched. Connection settings come from the same
 * environment variables as the app (DB_HOST, DB_PORT, DB_USER, DB_PASS).
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit();
}

const TEST_DB = 'secure_system_test';
const IDLE_TIMEOUT = 15;    // seconds, so the idle-timeout test runs quickly
const REAUTH_WINDOW = 10;   // seconds, so the step-up test runs quickly
const MAX_REGISTRATIONS = 6;

$root = dirname(__DIR__);
require __DIR__ . '/lib/TestRunner.php';
require __DIR__ . '/lib/HttpClient.php';

/* -------------------------------------------------------------------------
 * 1. Fresh test database
 * ---------------------------------------------------------------------- */
$dbHost = getenv('DB_HOST') ?: 'localhost';
$dbPort = getenv('DB_PORT') ?: '3306';
$dbUser = getenv('DB_USER') ?: 'root';
$dbPass = getenv('DB_PASS') ?: '';

$admin = new PDO("mysql:host=$dbHost;port=$dbPort;charset=utf8mb4", $dbUser, $dbPass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$admin->exec('DROP DATABASE IF EXISTS ' . TEST_DB);
$sql = str_replace('secure_system', TEST_DB, file_get_contents("$root/database/schema.sql"));
$sql = preg_replace('/^\s*--.*$/m', '', $sql);
foreach (preg_split('/;\s*\n/', $sql) as $statement) {
    if (trim($statement) !== '') {
        $admin->exec($statement);
    }
}

putenv('DB_NAME=' . TEST_DB);
putenv("DB_HOST=$dbHost");
putenv("DB_PORT=$dbPort");
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['HTTP_USER_AGENT'] = 'test-runner';
require "$root/src/bootstrap.php";

use App\Auth\Rbac;
use App\Core\Database;
use App\Domain\Ticket;
use App\Repositories\UserRepository;
use App\Security\Encryptor;
use App\Security\PasswordHasher;
use App\Security\PasswordPolicy;
use App\Security\Totp;

$db = Database::connection();

/* -------------------------------------------------------------------------
 * 2. Test web server
 * ---------------------------------------------------------------------- */
$port = 8765;
$serverEnv = array_merge(getenv(), [
    'DB_NAME'                 => TEST_DB,
    'DB_HOST'                 => $dbHost,
    'DB_PORT'                 => $dbPort,
    'SESSION_IDLE_TIMEOUT'    => (string) IDLE_TIMEOUT,
    'REAUTH_WINDOW'           => (string) REAUTH_WINDOW,
    'MAX_REGISTRATIONS_PER_IP' => (string) MAX_REGISTRATIONS,
]);
$server = proc_open([PHP_BINARY, '-S', "127.0.0.1:$port", '-t', "$root/public"], [1 => ['file', 'NUL', 'w'], 2 => ['file', 'NUL', 'w']], $pipes, $root, $serverEnv);
register_shutdown_function(static function () use ($server) {
    proc_terminate($server);
});
$base = "http://127.0.0.1:$port/";
for ($i = 0; $i < 50 && @file_get_contents($base . 'login.php') === false; $i++) {
    usleep(100000);
}

$t = new TestRunner();
$client = static fn (string $ua = 'Mozilla/5.0 HelpdeskTest') => new HttpClient($base, $ua);

// Accounts created directly in the database for the role tests
$pw = [
    'admin'   => 'Lasallian#Hub2026',
    'tech'    => 'Technician#Desk2026',
    'student' => 'Green&Gold-2026!',
    'other'   => 'Another$Student99',
];
$ids = [
    'admin' => UserRepository::create('t_admin', 't_admin@usls.edu.ph', PasswordHasher::hash($pw['admin']), 'admin'),
    'tech'  => UserRepository::create('t_tech', 't_tech@usls.edu.ph', PasswordHasher::hash($pw['tech']), 'technician'),
    'other' => UserRepository::create('t_other', 't_other@usls.edu.ph', PasswordHasher::hash($pw['other']), 'user'),
];
$legacyHash = password_hash('Legacy#Pass2024', PASSWORD_BCRYPT, ['cost' => 10]);   // what the old system stored
$ids['legacy'] = UserRepository::create('t_legacy', 't_legacy@usls.edu.ph', $legacyHash, 'user');
$ids['inactive'] = UserRepository::create('t_inactive', 't_inactive@usls.edu.ph', PasswordHasher::hash('Inactive#Pass2026'), 'user');
UserRepository::setActive($ids['inactive'], false);

function login(HttpClient $c, string $username, string $password): HttpResponse
{
    return $c->submit('login.php', ['username' => $username, 'password' => $password]);
}

/* =========================================================================
 * UNIT TESTS: security classes
 * ====================================================================== */
$t->section('Password hashing');

$hash = PasswordHasher::hash('Correct#Horse9');
$t->check('Passwords are hashed with Argon2id', str_starts_with($hash, '$argon2id$'), substr($hash, 0, 30) . '...');
$t->check('Correct password verifies', PasswordHasher::verify('Correct#Horse9', $hash)[0]);
$t->check('Wrong password is rejected', !PasswordHasher::verify('Correct#Horse8', $hash)[0]);
$t->check('Every hash has its own random salt', PasswordHasher::hash('Correct#Horse9') !== $hash);
$t->check('Pepper is required (raw password does not match the stored hash)', !password_verify('Correct#Horse9', $hash));
[$ok, $rehash] = PasswordHasher::verify('Legacy#Pass2024', $legacyHash);
$t->check('Old bcrypt hashes are still accepted and flagged for upgrade', $ok && $rehash);
$t->check('Plain text is never stored (hash is not the password)', !str_contains($hash, 'Correct#Horse9'));

$t->section('Password policy');
$t->check('Rejects short passwords', (bool) PasswordPolicy::errors('Ab1!'));
$t->check('Rejects passwords without an uppercase letter', (bool) PasswordPolicy::errors('lowercase#only123'));
$t->check('Rejects passwords without a symbol', (bool) PasswordPolicy::errors('NoSymbolsHere123'));
$t->check('Rejects common passwords such as "Password123!!"', in_array('This password is too common.', PasswordPolicy::errors('Password123!!'), true));
$t->check('Rejects passwords containing the username', (bool) PasswordPolicy::errors('Mariasantos#2026', 'mariasantos'));
$t->check('Rejects long runs of one character', (bool) PasswordPolicy::errors('Aaaaa#Bcdef123'));
$t->check('Accepts a strong password', PasswordPolicy::errors('Green&Gold-2026!', 'student', 'student@usls.edu.ph') === []);

$t->section('Two-factor (TOTP, RFC 6238)');
$rfcSecret = Totp::base32Encode('12345678901234567890');
$t->check('RFC 6238 test vector at T=59 gives 287082', Totp::code($rfcSecret, intdiv(59, 30)) === '287082');
$t->check('RFC 6238 test vector at T=1111111109 gives 081804', Totp::code($rfcSecret, intdiv(1111111109, 30)) === '081804');
$secret = Totp::generateSecret();
$now = Totp::currentStep();
$t->check('A current code is accepted', Totp::verify($secret, Totp::code($secret, $now)) === $now);
$t->check('A replayed code is rejected', Totp::verify($secret, Totp::code($secret, $now), $now) === null);
$t->check('A code from 5 minutes ago is rejected', Totp::verify($secret, Totp::code($secret, $now - 10)) === null);
$t->check('Non-numeric input is rejected', Totp::verify($secret, 'abcdef') === null);

$t->section('Encryption (AES-256-GCM)');
$cipher = Encryptor::encrypt('JBSWY3DPEHPK3PXP');
$t->check('Encrypt then decrypt returns the original', Encryptor::decrypt($cipher) === 'JBSWY3DPEHPK3PXP');
$t->check('Ciphertext does not contain the secret', !str_contains($cipher, 'JBSWY3DPEHPK3PXP'));
$raw = base64_decode($cipher);
$raw[strlen($raw) - 1] = chr(ord($raw[strlen($raw) - 1]) ^ 1);
$t->check('Tampered ciphertext is detected and refused', Encryptor::decrypt(base64_encode($raw)) === null);

$t->section('Authorization rules and input handling');
$t->check('Students can file tickets but not manage them', Rbac::allows('user', 'ticket.create') && !Rbac::allows('user', 'ticket.manage'));
$t->check('Technicians manage tickets but not users', Rbac::allows('technician', 'ticket.manage') && !Rbac::allows('technician', 'user.manage'));
$t->check('Administrators can manage users and read the audit log', Rbac::allows('admin', 'user.manage') && Rbac::allows('admin', 'audit.view'));
$t->check('Unknown roles get no permissions', !Rbac::allows('superuser', 'ticket.create') && !Rbac::allows(null, 'ticket.create'));
$t->check('Output escaping neutralises HTML', e('<script>"x"</script>') === '&lt;script&gt;&quot;x&quot;&lt;/script&gt;');
$t->check('Redirect targets outside the app are refused', safe_next('https://evil.example', 'dashboard.php') === 'dashboard.php' && safe_next('account.php', 'x') === 'account.php');
$t->check('Ticket validation rejects unknown categories and priorities', count(Ticket::validate(['category' => 'Hack', 'priority' => 'Urgent!!', 'subject' => 'Valid subject', 'description' => 'Valid description text'])) === 2);

/* =========================================================================
 * END-TO-END TESTS over HTTP
 * ====================================================================== */
$t->section('HTTP security headers and file protection');
$c = $client();
$r = $c->get('login.php');
$t->check('Content-Security-Policy blocks inline and third-party scripts', str_contains($r->header('content-security-policy'), "script-src 'self'"));
$t->check('X-Frame-Options: DENY (clickjacking)', $r->header('x-frame-options') === 'DENY');
$t->check('X-Content-Type-Options: nosniff', $r->header('x-content-type-options') === 'nosniff');
$t->check('Referrer-Policy is set', $r->header('referrer-policy') !== '');
$t->check('Pages are not cached (Cache-Control: no-store)', str_contains($r->header('cache-control'), 'no-store'));
$t->check('PHP version is not advertised (no X-Powered-By)', $r->header('x-powered-by') === '');
$cookie = $r->header('set-cookie');
$t->check('Session cookie is HttpOnly', stripos($cookie, 'httponly') !== false, $cookie);
$t->check('Session cookie is SameSite=Strict', stripos($cookie, 'samesite=strict') !== false);
foreach (['../src/bootstrap.php', '../config/secrets.php', 'config/secrets.php', 'src/Security/Secrets.php', '../database/schema.sql'] as $path) {
    $t->check("Private file is not reachable: /$path", $c->get($path)->status === 404);
}

$t->section('Registration');
$c = $client();
$r = $c->submit('register.php', ['username' => 'student1', 'email' => 'student1@usls.edu.ph', 'password' => 'password123', 'password_confirm' => 'password123']);
$t->check('Weak password is rejected on the server', $r->status === 200 && str_contains($r->body, 'This password is too common'));
$r = $c->submit('register.php', ['username' => 'student1', 'email' => 'student1@usls.edu.ph', 'password' => $pw['student'], 'password_confirm' => 'Different#Pass2026']);
$t->check('Mismatched confirmation is rejected', str_contains($r->body, 'Passwords do not match'));
$r = $c->submit('register.php', ['username' => '<b>x</b>', 'email' => 'not-an-email', 'password' => $pw['student'], 'password_confirm' => $pw['student']]);
$t->check('Invalid username and e-mail are rejected', str_contains($r->body, 'Username must be') && str_contains($r->body, 'valid e-mail'));
$r = $c->submit('register.php', ['username' => 'student1', 'email' => 'student1@usls.edu.ph', 'password' => $pw['student'], 'password_confirm' => $pw['student'], 'role' => 'admin']);
$t->check('Strong password creates the account', $r->status === 302 && str_ends_with($r->location, 'login.php'));
$new = UserRepository::findByLogin('student1');
$ids['student'] = (int) ($new['id'] ?? 0);
$t->check('A forged "role=admin" field is ignored (new accounts are always students)', ($new['role'] ?? '') === 'user');
$t->check('Stored password is an Argon2id hash', str_starts_with($new['password'] ?? '', '$argon2id$'));
$r = $c->submit('register.php', ['username' => 'student1', 'email' => 'other@usls.edu.ph', 'password' => $pw['student'], 'password_confirm' => $pw['student']]);
$t->check('Duplicate username is rejected', str_contains($r->body, 'already registered'));

$t->section('CSRF protection');
$c = $client();
$c->get('login.php');
$t->check('Sign-in without a CSRF token is refused (400)', $c->post('login.php', ['username' => 't_admin', 'password' => $pw['admin']])->status === 400);
$t->check('Sign-in with a forged CSRF token is refused (400)', $c->post('login.php', ['username' => 't_admin', 'password' => $pw['admin'], 'csrf_token' => str_repeat('a', 64)])->status === 400);
$t->check('Registration without a CSRF token is refused (400)', $c->post('register.php', ['username' => 'csrfuser', 'email' => 'c@usls.edu.ph', 'password' => $pw['student'], 'password_confirm' => $pw['student']])->status === 400);
login($c, 'student1', $pw['student']);
foreach (['dashboard.php' => ['category' => 'Other', 'priority' => 'Low', 'subject' => 'CSRF ticket', 'description' => 'Should never be created'], 'account.php' => ['action' => 'revoke_sessions'], 'logout.php' => [], 'my_tickets.php' => ['action' => 'cancel', 'ticket_id' => 1]] as $page => $data) {
    $t->check("POST $page without a token is refused (400)", $c->post($page, $data)->status === 400);
}
$t->check('No ticket was created by the forged request', (int) $db->query("SELECT COUNT(*) FROM tickets WHERE subject = 'CSRF ticket'")->fetchColumn() === 0);

$t->section('Authentication');
$c = $client();
$wrong = login($c, 't_other', 'Wrong#Password1');
$unknown = login($c, 'no_such_user', 'Wrong#Password1');
$t->check('Wrong password shows a generic message', str_contains($wrong->body, 'Incorrect username or password.'));
$t->check('Unknown username shows the identical message (no account enumeration)', str_contains($unknown->body, 'Incorrect username or password.'));
$r = login($client(), 't_other@usls.edu.ph', $pw['other']);
$t->check('Sign-in with e-mail address works', $r->status === 302 && str_ends_with($r->location, 'dashboard.php'));
$r = login($client(), "' OR '1'='1' -- ", "' OR '1'='1");
$t->check('SQL injection in the sign-in form does not log in', $r->status === 200 && str_contains($r->body, 'Incorrect username or password.'));
$r = login($client(), 't_inactive', 'Inactive#Pass2026');
$t->check('Deactivated accounts cannot sign in, and the reply does not confirm the password', $r->status === 200 && str_contains($r->body, 'Incorrect username or password.'));
$r = login($client(), str_repeat('a', 400), 'Wrong#Password1');
$t->check('A 400-character username is handled safely', $r->status === 200 && str_contains($r->body, 'Incorrect username or password.'));
$r = login($client(), "adm\xff\xfein", 'Wrong#Password1');
$t->check('A username with invalid UTF-8 bytes is handled safely (no error page)', $r->status === 200 && str_contains($r->body, 'Incorrect username or password.'));
$timings = ['real' => [], 'unknown' => []];
$tc = $client();
for ($i = 0; $i < 4; $i++) {
    $start = microtime(true); login($tc, 't_admin', 'Wrong#Timing' . $i); $timings['real'][] = microtime(true) - $start;
    $start = microtime(true); login($tc, 'nobody_' . $i, 'Wrong#Timing' . $i); $timings['unknown'][] = microtime(true) - $start;
}
login($tc, 't_admin', $pw['admin']);   // a success resets the admin's failure counter
sort($timings['real']); sort($timings['unknown']);
$ratio = $timings['real'][1] / max(0.001, $timings['unknown'][1]);
$t->check('Wrong password and unknown user take the same time (no timing enumeration)', $ratio > 0.67 && $ratio < 1.5, sprintf('ratio %.2f', $ratio));
$r = login($client(), 't_legacy', 'Legacy#Pass2024');
$t->check('Account with an old bcrypt hash can still sign in', $r->status === 302);
$t->check('Old hash is upgraded to Argon2id on sign-in', str_starts_with(UserRepository::find($ids['legacy'])['password'], '$argon2id$'));

// Session fixation: an attacker plants a session ID before the victim signs in
$c = $client();
$c->setCookie('USLS_HD_SID', 'attackerchosensessionid0123456789abcdef');
$c->get('login.php');
$before = $c->cookie('USLS_HD_SID');
login($c, 't_other', $pw['other']);
$after = $c->cookie('USLS_HD_SID');
$t->check('Planted session IDs are refused (strict mode)', $before !== 'attackerchosensessionid0123456789abcdef');
$t->check('A new session ID is issued at sign-in (fixation defence)', $after !== '' && $after !== $before);

// Session hijacking: the cookie is replayed from a different browser
$victim = $client();
login($victim, 't_other', $pw['other']);
$thief = $client('EvilBrowser/9.9');
$thief->setCookie('USLS_HD_SID', $victim->cookie('USLS_HD_SID'));
$r = $thief->get('dashboard.php');
$t->check('A stolen session cookie used from another browser is rejected', $r->status === 302 && str_ends_with($r->location, 'login.php'));

// Logout
$c = $client();
login($c, 't_other', $pw['other']);
$c->get('logout.php');
$t->check('Visiting logout.php with GET does not sign out (CSRF-safe)', $c->get('dashboard.php')->status === 200);
$c->submit('dashboard.php', [], 'logout.php');
$t->check('POST logout ends the session', $c->get('dashboard.php')->status === 302);

$t->section('Brute-force protection');
$c = $client();
for ($i = 0; $i < 5; $i++) {
    login($c, 't_other', 'Wrong#Guess' . $i);
}
$r = login($c, 't_other', $pw['other']);
$t->check('After 5 failures even the correct password is refused', str_contains($r->body, 'Too many failed sign-in attempts'));
$t->check('Lockout is written to the audit log', (int) $db->query("SELECT COUNT(*) FROM audit_log WHERE event = 'account_locked' AND username = 't_other'")->fetchColumn() > 0);
for ($i = 0; $i < 5; $i++) {
    login($c, 'ghost_account', 'Wrong#Guess' . $i);
}
$t->check('Non-existent accounts lock out the same way (no enumeration)', str_contains(login($c, 'ghost_account', 'x')->body, 'Too many failed sign-in attempts'));
$a = $client();
login($a, 't_admin', $pw['admin']);
$a->submit('manage_users.php', ['action' => 'unlock', 'user_id' => $ids['other']]);
$t->check('An administrator can clear the lockout', login($client(), 't_other', $pw['other'])->status === 302);

$t->section('Role-based access control (every page, every role)');
$expect = [
    // page                => [student, technician, admin]
    'dashboard.php'       => [200, 302, 302],
    'my_tickets.php'      => [200, 403, 403],
    'account.php'         => [200, 200, 200],
    'admin_dashboard.php' => [403, 200, 200],
    'admin_tickets.php'   => [403, 200, 200],
    'manage_users.php'    => [403, 403, 200],
    'audit_log.php'       => [403, 403, 200],
];
$roles = ['student' => ['student1', $pw['student']], 'technician' => ['t_tech', $pw['tech']], 'admin' => ['t_admin', $pw['admin']]];
$sessions = [];
foreach ($roles as $role => [$name, $password]) {
    $sessions[$role] = $client();
    login($sessions[$role], $name, $password);
}
foreach ($expect as $page => $codes) {
    $actual = [];
    foreach (array_keys($roles) as $i => $role) {
        $actual[] = $sessions[$role]->get($page)->status;
    }
    $t->check(sprintf('%-20s student %d, technician %d, admin %d', $page, ...$codes), $actual === $codes, 'got ' . implode('/', $actual));
}
$notRedirected = [];
foreach (array_keys($expect) as $page) {
    $r = $client()->get($page);
    if ($r->status !== 302 || !str_ends_with($r->location, 'login.php')) {
        $notRedirected[] = "$page ({$r->status})";
    }
}
$t->check('Visitors are sent to the sign-in page from every protected page', $notRedirected === [], implode(', ', $notRedirected));
$t->check('Denied requests are recorded as access_denied', (int) $db->query("SELECT COUNT(*) FROM audit_log WHERE event = 'access_denied'")->fetchColumn() >= 6);
$r = $sessions['student']->get('admin_tickets.php');
$t->check('The 403 page also carries the security headers', str_contains($r->header('content-security-policy'), "default-src 'self'"));

$t->section('Tickets: validation, XSS, SQL injection and ownership');
$s = $sessions['student'];
$r = $s->submit('dashboard.php', ['category' => 'Wi-Fi / Network', 'priority' => 'High', 'subject' => 'No Wi-Fi in the library', 'description' => 'Cannot connect on the 3rd floor since 8 AM.']);
$t->check('A valid ticket is created', $r->status === 302 && str_ends_with($r->location, 'my_tickets.php'));
$r = $s->submit('dashboard.php', ['category' => 'Hacking', 'priority' => 'Urgent', 'subject' => 'Bad', 'description' => 'short']);
$t->check('Invalid category, priority and lengths are rejected', str_contains($r->body, 'Choose a category') && str_contains($r->body, 'Choose a priority'));
$xss = '<script>alert("xss")</script><img src=x onerror=alert(1)>';
$s->submit('dashboard.php', ['category' => 'Other', 'priority' => 'Low', 'subject' => $xss, 'description' => 'Stored XSS attempt in the subject line.']);
$mine = $s->get('my_tickets.php')->body;
$queue = $sessions['technician']->get('admin_tickets.php')->body;
$t->check('Stored XSS payload is escaped on My tickets', !str_contains($mine, '<script>alert') && str_contains($mine, '&lt;script&gt;'));
$t->check('Stored XSS payload is escaped in the technician queue', !str_contains($queue, '<script>alert') && !str_contains($queue, '<img src=x'));
$r = $sessions['technician']->get("admin_tickets.php?q=" . rawurlencode("'; DROP TABLE tickets; --") . "&status=" . rawurlencode("Open' OR '1'='1"));
$t->check('SQL injection in search and filters is harmless', $r->status === 200 && (int) $db->query('SELECT COUNT(*) FROM tickets')->fetchColumn() >= 2);
$ticketId = (int) $db->query("SELECT id FROM tickets WHERE subject = 'No Wi-Fi in the library'")->fetchColumn();
$o = $client();
login($o, 't_other', $pw['other']);
$o->submit('my_tickets.php', ['action' => 'cancel', 'ticket_id' => $ticketId]);
$t->check("Another student cannot withdraw someone else's ticket (IDOR)", TicketExists($db, $ticketId));
$t->check('Other students do not see the ticket in their list', !str_contains($o->get('my_tickets.php')->body, 'No Wi-Fi in the library'));
$tech = $sessions['technician'];
$tech->submit('admin_tickets.php', ['action' => 'update', 'ticket_id' => $ticketId, 'status' => 'Hacked', 'priority' => 'High']);
$t->check('Invalid status values are refused', $db->query("SELECT status FROM tickets WHERE id = $ticketId")->fetchColumn() === 'Open');
$tech->submit('admin_tickets.php', ['action' => 'claim', 'ticket_id' => $ticketId]);
$row = $db->query("SELECT status, assigned_to FROM tickets WHERE id = $ticketId")->fetch();
$t->check('Technician can claim a ticket (assigned and moved to In Progress)', $row['status'] === 'In Progress' && (int) $row['assigned_to'] === $ids['tech']);
$tech->submit('admin_tickets.php', ['action' => 'update', 'ticket_id' => $ticketId, 'status' => 'Resolved', 'priority' => 'Normal']);
$t->check('Technician can resolve a ticket', $db->query("SELECT status FROM tickets WHERE id = $ticketId")->fetchColumn() === 'Resolved');
$s->submit('my_tickets.php', ['action' => 'cancel', 'ticket_id' => $ticketId]);
$t->check('A ticket already being handled cannot be withdrawn', TicketExists($db, $ticketId));
$own = (int) $db->query("SELECT id FROM tickets WHERE category = 'Other' AND user_id = {$ids['student']}")->fetchColumn();
$s->submit('my_tickets.php', ['action' => 'cancel', 'ticket_id' => $own]);
$t->check('A student can withdraw their own open ticket', !TicketExists($db, $own));

function TicketExists(PDO $db, int $id): bool
{
    return (int) $db->query('SELECT COUNT(*) FROM tickets WHERE id = ' . $id)->fetchColumn() === 1;
}

$t->section('Account administration');
$a = $client();
login($a, 't_admin', $pw['admin']);   // fresh sign-in keeps us inside the step-up window
$victim = $client();
login($victim, 't_other', $pw['other']);
$a->submit('manage_users.php', ['action' => 'role', 'user_id' => $ids['other'], 'role' => 'technician']);
$t->check('Role change is saved', UserRepository::find($ids['other'])['role'] === 'technician');
$t->check('The user is signed out immediately after a role change', $victim->get('my_tickets.php')->status === 302);
$a->submit('manage_users.php', ['action' => 'role', 'user_id' => $ids['other'], 'role' => 'superadmin']);
$t->check('Unknown roles are refused', UserRepository::find($ids['other'])['role'] === 'technician');
$a->submit('manage_users.php', ['action' => 'role', 'user_id' => $ids['other'], 'role' => 'user']);
login($victim, 't_other', $pw['other']);
$a->submit('manage_users.php', ['action' => 'toggle_active', 'user_id' => $ids['other']]);
$t->check('Deactivating an account ends its open sessions', $victim->get('dashboard.php')->status === 302);
$t->check('A deactivated account cannot sign back in', str_contains(login($client(), 't_other', $pw['other'])->body, 'Incorrect username or password.'));
$a->submit('manage_users.php', ['action' => 'toggle_active', 'user_id' => $ids['other']]);
$a->submit('manage_users.php', ['action' => 'delete', 'user_id' => $ids['admin']]);
$t->check('An administrator cannot delete their own account', UserRepository::find($ids['admin']) !== null);
$a->submit('manage_users.php', ['action' => 'role', 'user_id' => $ids['admin'], 'role' => 'user']);
$t->check('An administrator cannot demote themselves', UserRepository::find($ids['admin'])['role'] === 'admin');
$proc = proc_open([PHP_BINARY, "$root/setup/create_admin.php"], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $cliPipes, $root,
    array_merge(getenv(), ['DB_NAME' => TEST_DB, 'ADMIN_USERNAME' => 'brand_new_admin', 'ADMIN_EMAIL' => 't_other@usls.edu.ph', 'ADMIN_PASSWORD' => 'Setup#Script2026']));
stream_get_contents($cliPipes[1]); stream_get_contents($cliPipes[2]);
$exit = proc_close($proc);
$t->check('Admin setup script refuses an e-mail that belongs to another account', $exit === 1 && UserRepository::find($ids['other'])['role'] === 'user');
$tech->submit('manage_users.php', ['action' => 'delete', 'user_id' => $ids['student']]);
$t->check('A technician cannot use admin actions (forged POST is refused)', UserRepository::find($ids['student']) !== null);

$t->section('Step-up authentication and redirects');
$a = $client();
login($a, 't_admin', $pw['admin']);
sleep(REAUTH_WINDOW + 1);
$r = $a->get('manage_users.php');
$t->check('User management asks for the password again after the step-up window', $r->status === 302 && str_contains($r->location, 'confirm_password.php'));
$r = $a->submit('confirm_password.php?next=manage_users.php', ['password' => 'Wrong#Password1']);
$t->check('A wrong confirmation password is refused', str_contains($r->body, 'Incorrect password'));
$r = $a->submit('confirm_password.php?next=manage_users.php', ['password' => $pw['admin']]);
$t->check('The correct password unlocks the page', $r->status === 302 && str_ends_with($r->location, 'manage_users.php'));
$r = $a->submit('confirm_password.php?next=' . rawurlencode('https://evil.example/phish'), ['password' => $pw['admin']]);
$t->check('Redirects to other websites are refused (open redirect)', $r->status === 302 && !str_contains($r->location, 'evil.example'));

$t->section('Password change');
$c1 = $client();
$c2 = $client('Mozilla/5.0 SecondDevice');
login($c1, 't_tech', $pw['tech']);
login($c2, 't_tech', $pw['tech']);
$r = $c1->submit('account.php', ['action' => 'change_password', 'current_password' => 'Wrong#Current1', 'new_password' => 'Brand#NewPass2026', 'confirm_password' => 'Brand#NewPass2026']);
$t->check('Wrong current password is refused', str_contains($r->body, 'current password is incorrect'));
$r = $c1->submit('account.php', ['action' => 'change_password', 'current_password' => $pw['tech'], 'new_password' => 'weakpass', 'confirm_password' => 'weakpass']);
$t->check('A weak new password is refused', str_contains($r->body, 'Use at least 12 characters'));
$r = $c1->submit('account.php', ['action' => 'change_password', 'current_password' => $pw['tech'], 'new_password' => 'Brand#NewPass2026', 'confirm_password' => 'Brand#NewPass2026']);
$t->check('Password is changed', $r->status === 302);
$t->check('The current session stays signed in', $c1->get('account.php')->status === 200);
$t->check('Other devices are signed out', $c2->get('account.php')->status === 302);
$t->check('The old password no longer works', login($client(), 't_tech', $pw['tech'])->status === 200);
$t->check('The new password works', login($client(), 't_tech', 'Brand#NewPass2026')->status === 302);

$g = $client();
login($g, 't_tech', 'Brand#NewPass2026');
for ($i = 0; $i < 5; $i++) {
    $g->submit('account.php', ['action' => 'change_password', 'current_password' => 'Guess#Number' . $i, 'new_password' => 'Brand#Other2026x', 'confirm_password' => 'Brand#Other2026x']);
}
$t->check('Guessing the current password on the account page is limited (session ends)', $g->get('account.php')->status === 302);
App\Security\RateLimiter::clear('t_tech');

$t->section('Two-factor authentication');
$c = $client();
login($c, 'student1', $pw['student']);
$r = $c->submit('account.php', ['action' => '2fa_start', 'current_password' => 'Wrong#Password1']);
$t->check('Starting 2FA setup requires the correct password', str_contains($r->body, 'password is incorrect') && !str_contains($r->body, 'id="totp-secret"'));
$c->submit('account.php', ['action' => '2fa_start', 'current_password' => $pw['student']]);
preg_match('/id="totp-secret">([A-Z2-7 ]+)</', $c->get('account.php')->body, $m);
$secret = str_replace(' ', '', $m[1] ?? '');
$t->check('A 160-bit setup key is shown', strlen($secret) === 32);
$r = $c->submit('account.php', ['action' => '2fa_enable', 'code' => '000000']);
$t->check('A wrong setup code does not enable 2FA', str_contains($r->body, 'did not match') && !UserRepository::find($ids['student'])['totp_enabled']);
$enableStep = Totp::currentStep();
$c->submit('account.php', ['action' => '2fa_enable', 'code' => Totp::code($secret, $enableStep)]);
$page = $c->get('account.php')->body;
preg_match_all('/<span>([0-9A-F]{6}-[0-9A-F]{6})<\/span>/', $page, $codes);
$stored = UserRepository::find($ids['student']);
$t->check('2FA is enabled with a valid code', (bool) $stored['totp_enabled']);
$t->check('8 recovery codes are shown once', count($codes[1]) === 8 && !str_contains($c->get('account.php')->body, 'id="recovery-codes"'));
$t->check('The 2FA secret is encrypted in the database', $stored['totp_secret'] !== $secret && Encryptor::decrypt($stored['totp_secret']) === $secret);
$r = $c->submit('account.php', ['action' => '2fa_start', 'current_password' => $pw['student']]);
$t->check('2FA cannot be silently replaced while it is on', str_contains($r->body, 'already on'));
$t->check('Recovery codes are stored hashed', (int) $db->query("SELECT COUNT(*) FROM recovery_codes WHERE user_id = {$ids['student']} AND code_hash LIKE '\$argon2id\$%'")->fetchColumn() === 8);

$c = $client();
$r = login($c, 'student1', $pw['student']);
$t->check('Password alone now leads to the verification step', $r->status === 302 && str_ends_with($r->location, 'verify_2fa.php'));
$t->check('Protected pages stay locked until the code is entered', $c->get('dashboard.php')->status === 302);
$t->check('A wrong code is refused', str_contains($c->submit('verify_2fa.php', ['code' => '123456'])->body, 'not valid'));
$r = $c->submit('verify_2fa.php', ['code' => Totp::code($secret, $enableStep)]);
$t->check('A code that was already used is refused (replay protection)', $r->status === 200);
$r = $c->submit('verify_2fa.php', ['code' => Totp::code($secret, $enableStep + 1)]);
$t->check('A fresh code completes sign-in', $r->status === 302 && str_ends_with($r->location, 'dashboard.php'));

$c = $client();
login($c, 'student1', $pw['student']);
$r = $c->submit('verify_2fa.php', ['code' => $codes[1][0]]);
$t->check('A recovery code can be used instead', $r->status === 302);
$c = $client();
login($c, 'student1', $pw['student']);
$t->check('A recovery code works only once', $c->submit('verify_2fa.php', ['code' => $codes[1][0]])->status === 200);

$early = $client();
login($early, 'student1', $pw['student']);   // a second half-finished sign-in, opened before the lock
$c = $client();
login($c, 'student1', $pw['student']);
for ($i = 0; $i < 5; $i++) {
    $r = $c->submit('verify_2fa.php', ['code' => sprintf('%06d', 100000 + $i)]);
}
$t->check('Five wrong codes end the sign-in attempt', $r->status === 302 && str_ends_with($r->location, 'login.php'));
$t->check('...and lock the account', str_contains(login($client(), 'student1', $pw['student'])->body, 'Too many failed sign-in attempts'));
$db->exec('UPDATE users SET totp_last_step = ' . (Totp::currentStep() - 2) . " WHERE id = {$ids['student']}");
$r = $early->submit('verify_2fa.php', ['code' => Totp::code($secret, Totp::currentStep())]);
$t->check('Other half-finished sign-ins are blocked too, even with a correct code', !($r->status === 302 && str_ends_with($r->location, 'dashboard.php')));
App\Security\RateLimiter::clear('student1');

$p = $client();
login($p, 'student1', $pw['student']);
UserRepository::bumpSessionVersion($ids['student']);   // what a password change or "sign out everywhere" does
$db->exec('UPDATE users SET totp_last_step = ' . (Totp::currentStep() - 2) . " WHERE id = {$ids['student']}");
$r = $p->submit('verify_2fa.php', ['code' => Totp::code($secret, Totp::currentStep())]);
$t->check('A password change cancels half-finished sign-ins', $r->status === 302 && str_ends_with($r->location, 'login.php'));

$db->exec("UPDATE users SET totp_last_step = 100 WHERE id = {$ids['student']}");
$t->check('A two-factor time step can be claimed only once (no race)', UserRepository::claimTotpStep($ids['student'], 200) && !UserRepository::claimTotpStep($ids['student'], 200));
$fresh = App\Auth\RecoveryCodes::regenerate($ids['other']);
$t->check('A recovery code can be consumed only once (no race)', App\Auth\RecoveryCodes::consume($ids['other'], $fresh[0]) && !App\Auth\RecoveryCodes::consume($ids['other'], $fresh[0]));
$t->section('Audit log integrity');
$a = $client();
login($a, 't_admin', $pw['admin']);
$r = $a->submit('audit_log.php', ['action' => 'verify']);
$t->check('The untouched log verifies as intact', str_contains($r->body, 'Integrity verified'));
$events = (int) $db->query('SELECT COUNT(*) FROM audit_log')->fetchColumn();
$t->check("Security events are being recorded ($events so far)", $events > 50);
$db->exec("UPDATE audit_log SET details = 'covering my tracks' WHERE id = 5");
$r = $a->submit('audit_log.php', ['action' => 'verify']);
$t->check('Editing a log row directly in the database is detected', str_contains($r->body, 'Integrity check failed at entry #5'));

$t->section('Session timeout');
$c = $client();
login($c, 't_admin', $pw['admin']);
$t->check('Signed in', $c->get('admin_dashboard.php')->status === 200);
sleep(IDLE_TIMEOUT + 1);
$r = $c->get('admin_dashboard.php');
$t->check('An idle session expires automatically', $r->status === 302 && str_ends_with($r->location, 'login.php'));

$t->section('Rate limits per network address');
$db->exec("DELETE FROM auth_attempts WHERE action = 'register'");
$c = $client();
for ($i = 1; $i <= MAX_REGISTRATIONS; $i++) {
    $c->submit('register.php', ['username' => "bulk$i", 'email' => "bulk$i@usls.edu.ph", 'password' => $pw['student'], 'password_confirm' => $pw['student']]);
}
$r = $c->submit('register.php', ['username' => 'bulk_extra', 'email' => 'bulk_extra@usls.edu.ph', 'password' => $pw['student'], 'password_confirm' => $pw['student']]);
$t->check('Mass account creation from one address is blocked', str_contains($r->body, 'Too many accounts'));
$db->exec("DELETE FROM auth_attempts WHERE action = 'login'");
for ($i = 0; $i < (int) App\Core\Config::get('security.max_login_failures_per_ip'); $i++) {
    login($c, "spray_target_$i", 'Wrong#Guess1');
}
$t->check('Password spraying across many accounts locks the address', str_contains(login($c, 't_admin', $pw['admin'])->body, 'Too many failed sign-in attempts'));
$db->exec("DELETE FROM auth_attempts");

$t->section('Information leakage');
$t->check('No PHP errors, warnings or stack traces in any response', HttpClient::$leaks === [], implode(', ', array_unique(HttpClient::$leaks)));

exit($t->report());
