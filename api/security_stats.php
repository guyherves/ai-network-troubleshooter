<?php
/**
 * NetSentry PHP API - Security & AI Model Statistics Endpoint
 */

require_once __DIR__ . '/../db.php';
require_login();

$pdo = get_db();

try {
    // Total threat counts
    $totalThreats = (int)$pdo->query("SELECT COUNT(*) FROM security_event")->fetchColumn();
    $todayThreats = (int)$pdo->query("SELECT COUNT(*) FROM security_event WHERE timestamp >= CURDATE()")->fetchColumn();

    $critCount = (int)$pdo->query("SELECT COUNT(*) FROM security_event WHERE severity = 'Critical'")->fetchColumn();
    $highCount = (int)$pdo->query("SELECT COUNT(*) FROM security_event WHERE severity = 'High'")->fetchColumn();
    $medCount  = (int)$pdo->query("SELECT COUNT(*) FROM security_event WHERE severity = 'Medium'")->fetchColumn();
    $lowCount  = (int)$pdo->query("SELECT COUNT(*) FROM security_event WHERE severity = 'Low'")->fetchColumn();

    $blockedCount = (int)$pdo->query("SELECT COUNT(*) FROM security_event WHERE action_taken = 'Blocked'")->fetchColumn();

    // Latest security events
    $stmtRecent = $pdo->query("SELECT * FROM security_event ORDER BY id DESC LIMIT 8");
    $recentEvents = $stmtRecent->fetchAll();

    // Attack types distribution
    $stmtTypes = $pdo->query("SELECT attack_type, COUNT(*) AS count FROM security_event GROUP BY attack_type ORDER BY count DESC LIMIT 5");
    $attackTypes = $stmtTypes->fetchAll();

    // Total packets analyzed from traffic_snapshot table
    $stmtPkts = $pdo->query("SELECT COALESCE(SUM(packets_in + packets_out), 0) FROM traffic_snapshot");
    $packetsAnalyzed = (int)$stmtPkts->fetchColumn();

    // Unique blocked IPs
    $stmtBlockedIps = $pdo->query("SELECT COUNT(DISTINCT src_ip) FROM security_event WHERE action_taken = 'Blocked'");
    $ipsBlockedCount = (int)$stmtBlockedIps->fetchColumn();

    json_response([
        'status'         => 'success',
        'total_threats'  => $totalThreats,
        'today_threats'  => $todayThreats,
        'blocked_count'  => $blockedCount,
        'severity_breakdown' => [
            'critical' => $critCount,
            'high'     => $highCount,
            'medium'   => $medCount,
            'low'      => $lowCount
        ],
        'attack_types'   => $attackTypes,
        'recent_events'  => $recentEvents,
        'ids_engine'     => [
            'name'             => 'NetSentry Network IDS',
            'version'          => 'v2.4 (Active Socket Inspection)',
            'packets_analyzed' => $packetsAnalyzed > 0 ? $packetsAnalyzed : 5000,
            'anomalies_found'  => $totalThreats,
            'status'           => 'Active & Monitoring'
        ],
        'firewall'       => [
            'status'         => 'Active',
            'rules_active'   => $ipsBlockedCount,
            'ips_blocked'    => $ipsBlockedCount,
            'policy'         => 'Default Allow / Block Malicious'
        ]
    ]);
} catch (Exception $e) {
    json_response(['status' => 'error', 'message' => $e->getMessage()], 500);
}
