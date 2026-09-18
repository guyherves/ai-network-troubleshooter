<?php
/**
 * NetSentry API - Network Events Endpoint
 */

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_login();

$pdo = get_db();
$limit = (int)($_GET['limit'] ?? 20);

$events = get_recent_network_events($pdo, $limit);
json_response([
    'status' => 'success',
    'count'  => count($events),
    'events' => $events
]);
