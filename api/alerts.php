<?php
/**
 * NetSentry PHP API - Alert Management Endpoint
 */

require_once __DIR__ . '/../db.php';
require_login();

$pdo = get_db();
$action = $_GET['action'] ?? $_POST['action'] ?? 'recent';

if ($action === 'recent') {
    $alerts = [];
    try {
        $stmt = $pdo->query("SELECT * FROM alert ORDER BY id DESC LIMIT 50");
        $alerts = $stmt->fetchAll();
    } catch (Exception $e) {}

    // No fake fallbacks — show real data only
    json_response(['status' => 'success', 'alerts' => $alerts]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'acknowledge') {
        $alertId = (int)($_POST['id'] ?? 0);
        if ($alertId > 0) {
            $stmt = $pdo->prepare("UPDATE alert SET status = 'Resolved' WHERE id = :id");
            $stmt->execute([':id' => $alertId]);
            json_response(['status' => 'success']);
        }
        json_response(['status' => 'error', 'message' => 'Invalid ID'], 400);
    }

    if ($action === 'mark_all_read') {
        $pdo->query("UPDATE alert SET status = 'Resolved' WHERE status = 'Unresolved'");
        json_response(['status' => 'success']);
    }

    if ($action === 'clear') {
        require_admin();
        $pdo->query("DELETE FROM alert");
        $pdo->query("DELETE FROM ids_event");
        json_response(['status' => 'success']);
    }
}

json_response(['status' => 'error', 'message' => 'Invalid action'], 400);
