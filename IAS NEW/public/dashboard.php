<?php
require dirname(__DIR__) . '/src/bootstrap.php';

use App\Auth\Auth;
use App\Domain\Ticket;
use App\Repositories\TicketRepository;
use App\Security\AuditLog;

$user = Auth::requireLogin();
if (!can('ticket.create')) {
    redirect(home_for($user));   // staff accounts use the support desk overview instead
}

$errors = [];
$old = ['category' => '', 'priority' => 'Normal', 'subject' => '', 'description' => ''];

if (is_post()) {
    verify_csrf();
    $old = [
        'category'    => post_string('category'),
        'priority'    => post_string('priority'),
        'subject'     => post_string('subject'),
        'description' => post_string('description'),
    ];
    $errors = Ticket::validate($old);

    if (!$errors) {
        $id = TicketRepository::create((int) $user['id'], $old['category'], $old['priority'], $old['subject'], $old['description']);
        AuditLog::record('ticket_created', 'Ticket #' . $id);
        flash('success', 'Your ticket has been submitted. IT Services will review it shortly.');
        redirect('my_tickets.php');   // Post/Redirect/Get prevents duplicate submissions on refresh
    }
}

$counts = TicketRepository::statusCounts((int) $user['id']);

view('dashboard', [
    'pageTitle'    => 'Dashboard',
    'user'         => $user,
    'errors'       => $errors,
    'old'          => $old,
    'counts'       => $counts,
    'totalTickets' => array_sum($counts),
    'recent'       => TicketRepository::recentForUser((int) $user['id']),
]);
