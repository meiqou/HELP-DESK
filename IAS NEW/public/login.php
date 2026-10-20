<?php
require dirname(__DIR__) . '/src/bootstrap.php';

use App\Auth\Auth;
use App\Core\Config;

if ($user = current_user()) {
    redirect(home_for($user));
}

$error = '';
$username = '';

if (is_post()) {
    verify_csrf();
    $username = post_string('username');
    $result = Auth::attempt($username, post_raw('password'));

    switch ($result['status']) {
        case 'success':
            redirect(home_for($result['user']));
        case 'two_factor':
            redirect('verify_2fa.php');
        case 'locked':
            $minutes = (int) ceil(Config::get('security.lockout_window') / 60);
            $error = "Too many failed sign-in attempts. Please wait {$minutes} minutes and try again.";
            break;
        case 'empty':
            $error = 'Enter your username and password.';
            break;
        default:
            // Same message for unknown users and wrong passwords (no account enumeration).
            $error = 'Incorrect username or password.';
    }
}

view('login', ['pageTitle' => 'Sign in', 'error' => $error, 'username' => $username], 'auth');
