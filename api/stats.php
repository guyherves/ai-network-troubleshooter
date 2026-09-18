<?php
/**
 * NetSentry PHP API - Live Telemetry & Statistics (REAL DATA ONLY)
 */

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/bandwidth_helper.php';
require_login();

$pdo = get_db();

// 1. Devices KPI — real counts only
$onlineCount = 0;
$offlineCount = 0;
try {
    $stmtOnline = $pdo->query("SELECT COUNT(*) FROM lan_device WHERE status = 'Online'");
    $onlineCount = (int)$stmtOnline->fetchColumn();

    $stmtOffline = $pdo->query("SELECT COUNT(*) FROM lan_device WHERE status = 'Offline'");
    $offlineCount = (int)$stmtOffline->fetchColumn();
} catch (Exception $e) {}

// 2. Active Alerts KPI — real count only
$alertsCount = 0;
try {
    $stmtAlerts = $pdo->query("SELECT COUNT(*) FROM alert WHERE status = 'Unresolved'");
    $alertsCount = (int)$stmtAlerts->fetchColumn();
} catch (Exception $e) {}

// 3. Latest real latency from network_log
$latencyVal = 0;
try {
    $stmtLatency = $pdo->query("SELECT latency FROM network_log WHERE latency < 9000 AND latency > 0 ORDER BY id DESC LIMIT 1");
    $fetched = $stmtLatency->fetchColumn();
    if ($fetched !== false && (float)$fetched > 0) {
        $latencyVal = round((float)$fetched, 2);
    }
} catch (Exception $e) {}

// 4. Real bandwidth measurement (Mbps)
$bandwidth = measure_real_bandwidth($pdo);

// 5. Calculate uptime percentage
$total = $onlineCount + $offlineCount;
$uptime = ($total > 0) ? round(($onlineCount / $total) * 100, 1) : 0;

json_response([
    'status'       => 'success',
    'online'       => $onlineCount,
    'offline'      => $offlineCount,
    'active_alerts'=> $alertsCount,
    'latency'      => $latencyVal,
    'bandwidth'    => $bandwidth,
    'uptime'       => $uptime,
    'timestamp'    => date('H:i:s')
]);
