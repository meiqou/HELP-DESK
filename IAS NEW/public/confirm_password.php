<?php
require dirname(__DIR__) . '/src/bootstrap.php';

use App\Auth\Auth;

$user = Auth::requireLogin();
$next = safe_next($_GET['next'] ?? null, home_for($user));
$error = '';

if (is_post()) {
    verify_csrf();
    $result = Auth::confirmPassword($user, post_raw('password'));
    if ($result === 'ok') {
        redirect($next);
    }
    if ($result === 'signed_out') {
        redirect('login.php');
    }
    $error = 'Incorrect password.';
}

view('confirm_password', ['pageTitle' => 'Confirm password', 'error' => $error, 'user' => $user]);
