<?php
require dirname(__DIR__) . '/src/bootstrap.php';

use App\Auth\Auth;
use App\Repositories\TicketRepository;
use App\Repositories\UserRepository;
use App\Security\AuditLog;

$user = Auth::requirePermission('admin.dashboard');

$queue = TicketRepository::queueSummary();

$security = null;
$events = [];
if (can('audit.view')) {
    $totals = UserRepository::totals();
    $audit = AuditLog::summary();
    $security = [
        'users'      => $totals['users'],
        'mfa'        => $totals['mfa'],
        'failed_24h' => $audit['failed_logins'],
        'locked_24h' => $audit['lockouts'],
    ];
    $events = AuditLog::recentWarnings();
}

view('admin_dashboard', [
    'pageTitle'    => 'Overview',
    'user'         => $user,
    'ticketCounts' => ['Open' => $queue['open'], 'In Progress' => $queue['in_progress'], 'Resolved' => $queue['resolved']],
    'urgent'       => $queue['urgent'],
    'security'     => $security,
    'events'       => $events,
]);
