<?php
require dirname(__DIR__) . '/src/bootstrap.php';

use App\Auth\Auth;
use App\Security\AuditLog;

Auth::requirePermission('audit.view');
Auth::requireRecentAuth('audit_log.php');

$integrityChecked = false;
$integrity = null;
if (is_post() && post_string('action') === 'verify') {
    verify_csrf();
    $integrity = AuditLog::verifyChain();
    $integrityChecked = true;
    AuditLog::record('audit_integrity_check', $integrity === null ? 'Chain intact' : 'Chain broken at entry #' . $integrity);
}

$eventFilter = preg_match('/^[a-z0-9_]{1,50}$/', query_string('event', 50)) ? query_string('event', 50) : '';
$q = query_string('q');
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 50;

[$rows, $total] = AuditLog::search($eventFilter, $q, $page, $perPage);
$pages = max(1, (int) ceil($total / $perPage));
if ($page > $pages) {
    redirect('audit_log.php?' . http_build_query(array_filter(['event' => $eventFilter, 'q' => $q]) + ['page' => $pages]));
}

view('audit_log', [
    'pageTitle'        => 'Audit log',
    'integrityChecked' => $integrityChecked,
    'integrity'        => $integrity,
    'eventFilter'      => $eventFilter,
    'q'                => $q,
    'page'             => min($page, $pages),
    'pages'            => $pages,
    'rows'             => $rows,
    'total'            => $total,
    'events'           => AuditLog::eventNames(),
    'summary'          => AuditLog::summary(),
    'warningEvents'    => AuditLog::WARNING_EVENTS,
    'baseQuery'        => array_filter(['event' => $eventFilter, 'q' => $q]),
]);
