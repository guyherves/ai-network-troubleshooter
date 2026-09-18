<?php
/**
 * NetSentry - PHP Background Device Offline & Telemetry Monitor
 * CLI Script: run via terminal `php background_monitoring.php` or set up Windows Task Scheduler / Cron job.
 */

require_once __DIR__ . '/includes/db.php';

echo "[NetSentry Monitor] Starting LAN device and network telemetry background monitor...\n";

function ping_address($ip) {
    $isWindows = (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN');
    $cmd = $isWindows 
        ? "ping -n 1 -w 1000 " . escapeshellarg($ip)
        : "ping -c 1 -W 1 " . escapeshellarg($ip);

    exec($cmd, $output, $resultCode);
    $isOnline = ($resultCode === 0);
    $latency = null;

    if ($isOnline) {
        foreach ($output as $line) {
            if (preg_match('/time[=<]\s*([\d\.]+)ms/i', $line, $matches)) {
                $latency = round((float)$matches[1], 2);
                break;
            }
        }
    }
    return ['is_online' => $isOnline, 'latency' => $latency];
}

function run_monitoring_cycle() {
    $pdo = get_db();

    // 1. Fetch monitored LAN devices
    $stmt = $pdo->query("SELECT * FROM lan_device WHERE is_monitored = 1");
    $devices = $stmt->fetchAll();

    if (empty($devices)) {
        echo "[INFO] No monitored devices found in database. Pinging default gateway 127.0.0.1...\n";
        $devices = [['id' => 0, 'ip_address' => '127.0.0.1', 'hostname' => 'Localhost', 'status' => 'Online']];
    }

    foreach ($devices as $device) {
        $ip = $device['ip_address'];
        $res = ping_address($ip);
        $isOnline = $res['is_online'];
        $latency = $res['latency'];

        echo sprintf("[%s] Device: %s (%s) | Status: %s | Latency: %s ms\n", 
            date('Y-m-d H:i:s'), 
            $device['hostname'] ?? 'Unknown', 
            $ip, 
            $isOnline ? 'ONLINE' : 'OFFLINE',
            $isOnline ? ($latency ?? 0) : 'N/A'
        );

        // Insert Network Log
        $stmtLog = $pdo->prepare("
            INSERT INTO network_log (timestamp, target_ip, latency, packet_loss, status)
            VALUES (NOW(), :target_ip, :latency, :packet_loss, :status)
        ");
        $stmtLog->execute([
            ':target_ip'   => $ip,
            ':latency'     => $isOnline ? ($latency ?? 0) : 9999,
            ':packet_loss' => $isOnline ? 0 : 100,
            ':status'      => $isOnline ? 'Online' : 'Offline'
        ]);

        // State Transition Checks
        if ($device['id'] > 0) {
            if (!$isOnline && $device['status'] === 'Online') {
                // Device dropped offline
                $pdo->prepare("UPDATE lan_device SET status = 'Offline' WHERE id = :id")->execute([':id' => $device['id']]);
                
                $alertMsg = "ALERT: LAN Device {$device['hostname']} ({$ip}) went OFFLINE!";
                echo "[WARNING] {$alertMsg}\n";

                $pdo->prepare("
                    INSERT INTO alert (timestamp, severity, category, message, status)
                    VALUES (NOW(), 'High', 'Device Offline', :msg, 'Unresolved')
                ")->execute([':msg' => $alertMsg]);

            } elseif ($isOnline && $device['status'] === 'Offline') {
                // Device back online
                $pdo->prepare("UPDATE lan_device SET status = 'Online', last_seen = NOW() WHERE id = :id")->execute([':id' => $device['id']]);

                $alertMsg = "RECOVERY: LAN Device {$device['hostname']} ({$ip}) is back ONLINE.";
                echo "[INFO] {$alertMsg}\n";

                $pdo->prepare("
                    INSERT INTO alert (timestamp, severity, category, message, status)
                    VALUES (NOW(), 'Low', 'Device Online', :msg, 'Unresolved')
                ")->execute([':msg' => $alertMsg]);
            }
        }
    }
}

// Single-run if invoked via web or infinite loop if run via CLI
if (php_sapi_name() === 'cli') {
    echo "[NetSentry Monitor] Running in CLI mode (ctrl+c to stop)...\n";
    while (true) {
        run_monitoring_cycle();
        sleep(10); // Wait 10 seconds between cycles
    }
} else {
    run_monitoring_cycle();
    echo "<p>Monitoring cycle completed!</p>";
}
