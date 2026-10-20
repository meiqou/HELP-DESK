<?php
require dirname(__DIR__) . '/src/bootstrap.php';

use App\Auth\Registration;
use App\Core\Config;

if ($user = current_user()) {
    redirect(home_for($user));
}

$errors = [];
$username = '';
$email = '';

if (is_post()) {
    verify_csrf();
    $username = post_string('username');
    $email = strtolower(post_string('email'));
    $errors = Registration::register($username, $email, post_raw('password'), post_raw('password_confirm'));

    if (!$errors) {
        flash('success', 'Your account has been created. You can now sign in.');
        redirect('login.php');
    }
}

view('register', [
    'pageTitle' => 'Create account',
    'errors'    => $errors,
    'username'  => $username,
    'email'     => $email,
    'sec'       => Config::get('security'),
], 'auth');
