<?php
/**
 * NetSentry PHP API - Real Socket Port Scanner Endpoint
 * Scans a target IP for active listening TCP services using stream sockets.
 */

require_once __DIR__ . '/../db.php';
require_login();

$ip = trim($_GET['ip'] ?? $_POST['ip'] ?? '');
if (empty($ip)) {
    json_response(['status' => 'error', 'message' => 'Target IP parameter is required'], 400);
}

// Common ports to probe
$portsToScan = [
    21   => 'FTP (File Transfer Protocol)',
    22   => 'SSH (Secure Shell)',
    23   => 'Telnet (Unencrypted Shell)',
    25   => 'SMTP (Mail Server)',
    53   => 'DNS (Domain Name System)',
    80   => 'HTTP (Web Server)',
    110  => 'POP3 (Mail Server)',
    135  => 'RPC (Remote Procedure Call)',
    139  => 'NetBIOS (File Sharing)',
    443  => 'HTTPS (Secure Web Server)',
    445  => 'SMB (Windows Directory/Share)',
    1433 => 'MSSQL Database',
    3306 => 'MySQL Database',
    3389 => 'RDP (Remote Desktop)',
    5432 => 'PostgreSQL Database',
    8080 => 'HTTP Alt / Proxy',
    8443 => 'HTTPS Alt / Management'
];

$results = [];
$openCount = 0;

foreach ($portsToScan as $port => $service) {
    // Attempt socket connection with short timeout (400ms)
    $fp = @fsockopen($ip, $port, $errno, $errstr, 0.4);
    if ($fp) {
        $results[] = [
            'port'    => $port,
            'service' => $service,
            'status'  => 'Open',
            'state'   => 'Listening'
        ];
        $openCount++;
        fclose($fp);
    } else {
        $results[] = [
            'port'    => $port,
            'service' => $service,
            'status'  => 'Closed',
            'state'   => 'Filtered / Closed'
        ];
    }
}

json_response([
    'status'     => 'success',
    'target_ip'  => $ip,
    'scanned_at' => date('Y-m-d H:i:s'),
    'open_ports' => $openCount,
    'ports'      => $results
]);
