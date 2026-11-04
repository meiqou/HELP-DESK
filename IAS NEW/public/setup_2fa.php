<?php
require dirname(__DIR__) . '/src/bootstrap.php';

use App\Auth\Auth;
use App\Auth\EmailCodes;
use App\Core\Mailer;
use App\Core\Session;

$account = Auth::pendingEnrolmentUser();
if (!$account) {
    if (isset($_SESSION['pending_enrolment'])) {
        Session::destroy('Your setup window expired. Please sign in again.');
    }
    redirect('login.php');
}

$emailAvailable = Mailer::isConfigured();
$method = ($emailAvailable && ($_GET['method'] ?? '') === 'email') ? 'email' : 'app';
$error = '';
$notice = '';

if (is_post()) {
    verify_csrf();

    switch (post_string('action')) {
        case 'send_email':
            $status = $emailAvailable ? Auth::sendEnrolmentEmail() : 'failed';
            if ($status === 'expired') {
                Session::destroy('Your setup window expired. Please sign in again.');
                redirect('login.php');
            }
            if ($status === 'sent') {
                $notice = 'We sent a 6-digit code to ' . EmailCodes::mask($account['email']) . '. Check your inbox and spam folder.';
            } else {
                $error = EmailCodes::message($status);
            }
            break;

        case 'verify_email':
            $result = Auth::completeEmailEnrolment(post_string('code'));
            switch ($result['status']) {
                case 'success':
                    flash('success', 'Two-factor authentication is now on. Save your recovery codes below.');
                    redirect('account.php#two-factor');
                case 'expired':
                    Session::destroy('Your setup window expired. Please sign in again.');
                    redirect('login.php');
                case 'locked':
                    Session::destroy('Too many invalid codes. Your account is locked for ' . format_countdown((int) $result['retry_after']) . ' minutes.');
                    redirect('login.php');
                default:
                    $error = 'That code is not valid or has expired. ' . attempts_text($result['attempts_left']) . ' left before your account locks.';
            }
            break;

        default:
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
}

view('setup_2fa', [
    'pageTitle'      => 'Set up two-factor sign-in',
    'error'          => $error,
    'notice'         => $notice,
    'account'        => $account,
    'secret'         => $_SESSION['pending_enrolment']['secret'],
    'method'         => $method,
    'emailAvailable' => $emailAvailable,
    'emailSent'      => !empty($_SESSION['pending_enrolment']['email_sent']),
    'maskedEmail'    => EmailCodes::mask($account['email']),
    'loadQr'         => true,
], 'auth');
