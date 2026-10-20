<?php
require dirname(__DIR__) . '/src/bootstrap.php';

use App\Auth\Auth;
use App\Auth\RecoveryCodes;
use App\Core\Session;

$account = Auth::pendingTwoFactorUser();
if (!$account) {
    if (isset($_SESSION['pending_2fa'])) {
        Session::destroy('Your verification window expired. Please sign in again.');
    }
    redirect('login.php');
}

$error = '';

if (is_post()) {
    verify_csrf();
    $result = Auth::verifySecondFactor(post_string('code'));

    switch ($result['status']) {
        case 'success':
            if ($result['recovery']) {
                flash('warning', 'You signed in with a recovery code. You have ' . RecoveryCodes::remaining((int) $account['id']) . ' left. Consider generating new ones.');
            }
            redirect(home_for($result['user']));
        case 'locked':
            Session::destroy('Too many invalid codes. Your account is temporarily locked.');
            redirect('login.php');
        case 'expired':
            Session::destroy('Your verification window expired. Please sign in again.');
            redirect('login.php');
        default:
            $error = 'That code is not valid. Check your authenticator app and try again.';
    }
}

view('verify_2fa', ['pageTitle' => 'Two-factor verification', 'error' => $error, 'account' => $account], 'auth');
