<?php
require dirname(__DIR__) . '/src/bootstrap.php';

use App\Auth\Auth;
use App\Domain\Ticket;
use App\Repositories\TicketRepository;
use App\Security\AuditLog;

$user = Auth::requirePermission('ticket.view_own');

if (is_post() && post_string('action') === 'cancel') {
    verify_csrf();
    $ticketId = post_int('ticket_id');

    // Ownership is checked inside the query (prevents IDOR).
    if (TicketRepository::withdraw($ticketId, (int) $user['id'])) {
        AuditLog::record('ticket_cancelled', 'Ticket #' . $ticketId);
        flash('success', 'Your ticket was withdrawn.');
    } else {
        AuditLog::record('ticket_cancel_denied', 'Ticket #' . $ticketId);
        flash('error', 'That ticket could not be withdrawn.');
    }
    redirect('my_tickets.php');
}

$filter = Ticket::isStatus($_GET['status'] ?? null) ? $_GET['status'] : '';

view('my_tickets', [
    'pageTitle' => 'My tickets',
    'filter'    => $filter,
    'tickets'   => TicketRepository::forUser((int) $user['id'], $filter),
    'allCounts' => TicketRepository::statusCounts((int) $user['id']),
]);
