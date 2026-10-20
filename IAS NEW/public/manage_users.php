<?php
require dirname(__DIR__) . '/src/bootstrap.php';

use App\Auth\Auth;
use App\Auth\UserAdministration;
use App\Core\Config;
use App\Repositories\UserRepository;
use App\Security\RateLimiter;

$admin = Auth::requirePermission('user.manage');
Auth::requireRecentAuth('manage_users.php');   // step-up: password confirmed in the last 10 minutes

if (is_post()) {
    verify_csrf();
    [$type, $message] = UserAdministration::perform($admin, post_int('user_id'), post_string('action'), post_string('role'));
    flash($type, $message);
    redirect('manage_users.php');
}

$s = Config::get('security');
$users = array_map(static function (array $u) use ($s): array {
    $u['locked'] = RateLimiter::failures('login', $u['username'], $s['lockout_window']) >= $s['max_login_failures_per_account'];
    return $u;
}, UserRepository::allWithTicketCounts());

view('manage_users', ['pageTitle' => 'Users', 'admin' => $admin, 'users' => $users]);
