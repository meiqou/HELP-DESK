<?php
require dirname(__DIR__) . '/src/bootstrap.php';

use App\Auth\Auth;

// Signing out changes state, so it only accepts POST with a valid CSRF token.
// A plain link could be triggered by any other website.
if (!is_post()) {
    redirect('index.php');
}
verify_csrf();

Auth::logout();
redirect('login.php');
