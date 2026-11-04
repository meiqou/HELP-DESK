<?php
/**
 * Copy this file to config/mail.local.php and fill it in to turn on e-mailed sign-in codes.
 * config/mail.local.php is excluded from Git, so the password is never committed.
 *
 * For Gmail: turn on 2-Step Verification on the sending account, then create an App Password at
 * https://myaccount.google.com/apppasswords and use it below (not the normal Gmail password).
 */
return [
    'host'       => 'smtp.gmail.com',
    'port'       => 587,
    'encryption' => 'tls',
    'username'   => 'your.helpdesk.account@gmail.com',
    'password'   => 'your-16-character-app-password',
    'from'       => 'your.helpdesk.account@gmail.com',
    'from_name'  => 'USLS IT Helpdesk',
];
