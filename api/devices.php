<?php
/**
 * NetSentry PHP API - Subnet Devices & Device Operations Endpoint
 */

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../functions.php';
require_login();

$pdo = get_db();
$action = $_GET['action'] ?? $_POST['action'] ?? 'list';

if ($action === 'list') {
    $devices = get_subnet_devices($pdo, 150);
    json_response(['status' => 'success', 'devices' => $devices]);
}

if ($action === 'ping') {
    $ip = trim($_GET['ip'] ?? $_POST['ip'] ?? '');
    $id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);

    if (empty($ip)) {
        json_response(['status' => 'error', 'message' => 'Missing IP address'], 400);
    }

    $pingRes = ping_single_device($ip);
    
    // Update database record
    try {
        $stmt = $pdo->prepare("UPDATE lan_device SET status = :st, latency = :lat, packet_loss = :loss, last_seen = NOW() WHERE ip_address = :ip");
        $stmt->execute([
            ':st'   => $pingRes['status'],
            ':lat'  => $pingRes['latency'],
            ':loss' => $pingRes['packet_loss'],
            ':ip'   => $ip
        ]);

        // Insert into ping_history
        $stmtHist = $pdo->prepare("INSERT INTO ping_history (device_id, ip_address, latency, packet_loss, status) VALUES (:id, :ip, :lat, :loss, :st)");
        $stmtHist->execute([
            ':id'   => $id > 0 ? $id : null,
            ':ip'   => $ip,
            ':lat'  => $pingRes['latency'],
            ':loss' => $pingRes['packet_loss'],
            ':st'   => $pingRes['status']
        ]);
    } catch (Exception $e) {}

    json_response([
        'status'      => $pingRes['status'],
        'ip'          => $ip,
        'latency'     => $pingRes['latency'],
        'packet_loss' => $pingRes['packet_loss']
    ]);
}

if ($action === 'scan') {
    $results = run_network_scan($pdo, null, $_SESSION['user_username'] ?? 'Admin');
    json_response($results);
}

if ($action === 'toggle_trusted' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $deviceId = (int)($_POST['id'] ?? 0);
    if ($deviceId > 0) {
        $stmt = $pdo->prepare("UPDATE lan_device SET is_trusted = NOT is_trusted WHERE id = :id");
        $stmt->execute([':id' => $deviceId]);
        json_response(['status' => 'success']);
    }
}

json_response(['status' => 'error', 'message' => 'Invalid action'], 400);
