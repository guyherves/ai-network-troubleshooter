<?php
/**
 * clear_and_scan.php
 * Clears all fake/seeded devices from the database, then runs a REAL
 * ARP table scan to discover actual devices on your live network.
 * 
 * Run once: http://localhost/fyp/clear_and_scan.php
 */
require_once __DIR__ . '/db.php';
require_login();
require_admin();

$pdo = get_db();

// ───────────────────────────────────────────────
// STEP 1: Wipe ALL seeded / fake devices
// ───────────────────────────────────────────────
$pdo->exec("DELETE FROM lan_device");
$pdo->exec("ALTER TABLE lan_device AUTO_INCREMENT = 1");

$results = [];
$addedCount = 0;

// ───────────────────────────────────────────────
// STEP 1: Detect active local subnet & gateway
// ───────────────────────────────────────────────
$gwInfo = get_network_gateway_info();
$subnet = $gwInfo['subnet']; // e.g. '192.168.1.'
$gatewayIp = $gwInfo['gateway_ip'];
$ownIp = $gwInfo['interface_ip'];

// Wipe invalid / stale virtual adapter devices outside physical subnet
$pdo->exec("DELETE FROM lan_device WHERE ip_address NOT LIKE '" . $subnet . "%'");

$results = [];
$addedCount = 0;

// ───────────────────────────────────────────────
// STEP 2: Real ARP table scan
// ───────────────────────────────────────────────
$isWindows = (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN');
$arpCmd = $isWindows ? 'arp -a' : 'arp -n';
exec($arpCmd, $arpLines, $arpReturn);

foreach ($arpLines as $line) {
    // Match: IP address + MAC address on same line
    if (preg_match('/(\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3})\s+([0-9a-fA-F:.\-]{11,17})/', $line, $m)) {
        $ip  = trim($m[1]);
        $rawMac = trim($m[2]);

        // Only keep devices matching the primary physical subnet
        if (strpos($ip, $subnet) !== 0) continue;

        // Normalize MAC to XX:XX:XX:XX:XX:XX
        $mac = strtoupper(str_replace(['-', '.'], ':', $rawMac));

        // Skip broadcast, multicast, loopback, and invalid MACs
        if (strpos($ip, '224.') === 0 || strpos($ip, '239.') === 0 || strpos($ip, '240.') === 0) continue;
        if (substr($ip, -4) === '.255' || substr($ip, -4) === '.254') continue; // Skip subnet broadcast & virtual DHCP .254
        if ($ip === '127.0.0.1') continue;
        if (strpos($mac, 'FF:FF') !== false || strpos($mac, '01:00:5E') === 0) continue;
        if ($mac === '00:00:00:00:00:00') continue;

        // Determine hostname
        $hostname = @gethostbyaddr($ip);
        if (!$hostname || $hostname === $ip) {
            $lastOctet = (int)substr($ip, strrpos($ip, '.') + 1);
            if ($ip === $gatewayIp) {
                $hostname = 'Gateway Router';
            } else {
                $hostname = 'LAN-Device-' . $lastOctet;
            }
        }

        $devType = ($ip === $gatewayIp) ? 'Router' : 'Workstation';

        // Real ICMP Ping test
        $pingCmd = $isWindows ? "ping -n 1 -w 1000 {$ip}" : "ping -c 1 -W 1 {$ip}";
        exec($pingCmd, $pingOut, $pingCode);
        $status = ($pingCode === 0) ? 'Online' : 'Offline';

        $latency = null;
        foreach ($pingOut as $pLine) {
            if (preg_match('/[Tt]ime[=<]\s*(\d+)\s*ms/i', $pLine, $lm)) {
                $latency = (int)$lm[1];
                break;
            }
        }

        // Insert / Update database
        $stmt = $pdo->prepare("
            INSERT INTO lan_device (ip_address, mac_address, hostname, device_type, status, is_monitored, last_seen)
            VALUES (:ip, :mac, :host, :type, :status, 1, NOW())
            ON DUPLICATE KEY UPDATE
                mac_address = VALUES(mac_address),
                hostname    = VALUES(hostname),
                status      = VALUES(status),
                last_seen   = NOW()
        ");
        $stmt->execute([
            ':ip'     => $ip,
            ':mac'    => $mac,
            ':host'   => $hostname,
            ':type'   => $devType,
            ':status' => $status,
        ]);
        $addedCount++;
        $results[] = [
            'ip' => $ip, 'mac' => $mac, 'hostname' => $hostname,
            'type' => $devType, 'status' => $status,
            'latency' => $latency ? "{$latency}ms" : 'N/A'
        ];
    }
}

// Ensure local host PC is added and marked Online
if ($ownIp && strpos($ownIp, $subnet) === 0) {
    $ownHost = gethostname() . ' (This PC)';
    $stmt = $pdo->prepare("
        INSERT INTO lan_device (ip_address, mac_address, hostname, device_type, status, is_monitored, last_seen)
        VALUES (:ip, 'N/A', :host, 'Server/PC', 'Online', 1, NOW())
        ON DUPLICATE KEY UPDATE status = 'Online', last_seen = NOW()
    ");
    $stmt->execute([':ip' => $ownIp, ':host' => $ownHost]);
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <title>NetSentry — Real Network Scan</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="app/static/css/style.css">
    <style>
        body { background: #0a0f1e; color: #f8fafc; font-family: 'Inter', sans-serif; padding: 2rem; }
        .result-card { background: #101828; border: 1px solid #1e293b; border-radius: 12px; padding: 1.5rem; margin-bottom: 1rem; }
        .badge-online  { background: #00d084; color: #000; padding: 3px 10px; border-radius: 20px; font-size: 0.75rem; font-weight: 700; }
        .badge-offline { background: #ef4444; color: #fff; padding: 3px 10px; border-radius: 20px; font-size: 0.75rem; font-weight: 700; }
    </style>
</head>
<body>
    <div style="max-width:960px; margin: 0 auto;">
        <h3 class="fw-bold mb-1"><i class="bi bi-radar me-2 text-primary"></i>Real Network Scan — Complete</h3>
        <p class="text-muted mb-4">
            Cleared all fake/seeded devices and performed a live ARP table scan on your network subnet <strong><?php echo htmlspecialchars($subnet . '0/24'); ?></strong>.
        </p>

        <div class="alert alert-success mb-4">
            <i class="bi bi-check-circle-fill me-2"></i>
            <strong><?php echo $addedCount; ?> real network device(s)</strong> discovered and saved to the database from your live network!
        </div>

        <?php if (empty($results)): ?>
            <div class="alert alert-warning">
                <i class="bi bi-exclamation-triangle me-2"></i>
                <strong>No devices found in ARP table.</strong><br>
                Your ARP table may be empty. Try browsing the web or pinging some devices first to populate it, then run this scan again:<br>
                <code>ping 192.168.1.1</code> (replace with your router's IP)
            </div>
        <?php else: ?>
            <div class="table-responsive result-card">
                <table class="table table-dark table-hover mb-0 align-middle">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>IP Address</th>
                            <th>MAC Address</th>
                            <th>Hostname</th>
                            <th>Type</th>
                            <th>Status</th>
                            <th>Latency</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($results as $i => $r): ?>
                            <tr>
                                <td class="text-muted"><?php echo $i + 1; ?></td>
                                <td class="fw-bold text-info"><code><?php echo htmlspecialchars($r['ip']); ?></code></td>
                                <td><code style="font-size:0.8rem;"><?php echo htmlspecialchars($r['mac']); ?></code></td>
                                <td class="fw-bold text-white"><?php echo htmlspecialchars($r['hostname']); ?></td>
                                <td><span class="badge bg-secondary"><?php echo htmlspecialchars($r['type']); ?></span></td>
                                <td>
                                    <?php if ($r['status'] === 'Online'): ?>
                                        <span class="badge-online">● Online</span>
                                    <?php else: ?>
                                        <span class="badge-offline">● Offline</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-muted"><?php echo htmlspecialchars($r['latency']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <div class="d-flex gap-3 mt-4">
            <a href="index.php" class="btn btn-primary"><i class="bi bi-speedometer2 me-1"></i> Back to Dashboard</a>
            <a href="network_devices.php" class="btn btn-outline-info"><i class="bi bi-hdd-network me-1"></i> View All Devices</a>
            <a href="clear_and_scan.php" class="btn btn-outline-warning"><i class="bi bi-arrow-repeat me-1"></i> Re-Scan Network</a>
        </div>
    </div>
</body>
</html>
