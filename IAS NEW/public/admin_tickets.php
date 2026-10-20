<?php
require dirname(__DIR__) . '/src/bootstrap.php';

use App\Auth\Auth;
use App\Domain\Ticket;
use App\Repositories\TicketRepository;
use App\Security\AuditLog;

$user = Auth::requirePermission('ticket.manage');

if (is_post()) {
    verify_csrf();
    $ticket = TicketRepository::find(post_int('ticket_id'));
    $action = post_string('action');

    if (!$ticket) {
        flash('error', 'Ticket not found.');
    } elseif ($action === 'update') {
        $status = post_string('status');
        $priority = post_string('priority');
        if (!Ticket::isStatus($status) || !Ticket::isPriority($priority)) {
            flash('error', 'Invalid status or priority.');
        } else {
            TicketRepository::update((int) $ticket['id'], $status, $priority, (int) $user['id']);
            AuditLog::record('ticket_updated', sprintf('Ticket #%d: %s/%s to %s/%s', $ticket['id'], $ticket['status'], $ticket['priority'], $status, $priority));
            flash('success', 'Ticket #' . $ticket['id'] . ' updated.');
        }
    } elseif ($action === 'claim') {
        TicketRepository::claim((int) $ticket['id'], (int) $user['id']);
        AuditLog::record('ticket_claimed', 'Ticket #' . $ticket['id']);
        flash('success', 'Ticket #' . $ticket['id'] . ' is now assigned to you.');
    }

    // Keep the current filters after the redirect.
    $query = http_build_query(array_intersect_key($_GET, array_flip(['status', 'priority', 'q', 'mine', 'page'])));
    redirect('admin_tickets.php' . ($query ? '?' . $query : ''));
}

// Filters: every value is whitelisted here and bound as a parameter in the repository.
$status = Ticket::isStatus($_GET['status'] ?? null) ? $_GET['status'] : '';
$priority = Ticket::isPriority($_GET['priority'] ?? null) ? $_GET['priority'] : '';
$q = query_string('q');
$mine = ($_GET['mine'] ?? '') === '1';
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 20;

[$tickets, $total] = TicketRepository::search(
    ['status' => $status, 'priority' => $priority, 'q' => $q, 'assignee' => $mine ? (int) $user['id'] : null],
    $page,
    $perPage
);
$pages = max(1, (int) ceil($total / $perPage));
$baseQuery = array_filter(['status' => $status, 'priority' => $priority, 'q' => $q, 'mine' => $mine ? '1' : '']);

// e.g. resolving the only ticket on the last page: go to the new last page instead of an empty list
if ($page > $pages) {
    redirect('admin_tickets.php?' . http_build_query($baseQuery + ['page' => $pages]));
}

view('admin_tickets', [
    'pageTitle'  => 'Ticket queue',
    'user'       => $user,
    'tickets'    => $tickets,
    'total'      => $total,
    'page'       => min($page, $pages),
    'pages'      => $pages,
    'status'     => $status,
    'priority'   => $priority,
    'q'          => $q,
    'mine'       => $mine,
    'baseQuery'  => $baseQuery,
    'formAction' => 'admin_tickets.php?' . http_build_query($baseQuery + ['page' => $page]),
    'queue'      => TicketRepository::queueSummary(),
]);
