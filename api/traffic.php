<?php
/**
 * NetSentry PHP API - Real-Time Traffic & Protocol Monitoring Endpoint
 */

require_once __DIR__ . '/../db.php';
require_login();

$pdo = get_db();

// 1. Gather system network traffic stats
$isWindows = (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN');

$packetsIn = 0;
$packetsOut = 0;
$tcpCount = 0;
$udpCount = 0;
$icmpCount = 0;
$httpCount = 0;
$dropped = 0;

if ($isWindows) {
    // Parse Windows netstat -e for interface stats
    @exec("netstat -e", $outputE);
    if (!empty($outputE)) {
        foreach ($outputE as $line) {
            if (preg_match('/Bytes\s+(\d+)\s+(\d+)/i', $line, $m)) {
                $packetsIn = (int)($m[1] / 1024); // KB approximate packet/byte metric
                $packetsOut = (int)($m[2] / 1024);
            }
            if (preg_match('/Unicast packets\s+(\d+)\s+(\d+)/i', $line, $m)) {
                $packetsIn += (int)$m[1];
                $packetsOut += (int)$m[2];
            }
            if (preg_match('/Discards\s+(\d+)\s+(\d+)/i', $line, $m)) {
                $dropped = (int)$m[1] + (int)$m[2];
            }
        }
    }

    // Parse active protocol counts via netstat -an
    @exec("netstat -an", $outputAN);
    if (!empty($outputAN)) {
        foreach ($outputAN as $line) {
            $lineUpper = strtoupper(trim($line));
            if (strpos($lineUpper, 'TCP') === 0) {
                $tcpCount++;
                if (strpos($lineUpper, ':80') !== false || strpos($lineUpper, ':443') !== false || strpos($lineUpper, ':8080') !== false) {
                    $httpCount++;
                }
            } elseif (strpos($lineUpper, 'UDP') === 0) {
                $udpCount++;
            }
        }
    }

    // Count ICMP from active ping/network log
    $stmtIcmp = $pdo->query("SELECT COUNT(*) FROM network_log WHERE timestamp >= NOW() - INTERVAL 1 HOUR");
    $icmpCount = (int)$stmtIcmp->fetchColumn();

} else {
    // Linux /proc/net/snmp or /proc/net/dev
    if (file_exists('/proc/net/snmp')) {
        $snmp = file_get_contents('/proc/net/snmp');
        if (preg_match('/Tcp:\s+\d+\s+\d+\s+\d+\s+\d+\s+\d+\s+\d+\s+(\d+)/i', $snmp, $m)) {
            $tcpCount = (int)$m[1];
        }
        if (preg_match('/Udp:\s+(\d+)/i', $snmp, $m)) {
            $udpCount = (int)$m[1];
        }
    }
}

$totalPackets = $tcpCount + $udpCount + $icmpCount + $httpCount;

// Calculate real packets per second based on delta with last recorded snapshot
$packetsPerSec = 0;
try {
    $stmtPrev = $pdo->query("SELECT * FROM traffic_snapshot ORDER BY id DESC LIMIT 1");
    $prev = $stmtPrev->fetch();
    if ($prev) {
        $tDiff = time() - strtotime($prev['timestamp']);
        if ($tDiff > 0 && $tDiff < 300) {
            $pDiff = ($packetsIn + $packetsOut) - ($prev['packets_in'] + $prev['packets_out']);
            if ($pDiff >= 0) {
                $packetsPerSec = (int)round($pDiff / $tDiff);
            }
        }
    }
} catch (Exception $e) {}

// Record snapshot
try {
    $stmt = $pdo->prepare("
        INSERT INTO traffic_snapshot (packets_in, packets_out, tcp_count, udp_count, icmp_count, http_count, dropped)
        VALUES (:pin, :pout, :tcp, :udp, :icmp, :http, :drop)
    ");
    $stmt->execute([
        ':pin'  => $packetsIn,
        ':pout' => $packetsOut,
        ':tcp'  => $tcpCount,
        ':udp'  => $udpCount,
        ':icmp' => $icmpCount,
        ':http' => $httpCount,
        ':drop' => $dropped
    ]);
    
    // Clean old snapshots (keep last 500)
    $pdo->exec("DELETE FROM traffic_snapshot WHERE id NOT IN (SELECT id FROM (SELECT id FROM traffic_snapshot ORDER BY id DESC LIMIT 500) AS keep)");
} catch (Exception $e) {
    error_log("Traffic snapshot log error: " . $e->getMessage());
}

json_response([
    'status'          => 'success',
    'packets_in'      => $packetsIn,
    'packets_out'     => $packetsOut,
    'tcp_count'       => $tcpCount,
    'udp_count'       => $udpCount,
    'icmp_count'      => $icmpCount,
    'http_count'      => $httpCount,
    'dropped'         => $dropped,
    'total_packets'   => $totalPackets,
    'packets_per_sec' => $packetsPerSec,
    'timestamp'       => date('Y-m-d H:i:s')
]);
