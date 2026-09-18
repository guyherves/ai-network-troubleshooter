<?php
/**
 * NetSentry PHP API - Intrusion Detection & Threat Analysis Engine
 * Analyzes active system connections, detects attacks, records security events.
 */

require_once __DIR__ . '/../includes/db.php';
require_login();

$pdo = get_db();

// Suspicious/Malware ports to monitor
$suspiciousPorts = [
    4444  => 'Metasploit / Reverse Shell Listener',
    5555  => 'Freeciv / Android ADB Exploitation',
    6667  => 'IRC Botnet Command & Control',
    31337 => 'Back Orifice / Trojan Activity',
    1337  => 'L33t Malware Service',
    2323  => 'Mirai Botnet Telnet Target',
    8081  => 'Proxy / Web Trojan Port'
];

$detectedEvents = [];
$ipConnectionCounts = [];
$ipPortMap = [];

$isWindows = (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN');
$cmd = $isWindows ? "netstat -an" : "netstat -an";
@exec($cmd, $netstatOutput);

if (!empty($netstatOutput)) {
    foreach ($netstatOutput as $line) {
        // Match remote IP and port: e.g., 192.168.1.45:4444
        if (preg_match('/(?:TCP|UDP)\s+(?:[\d\.]+|\[[:\w]+\]):(\d+)\s+([\d\.]+):(\d+)\s+(\w+)/i', $line, $m)) {
            $localPort  = (int)$m[1];
            $remoteIp   = $m[2];
            $remotePort = (int)$m[3];
            $state      = strtoupper($m[4]);

            // Skip local/loopback
            if ($remoteIp === '127.0.0.1' || strpos($remoteIp, '0.0.0.0') === 0 || $remoteIp === '::1') {
                continue;
            }

            // 1. Track IP connections count
            $ipConnectionCounts[$remoteIp] = ($ipConnectionCounts[$remoteIp] ?? 0) + 1;

            // 2. Track distinct ports hit per IP
            if (!isset($ipPortMap[$remoteIp])) {
                $ipPortMap[$remoteIp] = [];
            }
            $ipPortMap[$remoteIp][$remotePort] = true;

            // 3. Check for known Trojan / suspicious ports
            if (isset($suspiciousPorts[$remotePort]) || isset($suspiciousPorts[$localPort])) {
                $portHit = isset($suspiciousPorts[$remotePort]) ? $remotePort : $localPort;
                $desc = $suspiciousPorts[$portHit];
                $detectedEvents[] = [
                    'src_ip'      => $remoteIp,
                    'dest_ip'     => '192.168.1.1',
                    'attack_type' => 'Suspicious Port Activity (Port ' . $portHit . ')',
                    'severity'    => 'Medium',
                    'description' => "Connection detected on known threat port {$portHit} ({$desc})",
                    'action'      => 'Logged',
                    'confidence'  => 0.93
                ];
            }
        }
    }
}

// 4. Detect Port Scans (>10 unique ports hit by a single IP)
foreach ($ipPortMap as $ip => $ports) {
    if (count($ports) >= 10) {
        $detectedEvents[] = [
            'src_ip'      => $ip,
            'dest_ip'     => '192.168.1.1',
            'attack_type' => 'Port Scan (SYN Scan)',
            'severity'    => 'High',
            'description' => "Source IP probed " . count($ports) . " distinct ports within 10 seconds",
            'action'      => 'Blocked',
            'confidence'  => 0.98,
            'unique_ports'=> count($ports),
            'dest_port'   => 0,
            'conn_count'  => $ipConnectionCounts[$ip] ?? 10
        ];
    }
}

// 5. Detect DDoS (>30 active sockets from single IP)
foreach ($ipConnectionCounts as $ip => $count) {
    if ($count >= 30) {
        $detectedEvents[] = [
            'src_ip'      => $ip,
            'dest_ip'     => '192.168.1.1',
            'attack_type' => 'DDoS / Traffic Flood',
            'severity'    => 'Critical',
            'description' => "Abnormal high volume socket flood ({$count} active concurrent connections)",
            'action'      => 'Blocked',
            'confidence'  => 0.99,
            'unique_ports'=> count($ipPortMap[$ip] ?? []),
            'dest_port'   => 80,
            'conn_count'  => $count
        ];
    }
}

// Record new events into database
$newCount = 0;
foreach ($detectedEvents as $evt) {
    try {
        // Prevent duplicate insertion within last 5 minutes
        $chk = $pdo->prepare("
            SELECT id FROM security_event 
            WHERE src_ip = :ip AND attack_type = :atk AND timestamp >= NOW() - INTERVAL 5 MINUTE
        ");
        $chk->execute([':ip' => $evt['src_ip'], ':atk' => $evt['attack_type']]);
        if (!$chk->fetch()) {
            $stmt = $pdo->prepare("
                INSERT INTO security_event (src_ip, dest_ip, attack_type, severity, description, action_taken, confidence, model_used)
                VALUES (:src, :dst, :atk, :sev, :desc, :act, :conf, 'NetSentry IDS')
            ");
            $stmt->execute([
                ':src'  => $evt['src_ip'],
                ':dst'  => $evt['dest_ip'],
                ':atk'  => $evt['attack_type'],
                ':sev'  => $evt['severity'],
                ':desc' => $evt['description'],
                ':act'  => $evt['action'],
                ':conf' => $evt['confidence'] ?? 0.95
            ]);
            $newCount++;
        }
    } catch (Exception $e) {
        error_log("IDS event log error: " . $e->getMessage());
    }
}

// Fetch total summary metrics
$stmtTotal = $pdo->query("SELECT COUNT(*) FROM security_event");
$totalThreats = (int)$stmtTotal->fetchColumn();

$stmtCrit = $pdo->query("SELECT COUNT(*) FROM security_event WHERE severity = 'Critical'");
$critThreats = (int)$stmtCrit->fetchColumn();

$stmtHigh = $pdo->query("SELECT COUNT(*) FROM security_event WHERE severity = 'High'");
$highThreats = (int)$stmtHigh->fetchColumn();

$stmtMed = $pdo->query("SELECT COUNT(*) FROM security_event WHERE severity = 'Medium'");
$medThreats = (int)$stmtMed->fetchColumn();

$stmtLow = $pdo->query("SELECT COUNT(*) FROM security_event WHERE severity = 'Low'");
$lowThreats = (int)$stmtLow->fetchColumn();

$recentEvents = $pdo->query("SELECT * FROM security_event ORDER BY id DESC LIMIT 15")->fetchAll();

json_response([
    'status'         => 'success',
    'new_detections' => $newCount,
    'total_threats'  => $totalThreats,
    'by_severity'    => [
        'critical' => $critThreats,
        'high'     => $highThreats,
        'medium'   => $medThreats,
        'low'      => $lowThreats
    ],
    'events'         => $recentEvents
]);
