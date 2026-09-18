<?php
/**
 * NetSentry - Real World LAN Sync Script
 * Cleans out dummy test entries and performs a 100% real live scan of the active subnet.
 */

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

$pdo = get_db();

// 1. Detect Real Network Interface & Gateway
$netInfo = get_network_gateway_info();
echo "Active Interface: " . $netInfo['interface_name'] . "\n";
echo "Local IP: " . $netInfo['interface_ip'] . "\n";
echo "Default Gateway: " . $netInfo['gateway_ip'] . "\n";
echo "Subnet: " . $netInfo['subnet'] . "\n\n";

// 2. Clear old demo data from tables
$pdo->exec("TRUNCATE TABLE lan_device");
$pdo->exec("TRUNCATE TABLE network_events");
$pdo->exec("TRUNCATE TABLE ping_history");
$pdo->exec("TRUNCATE TABLE device_status_history");
$pdo->exec("TRUNCATE TABLE network_scans");

echo "Cleared old placeholder data.\n";

// 3. Run Real Subnet Discovery Scan
echo "Scanning real LAN subnet (" . $netInfo['subnet'] . ")...\n";
$scanResult = run_network_scan($pdo, $netInfo['subnet'], 'System Auto-Discovery');

echo "Discovered " . $scanResult['total_found'] . " real devices on your LAN:\n";
foreach ($scanResult['devices'] as $d) {
    echo " - [{$d['status']}] IP: {$d['ip_address']} | MAC: {$d['mac_address']} | Hostname: {$d['hostname']} | Type: {$d['device_type']} | Latency: {$d['latency']}ms\n";
}

echo "\nReal-world sync complete!\n";
