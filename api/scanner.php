<?php
/**
 * NetSentry API - Network Subnet Scanner Endpoint
 */

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

require_login();
$pdo = get_db();

$action = $_GET['action'] ?? 'scan';
$subnet = trim($_GET['subnet'] ?? '');

if ($action === 'scan') {
    $results = run_network_scan($pdo, $subnet ?: null, $_SESSION['user_username'] ?? 'Admin');
    json_response($results);
} else {
    json_response(['status' => 'error', 'message' => 'Invalid action'], 400);
}
