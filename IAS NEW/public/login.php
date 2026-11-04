<?php
require dirname(__DIR__) . '/src/bootstrap.php';

use App\Auth\Auth;

if ($user = current_user()) {
    redirect(home_for($user));
}

$error = '';
$username = '';
$cooldown = 0;

if (is_post()) {
    verify_csrf();
    $username = post_string('username');
    $result = Auth::attempt($username, post_raw('password'));

    switch ($result['status']) {
        case 'success':
            redirect(home_for($result['user']));
        case 'two_factor':
            redirect('verify_2fa.php');
        case 'enrol':
            redirect('setup_2fa.php');
        case 'locked':
            $cooldown = (int) $result['retry_after'];
            break;
        case 'empty':
            $error = 'Enter your username and password.';
            break;
        default:
            // Same message for unknown users and wrong passwords (no account enumeration).
            $error = 'Incorrect username or password. ' . attempts_text($result['attempts_left']) . ' left before a cooldown.';
    }
}

view('login', ['pageTitle' => 'Sign in', 'error' => $error, 'username' => $username, 'cooldown' => $cooldown], 'auth');
