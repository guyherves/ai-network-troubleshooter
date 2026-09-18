<?php
/**
 * NetSentry - CSV Export Generator
 * Section 15: Network Inventory Report, Network Availability Report, Network Changes Report
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';
require_login();

$pdo = get_db();
$type = $_GET['type'] ?? 'inventory';

header('Content-Type: text/csv; charset=utf-8');

if ($type === 'events') {
    header('Content-Disposition: attachment; filename=netsentry_events_report_' . date('Y-m-d_His') . '.csv');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['ID', 'Timestamp', 'Event Type', 'Severity', 'IP Address', 'Hostname', 'Message']);

    $stmt = $pdo->query("SELECT * FROM network_events ORDER BY id DESC LIMIT 1000");
    while ($row = $stmt->fetch()) {
        fputcsv($output, [
            $row['id'],
            $row['timestamp'],
            $row['event_type'],
            $row['severity'],
            $row['ip_address'],
            $row['hostname'] ?: 'Unknown',
            $row['message']
        ]);
    }
    fclose($output);
    exit();
}

if ($type === 'availability' || $type === 'telemetry') {
    header('Content-Disposition: attachment; filename=netsentry_availability_report_' . date('Y-m-d_His') . '.csv');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['ID', 'Timestamp', 'IP Address', 'Latency (ms)', 'Packet Loss (%)', 'Status']);

    $stmt = $pdo->query("SELECT * FROM ping_history ORDER BY id DESC LIMIT 1000");
    while ($row = $stmt->fetch()) {
        fputcsv($output, [
            $row['id'],
            $row['timestamp'],
            $row['ip_address'],
            $row['latency'],
            $row['packet_loss'],
            $row['status']
        ]);
    }
    fclose($output);
    exit();
}

// Default: Network Inventory Report (Section 15)
header('Content-Disposition: attachment; filename=netsentry_inventory_report_' . date('Y-m-d_His') . '.csv');
$output = fopen('php://output', 'w');
fputcsv($output, ['Device ID', 'Hostname', 'IP Address', 'MAC Address', 'Device Type', 'Vendor', 'Operating System', 'Status', 'Latency (ms)', 'Trusted', 'Administrator Notes', 'First Discovered', 'Last Seen']);

$stmt = $pdo->query("SELECT * FROM lan_device ORDER BY INET_ATON(ip_address) ASC");
while ($row = $stmt->fetch()) {
    fputcsv($output, [
        $row['id'],
        $row['hostname'] ?: 'Unknown',
        $row['ip_address'],
        $row['mac_address'] ?: 'Unknown',
        $row['device_type'] ?: 'Unknown',
        $row['vendor'] ?: 'Unknown',
        $row['operating_system'] ?: 'Unknown',
        $row['status'] ?: 'Unknown',
        $row['latency'] ?? 0,
        $row['is_trusted'] ? 'Yes' : 'No',
        $row['notes'] ?: '',
        $row['first_seen'] ?: '',
        $row['last_seen'] ?: ''
    ]);
}
fclose($output);
exit();
