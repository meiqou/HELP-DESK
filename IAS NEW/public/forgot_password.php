<?php
require dirname(__DIR__) . '/src/bootstrap.php';

use App\Auth\EmailCodes;
use App\Auth\PasswordReset;
use App\Core\Config;

if ($user = current_user()) {
    redirect(home_for($user));
}

$errors = [];
$error = '';
$notice = '';
$email = (string) ($_SESSION['password_reset_email'] ?? '');
$startedAt = (int) ($_SESSION['password_reset_started'] ?? 0);
$resetStep = $email !== '' && $startedAt > 0;
if ($resetStep && time() - $startedAt > EmailCodes::TTL_SECONDS) {
    unset($_SESSION['password_reset_email'], $_SESSION['password_reset_started']);
    $email = '';
    $resetStep = false;
    $error = 'Your reset request expired. Please request a new code.';
}

if (is_post()) {
    verify_csrf();
    switch (post_string('action')) {
        case 'request_code':
            $email = strtolower(post_string('email'));
            if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 254) {
                $errors[] = 'Enter a valid e-mail address.';
                $resetStep = false;
                break;
            }
            PasswordReset::requestCode($email);
            $_SESSION['password_reset_email'] = $email;
            $_SESSION['password_reset_started'] = time();
            $resetStep = true;
            $notice = 'If an account exists for that address, a reset code has been sent. Check your inbox and spam folder.';
            break;

        case 'resend_code':
            if (!$resetStep) {
                $error = 'Your reset request expired. Please request a new code.';
                break;
            }
            PasswordReset::requestCode($email);
            $notice = 'If an account exists for that address, a reset code has been sent. Check your inbox and spam folder.';
            break;

        case 'reset_password':
            if (!$resetStep) {
                $error = 'Your reset request expired. Please request a new code.';
                break;
            }
            $result = PasswordReset::complete($email, post_string('code'), post_raw('new_password'), post_raw('confirm_password'));
            if ($result['status'] === 'success') {
                unset($_SESSION['password_reset_email'], $_SESSION['password_reset_started']);
                flash('success', 'Your password has been reset. Sign in with your new password.');
                redirect('login.php');
            }
            if ($result['status'] === 'invalid_input') {
                $errors = $result['errors'];
            } elseif ($result['status'] === 'limited') {
                $error = 'Too many verification attempts. Please wait before trying again.';
            } else {
                $error = 'That code is invalid or expired. Request a new code and try again.';
            }
            break;

        case 'change_email':
            unset($_SESSION['password_reset_email'], $_SESSION['password_reset_started']);
            $email = '';
            $resetStep = false;
            break;
    }
}

view('forgot_password', [
    'pageTitle' => 'Reset password',
    'errors'    => $errors,
    'error'     => $error,
    'notice'    => $notice,
    'email'     => $email,
    'resetStep' => $resetStep,
    'sec'       => Config::get('security'),
], 'auth');
