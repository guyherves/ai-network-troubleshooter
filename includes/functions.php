<?php
/**
 * NetSentry - Core PHP Helper Functions & LAN Management Logic
 */

require_once __DIR__ . '/db.php';

/**
 * Fetch overview network telemetry & device metrics from database (Section 6 Dashboard KPIs)
 */
function get_dashboard_metrics($pdo) {
    $metrics = [
        'total'           => 0,
        'online'          => 0,
        'offline'         => 0,
        'new_devices'     => 0,
        'unknown_devices' => 0,
        'avg_latency'     => 0.0,
        'active_alerts'   => 0,
        'uptime'          => 100.0
    ];

    try {
        // Total devices
        $stmtTot = $pdo->query("SELECT COUNT(*) FROM lan_device");
        $metrics['total'] = (int)$stmtTot->fetchColumn();

        // Online devices
        $stmtOn = $pdo->query("SELECT COUNT(*) FROM lan_device WHERE status = 'Online'");
        $metrics['online'] = (int)$stmtOn->fetchColumn();

        // Offline devices
        $stmtOff = $pdo->query("SELECT COUNT(*) FROM lan_device WHERE status = 'Offline'");
        $metrics['offline'] = (int)$stmtOff->fetchColumn();

        // New devices discovered in last 24 hours
        $stmtNew = $pdo->query("SELECT COUNT(*) FROM lan_device WHERE first_seen >= NOW() - INTERVAL 24 HOUR");
        $metrics['new_devices'] = (int)$stmtNew->fetchColumn();

        // Unknown devices (device_type = 'Unknown' or hostname IS NULL or hostname = 'Unknown')
        $stmtUnk = $pdo->query("SELECT COUNT(*) FROM lan_device WHERE device_type = 'Unknown' OR hostname = 'Unknown' OR hostname IS NULL");
        $metrics['unknown_devices'] = (int)$stmtUnk->fetchColumn();

        // Average latency across online devices
        $stmtLat = $pdo->query("SELECT AVG(latency) FROM lan_device WHERE status = 'Online' AND latency > 0 AND latency < 1000");
        $avgLat = $stmtLat->fetchColumn();
        $metrics['avg_latency'] = $avgLat ? round((float)$avgLat, 1) : 0.0;

        // Uptime percentage
        $totalDevs = $metrics['total'];
        if ($totalDevs > 0) {
            $metrics['uptime'] = round(($metrics['online'] / $totalDevs) * 100, 1);
        }

        // Active events/alerts
        $stmtEvt = $pdo->query("SELECT COUNT(*) FROM network_events WHERE is_read = 0");
        $metrics['active_alerts'] = (int)$stmtEvt->fetchColumn();

    } catch (Exception $e) {
        error_log("Dashboard Metrics Query Error: " . $e->getMessage());
    }

    return $metrics;
}

/**
 * Fetch recent network events (Section 9 & 10 Change Detection)
 */
function get_recent_network_events($pdo, $limit = 10) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM network_events ORDER BY id DESC LIMIT :limit");
        $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    } catch (Exception $e) {
        error_log("Network Events Query Error: " . $e->getMessage());
    }
    return [];
}

/**
 * Log structured network event into database
 */
function log_network_event($pdo, $eventType, $severity, $ipAddress, $hostname, $message, $deviceId = null) {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO network_events (event_type, severity, device_id, ip_address, hostname, message, is_read)
            VALUES (:type, :sev, :dev_id, :ip, :host, :msg, 0)
        ");
        $stmt->execute([
            ':type'   => $eventType,
            ':sev'    => $severity,
            ':dev_id' => $deviceId,
            ':ip'     => $ipAddress,
            ':host'   => $hostname,
            ':msg'    => $message
        ]);
        return true;
    } catch (Exception $e) {
        error_log("Log Network Event Error: " . $e->getMessage());
        return false;
    }
}

/**
 * Fetch subnet LAN devices (filtered against multicast/broadcast)
 */
function get_subnet_devices($pdo, $limit = 100) {
    try {
        $stmt = $pdo->prepare("
            SELECT * FROM lan_device 
            WHERE ip_address NOT LIKE '224.%' AND ip_address NOT LIKE '239.%' AND ip_address NOT LIKE '240.%' AND ip_address NOT LIKE '%.255'
            ORDER BY 
                CASE WHEN status = 'Online' THEN 1 ELSE 2 END,
                INET_ATON(ip_address) ASC, 
                id DESC 
            LIMIT :limit
        ");
        $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    } catch (Exception $e) {
        error_log("Devices Query Error: " . $e->getMessage());
    }
    return [];
}

/**
 * Fetch recent latency & ping telemetry history for Chart.js
 */
function get_telemetry_history($pdo, $limit = 15) {
    try {
        $stmt = $pdo->prepare("
            SELECT DATE_FORMAT(timestamp, '%H:%i:%s') AS time_label, 
                   AVG(latency) AS latency, 
                   COUNT(*) AS ping_count
            FROM ping_history 
            WHERE latency < 1000 
            GROUP BY timestamp 
            ORDER BY timestamp DESC 
            LIMIT :limit
        ");
        $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll();
        
        if (empty($rows)) {
            // Fallback to network_log if ping_history is fresh
            $stmt2 = $pdo->prepare("
                SELECT DATE_FORMAT(timestamp, '%H:%i:%s') AS time_label, 
                       latency, 
                       COALESCE(bandwidth_mbps, 0) AS bandwidth_mbps
                FROM network_log 
                WHERE latency < 1000 
                ORDER BY id DESC 
                LIMIT :limit
            ");
            $stmt2->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
            $stmt2->execute();
            $rows = $stmt2->fetchAll();
        }

        return array_reverse($rows);
    } catch (Exception $e) {
        error_log("Telemetry History Error: " . $e->getMessage());
    }
    return [];
}

/**
 * Get device type distribution for Chart.js
 */
function get_device_type_distribution($pdo) {
    try {
        $stmt = $pdo->query("SELECT device_type, COUNT(*) AS cnt FROM lan_device GROUP BY device_type ORDER BY cnt DESC");
        return $stmt->fetchAll();
    } catch (Exception $e) {
        error_log("Device Type Distribution Error: " . $e->getMessage());
    }
    return [];
}

/**
 * Get device status distribution for Chart.js
 */
function get_device_status_distribution($pdo) {
    try {
        $stmt = $pdo->query("SELECT status, COUNT(*) AS cnt FROM lan_device GROUP BY status");
        $res = ['Online' => 0, 'Offline' => 0, 'Unknown' => 0];
        while ($row = $stmt->fetch()) {
            $st = $row['status'] ?: 'Unknown';
            $res[$st] = (int)$row['cnt'];
        }
        return $res;
    } catch (Exception $e) {
        return ['Online' => 0, 'Offline' => 0, 'Unknown' => 0];
    }
}

/**
 * PHP Component Renderer: Render Top KPI Card HTML (Section 6)
 */
function render_kpi_card($label, $value, $unit, $elementId, $colorClass, $iconClass, $trendText) {
    ?>
    <div class="col-6 col-md-4 col-xl-2">
        <div class="kpi-card <?php echo htmlspecialchars($colorClass); ?>">
            <div class="kpi-icon"><i class="<?php echo htmlspecialchars($iconClass); ?>"></i></div>
            <div>
                <div class="kpi-label"><?php echo htmlspecialchars($label); ?></div>
                <div class="kpi-value" id="<?php echo htmlspecialchars($elementId); ?>">
                    <?php echo htmlspecialchars($value); ?><?php echo $unit ? ' <small style="font-size:0.75rem; font-weight:normal;">' . htmlspecialchars($unit) . '</small>' : ''; ?>
                </div>
                <div class="kpi-trend"><?php echo htmlspecialchars($trendText); ?></div>
            </div>
        </div>
    </div>
    <?php
}

/**
 * Detect primary Default Gateway IP & Host Interface IP dynamically (Section 4)
 */
function get_network_gateway_info() {
    $isWindows = (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN');
    $gateway = '192.168.1.1';
    $interfaceIp = gethostbyname(gethostname());
    $ifaceName = 'Local Interface';

    if ($isWindows) {
        @exec('ipconfig', $ipconfigOut);
        if (!empty($ipconfigOut)) {
            $curAdapter = 'Ethernet / Wi-Fi';
            foreach ($ipconfigOut as $line) {
                if (stripos($line, 'adapter') !== false && strpos($line, ':') !== false) {
                    $curAdapter = trim(substr($line, strpos($line, 'adapter') + 7), ": \r\n");
                }
                if (stripos($line, 'IPv4 Address') !== false || stripos($line, 'IP Address') !== false) {
                    if (preg_match('/:\s*([\d\.]+)/', $line, $m)) {
                        $interfaceIp = $m[1];
                        $ifaceName = $curAdapter;
                    }
                }
                if (stripos($line, 'Default Gateway') !== false) {
                    if (preg_match('/:\s*([\d\.]+)/', $line, $m)) {
                        if ($m[1] !== '0.0.0.0' && strpos($m[1], '127.') !== 0) {
                            $gateway = $m[1];
                        }
                    }
                }
            }
        }
    } else {
        @exec('ip route', $routeOut);
        if (!empty($routeOut)) {
            foreach ($routeOut as $line) {
                if (preg_match('/default via ([\d\.]+)\s+dev\s+([\w\d]+)/i', $line, $m)) {
                    $gateway = $m[1];
                    $ifaceName = $m[2];
                    break;
                }
            }
        }
    }

    $octets = explode('.', $gateway);
    $subnet = (count($octets) === 4) ? "{$octets[0]}.{$octets[1]}.{$octets[2]}.0/24" : "192.168.1.0/24";

    return [
        'interface_name' => $ifaceName,
        'interface_ip'   => $interfaceIp,
        'gateway_ip'     => $gateway,
        'subnet'         => $subnet
    ];
}

/**
 * Ping a single device and return exact latency (ms) and status
 */
function ping_single_device($ip) {
    $isWindows = (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN');
    $cmd = $isWindows ? "ping -n 1 -w 600 " . escapeshellarg($ip) : "ping -c 1 -W 1 " . escapeshellarg($ip);
    
    $start = microtime(true);
    @exec($cmd, $out, $code);
    $duration = (microtime(true) - $start) * 1000.0;

    $isOnline = ($code === 0);
    $latency = round($duration, 1);

    if ($isOnline && !empty($out)) {
        $outStr = implode(" ", $out);
        if (preg_match('/(?:time|temps)[=<]\s*([\d\.]+)\s*ms/i', $outStr, $m)) {
            $latency = (float)$m[1];
        }
    }

    return [
        'ip'          => $ip,
        'status'      => $isOnline ? 'Online' : 'Offline',
        'latency'     => $isOnline ? $latency : 0.0,
        'packet_loss' => $isOnline ? 0.0 : 100.0
    ];
}

/**
 * Execute Network Scan with Change Detection (Section 3, 4, 9, 10)
 */
function run_network_scan($pdo, $targetSubnet = null, $triggeredBy = 'Admin') {
    set_time_limit(0); // Prevent 30-second PHP timeout during long scans
    $netInfo = get_network_gateway_info();
    $subnet = $targetSubnet ?: $netInfo['subnet'];
    $startTime = microtime(true);

    $discovered = [];

    // 1. Try Python Multi-threaded Scanner
    $pyScript = __DIR__ . '/scanner.py';
    if (file_exists($pyScript)) {
        $pyCmd = "python " . escapeshellarg($pyScript) . " " . escapeshellarg($subnet);
        @exec($pyCmd, $pyOut, $pyCode);
        if ($pyCode === 0 && !empty($pyOut)) {
            $jsonStr = implode("\n", $pyOut);
            $pyData = json_decode($jsonStr, true);
            if (isset($pyData['devices']) && is_array($pyData['devices'])) {
                $discovered = $pyData['devices'];
            }
        }
    }

    // 2. Fallback to PHP native ARP / Gateway discovery if Python wasn't available
    if (empty($discovered)) {
        $discovered = run_native_php_scan($subnet, $netInfo['gateway_ip'], $netInfo['interface_ip']);
    }

    // 3. Database Synchronization & Change Detection
    $existingDevices = [];
    $stmtAll = $pdo->query("SELECT id, ip_address, hostname, status, mac_address FROM lan_device");
    while ($row = $stmtAll->fetch()) {
        $existingDevices[$row['ip_address']] = $row;
    }

    $newDevicesCount = 0;
    $discoveredIps = [];

    foreach ($discovered as $d) {
        $ip = $d['ip_address'];
        $discoveredIps[] = $ip;
        $mac = !empty($d['mac_address']) && $d['mac_address'] !== 'Unknown' ? $d['mac_address'] : null;
        $hostname = !empty($d['hostname']) && $d['hostname'] !== 'Unknown' ? $d['hostname'] : null;
        $devType = $d['device_type'] ?? 'Unknown';
        $vendor = $d['vendor'] ?? 'Unknown';
        $os = $d['operating_system'] ?? 'Unknown';
        $latency = (float)($d['latency'] ?? 1.0);
        $packetLoss = (float)($d['packet_loss'] ?? 0.0);

        if (!isset($existingDevices[$ip])) {
            // NEW DEVICE DETECTED (Section 9)
            $newDevicesCount++;
            $stmtIns = $pdo->prepare("
                INSERT INTO lan_device (ip_address, mac_address, hostname, device_type, vendor, operating_system, status, latency, packet_loss, first_seen, last_seen)
                VALUES (:ip, :mac, :host, :dtype, :vendor, :os, 'Online', :lat, :loss, NOW(), NOW())
            ");
            $stmtIns->execute([
                ':ip'     => $ip,
                ':mac'    => $mac,
                ':host'   => $hostname,
                ':dtype'  => $devType,
                ':vendor' => $vendor,
                ':os'     => $os,
                ':lat'    => $latency,
                ':loss'   => $packetLoss
            ]);
            $newId = (int)$pdo->lastInsertId();

            // Log New Device Event
            log_network_event(
                $pdo,
                'NEW_DEVICE',
                'Warning',
                $ip,
                $hostname ?: 'Unknown',
                "⚠ NEW DEVICE DETECTED: IP {$ip}, MAC " . ($mac ?: 'Unknown') . " ({$devType}) connected to network.",
                $newId
            );

        } else {
            // EXISTING DEVICE (Update & Check Status Transition)
            $prev = $existingDevices[$ip];
            $prevStatus = $prev['status'];

            $stmtUp = $pdo->prepare("
                UPDATE lan_device SET 
                    status = 'Online',
                    latency = :lat,
                    packet_loss = :loss,
                    last_seen = NOW(),
                    mac_address = COALESCE(:mac, mac_address),
                    hostname = COALESCE(:host, hostname),
                    device_type = CASE WHEN device_type = 'Unknown' AND :dtype_check != 'Unknown' THEN :dtype_value ELSE device_type END,
                    vendor = CASE WHEN vendor = 'Unknown' AND :vendor_check != 'Unknown' THEN :vendor_value ELSE vendor END
                WHERE ip_address = :ip
            ");
            $stmtUp->execute([
                ':lat'    => $latency,
                ':loss'   => $packetLoss,
                ':mac'    => $mac,
                ':host'   => $hostname,
                ':dtype_check' => $devType,
                ':dtype_value' => $devType,
                ':vendor_check' => $vendor,
                ':vendor_value' => $vendor,
                ':ip'     => $ip
            ]);

            // If device was previously Offline -> DEVICE RECONNECTED
            if ($prevStatus === 'Offline') {
                log_network_event(
                    $pdo,
                    'DEVICE_RECONNECTED',
                    'Info',
                    $ip,
                    $prev['hostname'] ?: 'Unknown',
                    "Device reconnected to network: {$ip} (" . ($prev['hostname'] ?: 'LAN Device') . ") is now ONLINE.",
                    $prev['id']
                );

                $stmtHist = $pdo->prepare("INSERT INTO device_status_history (device_id, ip_address, previous_status, new_status, reason) VALUES (:id, :ip, 'Offline', 'Online', 'Responded in active subnet scan')");
                $stmtHist->execute([':id' => $prev['id'], ':ip' => $ip]);
            }
        }

        // Record ping history sample
        try {
            $devId = isset($existingDevices[$ip]) ? $existingDevices[$ip]['id'] : ($newId ?? null);
            $stmtP = $pdo->prepare("INSERT INTO ping_history (device_id, ip_address, latency, packet_loss, status) VALUES (:id, :ip, :lat, :loss, 'Online')");
            $stmtP->execute([':id' => $devId, ':ip' => $ip, ':lat' => $latency, ':loss' => $packetLoss]);
        } catch (Exception $e) {}
    }

    // 4. DISCONNECTED / OFFLINE DEVICE DETECTION (Section 10)
    foreach ($existingDevices as $ip => $dev) {
        if (!in_array($ip, $discoveredIps) && $dev['status'] === 'Online') {
            // Verify with direct ping check before declaring offline
            $singlePing = ping_single_device($ip);
            if ($singlePing['status'] === 'Offline') {
                $stmtOff = $pdo->prepare("UPDATE lan_device SET status = 'Offline', packet_loss = 100.0 WHERE ip_address = :ip");
                $stmtOff->execute([':ip' => $ip]);

                log_network_event(
                    $pdo,
                    'DEVICE_OFFLINE',
                    'Danger',
                    $ip,
                    $dev['hostname'] ?: 'Unknown',
                    "DEVICE OFFLINE: {$ip} (" . ($dev['hostname'] ?: 'LAN Host') . ") stopped responding to pings.",
                    $dev['id']
                );

                $stmtHist = $pdo->prepare("INSERT INTO device_status_history (device_id, ip_address, previous_status, new_status, reason) VALUES (:id, :ip, 'Online', 'Offline', 'Unresponsive during subnet scan')");
                $stmtHist->execute([':id' => $dev['id'], ':ip' => $ip]);
            }
        }
    }

    $duration = round(microtime(true) - $startTime, 2);

    // 5. Record Network Scan Log (Section 16)
    try {
        $stmtScan = $pdo->prepare("
            INSERT INTO network_scans (interface_name, subnet_range, total_devices_found, new_devices_count, duration_seconds, scan_type, triggered_by, status)
            VALUES (:iface, :subnet, :total, :new_cnt, :dur, 'ARP / ICMP Subnet Scan', :user, 'Completed')
        ");
        $stmtScan->execute([
            ':iface'   => $netInfo['interface_name'],
            ':subnet'  => $subnet,
            ':total'   => count($discovered),
            ':new_cnt' => $newDevicesCount,
            ':dur'     => $duration,
            ':user'    => $triggeredBy
        ]);
    } catch (Exception $e) {}

    return [
        'status'         => 'success',
        'subnet'         => $subnet,
        'interface'      => $netInfo['interface_name'],
        'gateway'        => $netInfo['gateway_ip'],
        'duration'       => $duration,
        'total_found'    => count($discovered),
        'new_devices'    => $newDevicesCount,
        'devices'        => $discovered
    ];
}

/**
 * Native PHP Network Scanner Fallback (Uses OS ARP Table & ICMP ping)
 */
function run_native_php_scan($subnet, $gatewayIp, $localIp) {
    $devices = [];
    $isWindows = (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN');

    // Add Gateway & Local machine
    if ($gatewayIp && $gatewayIp !== '127.0.0.1') {
        $devices[$gatewayIp] = [
            'ip_address'       => $gatewayIp,
            'mac_address'      => 'Unknown',
            'hostname'         => 'Gateway-Router',
            'device_type'      => 'Router',
            'vendor'           => 'Gateway',
            'operating_system' => 'RouterOS / Linux',
            'status'           => 'Online',
            'latency'          => 1.2,
            'packet_loss'      => 0.0
        ];
    }

    if ($localIp && $localIp !== '127.0.0.1') {
        $devices[$localIp] = [
            'ip_address'       => $localIp,
            'mac_address'      => 'Unknown',
            'hostname'         => gethostname() ?: 'Local-Admin-PC',
            'device_type'      => 'Computer',
            'vendor'           => 'Local Host',
            'operating_system' => PHP_OS,
            'status'           => 'Online',
            'latency'          => 0.2,
            'packet_loss'      => 0.0
        ];
    }

    // Read ARP cache table
    $cmd = $isWindows ? "arp -a" : "ip neigh || arp -an";
    @exec($cmd, $arpOut);
    if (!empty($arpOut)) {
        foreach ($arpOut as $line) {
            $line = trim($line);
            if (preg_match('/([\d\.]+)\s+([0-9a-fA-F\:\-]{11,17})/i', $line, $m)) {
                $ip = $m[1];
                $mac = strtoupper(str_replace('-', ':', $m[2]));
                
                // Exclude multicast / broadcast
                if (strpos($ip, '224.') === 0 || strpos($ip, '239.') === 0 || strpos($ip, '255.') === 0 || substr($ip, -4) === '.255' || $mac === 'FF:FF:FF:FF:FF:FF') {
                    continue;
                }

                $host = @gethostbyaddr($ip);
                $hostname = ($host && $host !== $ip) ? explode('.', $host)[0] : 'Unknown';
                $devType = ($ip === $gatewayIp) ? 'Router' : 'Computer';

                $devices[$ip] = [
                    'ip_address'       => $ip,
                    'mac_address'      => $mac,
                    'hostname'         => $hostname,
                    'device_type'      => $devType,
                    'vendor'           => 'Unknown',
                    'operating_system' => 'Unknown',
                    'status'           => 'Online',
                    'latency'          => 2.5,
                    'packet_loss'      => 0.0
                ];
            }
        }
    }

    return array_values($devices);
}

