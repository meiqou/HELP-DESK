<?php
require dirname(__DIR__) . '/src/bootstrap.php';

use App\Auth\Auth;
use App\Core\Session;

$account = Auth::pendingEnrolmentUser();
if (!$account) {
    if (isset($_SESSION['pending_enrolment'])) {
        Session::destroy('Your setup window expired. Please sign in again.');
    }
    redirect('login.php');
}

$error = '';

if (is_post()) {
    verify_csrf();
    switch (Auth::completeEnrolment(post_string('code'))) {
        case 'success':
            flash('success', 'Two-factor authentication is now on. Save your recovery codes below.');
            redirect('account.php#two-factor');
        case 'expired':
            Session::destroy('Your setup window expired. Please sign in again.');
            redirect('login.php');
        default:
            $error = 'That code did not match. Make sure your phone\'s clock is correct and try again.';
    }
}

view('setup_2fa', [
    'pageTitle' => 'Set up two-factor sign-in',
    'error'     => $error,
    'account'   => $account,
    'secret'    => $_SESSION['pending_enrolment']['secret'],
], 'auth');
