<?php
/**
 * NetSentry - Printable PDF & Audit Report Generator
 * Section 15: Network Inventory Report, Network Availability Report, Network Changes Report
 */

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_login();

$pdo = get_db();
$metrics = get_dashboard_metrics($pdo);
$netInfo = get_network_gateway_info();

$devices = get_subnet_devices($pdo, 150);
$events = get_recent_network_events($pdo, 25);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>NetSentry — LAN Management & Monitoring Audit Report</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; margin: 40px; color: #1e293b; background: #fff; }
        .report-header { border-bottom: 3px solid #3b82f6; padding-bottom: 15px; margin-bottom: 25px; }
        .report-header h1 { color: #1e3a8a; margin: 0 0 5px 0; font-size: 24px; }
        .meta-table { width: 100%; margin-bottom: 20px; font-size: 13px; }
        .meta-table td { padding: 4px 8px; }
        .summary-boxes { display: flex; gap: 15px; margin-bottom: 30px; }
        .box { flex: 1; border: 1px solid #cbd5e1; border-radius: 6px; padding: 12px; text-align: center; background: #f8fafc; }
        .box-title { font-size: 11px; text-transform: uppercase; color: #64748b; font-weight: bold; }
        .box-val { font-size: 22px; font-weight: bold; color: #0f172a; margin-top: 4px; }
        table.data-table { width: 100%; border-collapse: collapse; margin-bottom: 30px; font-size: 12px; }
        table.data-table th, table.data-table td { border: 1px solid #cbd5e1; padding: 8px 10px; text-align: left; }
        table.data-table th { background: #f1f5f9; color: #334155; }
        .badge { display: inline-block; padding: 2px 6px; border-radius: 4px; font-size: 10px; font-weight: bold; }
        .badge-online { background: #dcfce7; color: #166534; }
        .badge-offline { background: #fee2e2; color: #991b1b; }
        .badge-new { background: #fef9c3; color: #854d0e; }
        .print-btn { float: right; padding: 10px 20px; background: #3b82f6; color: white; border: none; border-radius: 6px; font-weight: bold; cursor: pointer; }
        @media print { .print-btn { display: none; } body { margin: 15px; } }
    </style>
</head>
<body>
    <button class="print-btn" onclick="window.print()">🖨️ Print / Save as PDF</button>

    <div class="report-header">
        <h1>🌐 NetSentry LAN Management & Monitoring System</h1>
        <div style="font-size: 14px; color: #64748b;">Comprehensive Subnet Inventory, Availability & Change Detection Report</div>
    </div>

    <table class="meta-table">
        <tr>
            <td width="20%"><strong>Report Date:</strong></td>
            <td width="30%"><?php echo date('F j, Y, g:i A'); ?></td>
            <td width="20%"><strong>Monitored Subnet:</strong></td>
            <td width="30%"><code><?php echo htmlspecialchars($netInfo['subnet']); ?></code></td>
        </tr>
        <tr>
            <td><strong>Default Gateway:</strong></td>
            <td><code><?php echo htmlspecialchars($netInfo['gateway_ip']); ?></code></td>
            <td><strong>Network Interface:</strong></td>
            <td><?php echo htmlspecialchars($netInfo['interface_name']); ?></td>
        </tr>
    </table>

    <div class="summary-boxes">
        <div class="box">
            <div class="box-title">Total Discovered</div>
            <div class="box-val"><?php echo $metrics['total']; ?></div>
        </div>
        <div class="box">
            <div class="box-title">Online Devices</div>
            <div class="box-val" style="color:#16a34a;"><?php echo $metrics['online']; ?></div>
        </div>
        <div class="box">
            <div class="box-title">Offline Devices</div>
            <div class="box-val" style="color:#dc2626;"><?php echo $metrics['offline']; ?></div>
        </div>
        <div class="box">
            <div class="box-title">Uptime Ratio</div>
            <div class="box-val" style="color:#2563eb;"><?php echo $metrics['uptime']; ?>%</div>
        </div>
        <div class="box">
            <div class="box-title">Average Latency</div>
            <div class="box-val"><?php echo $metrics['avg_latency']; ?> ms</div>
        </div>
    </div>

    <h3 style="color:#1e3a8a; border-bottom:1px solid #cbd5e1; padding-bottom:5px;">1. Connected Devices Inventory</h3>
    <table class="data-table">
        <thead>
            <tr>
                <th>Status</th>
                <th>Device Hostname</th>
                <th>IP Address</th>
                <th>MAC Address</th>
                <th>Type</th>
                <th>Vendor / Manufacturer</th>
                <th>Latency</th>
                <th>Last Seen Active</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($devices as $d): ?>
                <tr>
                    <td>
                        <?php if ($d['status'] === 'Online'): ?>
                            <span class="badge badge-online">ONLINE</span>
                        <?php else: ?>
                            <span class="badge badge-offline">OFFLINE</span>
                        <?php endif; ?>
                    </td>
                    <td><strong><?php echo htmlspecialchars($d['hostname'] ?: 'LAN Device'); ?></strong></td>
                    <td><code><?php echo htmlspecialchars($d['ip_address']); ?></code></td>
                    <td><small><?php echo htmlspecialchars($d['mac_address'] ?: 'Unknown'); ?></small></td>
                    <td><?php echo htmlspecialchars($d['device_type'] ?: 'Unknown'); ?></td>
                    <td><?php echo htmlspecialchars($d['vendor'] ?: 'Unknown'); ?></td>
                    <td><?php echo round((float)$d['latency'], 1); ?> ms</td>
                    <td><small><?php echo htmlspecialchars($d['last_seen'] ?: 'N/A'); ?></small></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <h3 style="color:#1e3a8a; border-bottom:1px solid #cbd5e1; padding-bottom:5px;">2. Network Changes & Event History</h3>
    <table class="data-table">
        <thead>
            <tr>
                <th>Timestamp</th>
                <th>Event Type</th>
                <th>Target IP</th>
                <th>Hostname</th>
                <th>Details / Event Description</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($events)): ?>
                <tr><td colspan="5" style="text-align:center; color:#94a3b8;">No events recorded.</td></tr>
            <?php else: ?>
                <?php foreach ($events as $e): ?>
                    <tr>
                        <td><small><?php echo htmlspecialchars($e['timestamp']); ?></small></td>
                        <td>
                            <?php if ($e['event_type'] === 'NEW_DEVICE'): ?>
                                <span class="badge badge-new">NEW DEVICE</span>
                            <?php elseif ($e['event_type'] === 'DEVICE_OFFLINE'): ?>
                                <span class="badge badge-offline">DEVICE OFFLINE</span>
                            <?php else: ?>
                                <span class="badge badge-online"><?php echo htmlspecialchars($e['event_type']); ?></span>
                            <?php endif; ?>
                        </td>
                        <td><code><?php echo htmlspecialchars($e['ip_address']); ?></code></td>
                        <td><?php echo htmlspecialchars($e['hostname'] ?: 'LAN Host'); ?></td>
                        <td><?php echo htmlspecialchars($e['message']); ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <div style="text-align:center; font-size:11px; color:#94a3b8; margin-top:40px; border-top:1px solid #e2e8f0; padding-top:15px;">
        Generated by NetSentry LAN Management & Monitoring System &bull; Confidential Administrative Network Audit
    </div>
</body>
</html>
