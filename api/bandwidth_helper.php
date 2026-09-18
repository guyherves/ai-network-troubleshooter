<?php
/**
 * NetSentry - Real Bandwidth Measurement Helper
 * Uses Windows netstat or wmic to measure actual network throughput.
 */

/**
 * Get current network interface bytes sent/received.
 * Uses Windows `netstat -e` to read real byte counters.
 * Returns ['bytes_sent' => int, 'bytes_recv' => int] or null on failure.
 */
function get_interface_bytes() {
    $isWindows = (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN');

    if ($isWindows) {
        // Windows: use `netstat -e` which shows total bytes sent/received
        exec('netstat -e', $output, $code);
        if ($code === 0) {
            foreach ($output as $line) {
                // Match the "Bytes" row: "Bytes           1234567890   9876543210"
                if (preg_match('/Bytes\s+(\d+)\s+(\d+)/i', $line, $m)) {
                    return [
                        'bytes_recv' => (int)$m[1],
                        'bytes_sent' => (int)$m[2]
                    ];
                }
            }
        }
    } else {
        // Linux: read from /sys/class/net/
        $ifaces = ['eth0', 'ens33', 'enp0s3', 'wlan0', 'wlp2s0'];
        foreach ($ifaces as $iface) {
            $rxFile = "/sys/class/net/{$iface}/statistics/rx_bytes";
            $txFile = "/sys/class/net/{$iface}/statistics/tx_bytes";
            if (file_exists($rxFile) && file_exists($txFile)) {
                return [
                    'bytes_recv' => (int)trim(file_get_contents($rxFile)),
                    'bytes_sent' => (int)trim(file_get_contents($txFile))
                ];
            }
        }
    }
    return null;
}

/**
 * Calculate real bandwidth in Mbps by comparing current bytes with last snapshot.
 * Stores snapshots in the `bandwidth_snapshot` table.
 * Returns bandwidth in Mbps (float) or 0 if no previous snapshot.
 */
function measure_real_bandwidth($pdo) {
    $current = get_interface_bytes();
    if ($current === null) {
        return 0;
    }

    $bandwidth = 0;

    try {
        // Get last snapshot
        $stmt = $pdo->query("SELECT * FROM bandwidth_snapshot ORDER BY id DESC LIMIT 1");
        $last = $stmt->fetch();

        if ($last) {
            $timeDiff = time() - strtotime($last['timestamp']);
            if ($timeDiff > 0 && $timeDiff < 300) { // Only valid within 5 minutes
                $bytesDelta = ($current['bytes_sent'] - $last['bytes_sent']) + ($current['bytes_recv'] - $last['bytes_recv']);
                if ($bytesDelta > 0) {
                    // Convert bytes/sec to Mbps: (bytes * 8) / (seconds * 1,000,000)
                    $bandwidth = round(($bytesDelta * 8) / ($timeDiff * 1000000), 1);
                }
            }
        }

        // Save current snapshot
        $ins = $pdo->prepare("INSERT INTO bandwidth_snapshot (timestamp, bytes_sent, bytes_recv) VALUES (NOW(), :sent, :recv)");
        $ins->execute([':sent' => $current['bytes_sent'], ':recv' => $current['bytes_recv']]);

        // Keep only last 100 snapshots
        $pdo->exec("DELETE FROM bandwidth_snapshot WHERE id NOT IN (SELECT id FROM (SELECT id FROM bandwidth_snapshot ORDER BY id DESC LIMIT 100) AS keep)");

    } catch (Exception $e) {
        error_log("Bandwidth measurement error: " . $e->getMessage());
    }

    return $bandwidth;
}
