<?php
require dirname(__DIR__) . '/src/bootstrap.php';

use App\Auth\Auth;
use App\Auth\EmailCodes;
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
$notice = '';
$byEmail = $account['two_factor_method'] === 'email';
$emailStatus = $_SESSION['pending_2fa']['email_status'] ?? '';
unset($_SESSION['pending_2fa']['email_status']);
// 'too_soon' is fine here: a code sent moments ago still works.
if ($emailStatus !== '' && $emailStatus !== 'sent' && $emailStatus !== 'too_soon') {
    $error = EmailCodes::message($emailStatus);   // the code sent at sign-in did not go out
}

if (is_post() && post_string('action') === 'resend') {
    verify_csrf();
    $status = Auth::resendLoginCode();
    if ($status === 'sent') {
        $notice = EmailCodes::message($status);
    } else {
        $error = EmailCodes::message($status);
    }
} elseif (is_post()) {
    verify_csrf();
    $result = Auth::verifySecondFactor(post_string('code'));

    switch ($result['status']) {
        case 'success':
            if ($result['recovery']) {
                flash('warning', 'You signed in with a recovery code. You have ' . RecoveryCodes::remaining((int) $account['id']) . ' left. Consider generating new ones.');
            }
            redirect(home_for($result['user']));
        case 'locked':
            Session::destroy('Too many invalid codes. Your account is locked for ' . format_countdown((int) $result['retry_after']) . ' minutes.');
            redirect('login.php');
        case 'expired':
            Session::destroy('Your verification window expired. Please sign in again.');
            redirect('login.php');
        default:
            $error = 'That code is not valid. ' . attempts_text($result['attempts_left']) . ' left before your account locks.';
    }
}

view('verify_2fa', [
    'pageTitle'   => 'Two-factor verification',
    'error'       => $error,
    'notice'      => $notice,
    'account'     => $account,
    'byEmail'     => $byEmail,
    'maskedEmail' => EmailCodes::mask($account['email']),
], 'auth');
