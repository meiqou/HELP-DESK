<?php
require dirname(__DIR__) . '/src/bootstrap.php';

view('home', [
    'pageTitle'  => 'Home',
    'publicPage' => true,
    'mainClass'  => 'landing',
    'user'       => current_user(),
]);
