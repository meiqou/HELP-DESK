<?php
require dirname(__DIR__) . '/src/bootstrap.php';

use App\Auth\Registration;
use App\Core\Config;

if ($user = current_user()) {
    redirect(home_for($user));
}

$errors = [];
$firstName = '';
$lastName = '';
$username = '';
$email = '';

if (is_post()) {
    verify_csrf();
    $firstName = post_string('first_name');
    $lastName = post_string('last_name');
    $username = post_string('username');
    $email = strtolower(post_string('email'));
    $errors = Registration::register($firstName, $lastName, $username, $email, post_raw('password'), post_raw('password_confirm'));

    if (!$errors) {
        flash('success', 'Your account has been created. You can now sign in.');
        redirect('login.php');
    }
}

view('register', [
    'pageTitle' => 'Create account',
    'errors'    => $errors,
    'firstName' => $firstName,
    'lastName'  => $lastName,
    'username'  => $username,
    'email'     => $email,
    'sec'       => Config::get('security'),
], 'auth');
