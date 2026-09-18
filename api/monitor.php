<?php
/**
 * NetSentry PHP API - Continuous Monitor & Interval Endpoint
 */

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_login();

$pdo = get_db();
$action = $_GET['action'] ?? $_POST['action'] ?? 'ping_all';

if ($action === 'set_interval') {
    $val = (int)($_GET['val'] ?? $_POST['val'] ?? 30);
    $stmt = $pdo->prepare("UPDATE settings SET setting_value = :val WHERE setting_key = 'ping_interval'");
    $stmt->execute([':val' => (string)$val]);
    json_response(['status' => 'success', 'interval' => $val]);
}

if ($action === 'ping_all') {
    $stmt = $pdo->query("SELECT id, ip_address, hostname, status FROM lan_device WHERE ip_address NOT LIKE '224.%' AND ip_address NOT LIKE '%.255'");
    $devices = $stmt->fetchAll();

    $results = [];

    foreach ($devices as $d) {
        $ip = $d['ip_address'];
        $ping = ping_single_device($ip);
        
        $isOnline = ($ping['status'] === 'Online');
        $lat = $ping['latency'];
        $loss = $ping['packet_loss'];

        // Update lan_device
        $stmtUp = $pdo->prepare("UPDATE lan_device SET status = :st, latency = :lat, packet_loss = :loss, last_seen = CASE WHEN :st = 'Online' THEN NOW() ELSE last_seen END WHERE id = :id");
        $stmtUp->execute([
            ':st'   => $ping['status'],
            ':lat'  => $lat,
            ':loss' => $loss,
            ':id'   => $d['id']
        ]);

        // Insert into ping_history
        try {
            $stmtH = $pdo->prepare("INSERT INTO ping_history (device_id, ip_address, latency, packet_loss, status) VALUES (:id, :ip, :lat, :loss, :st)");
            $stmtH->execute([
                ':id'   => $d['id'],
                ':ip'   => $ip,
                ':lat'  => $lat,
                ':loss' => $loss,
                ':st'   => $ping['status']
            ]);
        } catch (Exception $e) {}

        // State Change Detection
        if (!$isOnline && $d['status'] === 'Online') {
            log_network_event(
                $pdo,
                'DEVICE_OFFLINE',
                'Danger',
                $ip,
                $d['hostname'] ?: 'Unknown',
                "DEVICE OFFLINE: {$ip} (" . ($d['hostname'] ?: 'LAN Host') . ") failed to respond to ping.",
                $d['id']
            );
        } elseif ($isOnline && $d['status'] === 'Offline') {
            log_network_event(
                $pdo,
                'DEVICE_RECONNECTED',
                'Info',
                $ip,
                $d['hostname'] ?: 'Unknown',
                "Device reconnected: {$ip} (" . ($d['hostname'] ?: 'LAN Host') . ") is back ONLINE.",
                $d['id']
            );
        }

        $results[] = [
            'id'          => $d['id'],
            'ip'          => $ip,
            'status'      => $ping['status'],
            'latency'     => $lat,
            'packet_loss' => $loss
        ];
    }

    json_response([
        'status'    => 'success',
        'timestamp' => date('Y-m-d H:i:s'),
        'count'     => count($results),
        'results'   => $results
    ]);
}

json_response(['status' => 'error', 'message' => 'Invalid action'], 400);
