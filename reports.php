<?php
/**
 * NetSentry - Network Reports & Audit Logs Page
 * Section 15: Network Inventory Report, Network Availability Report, Network Changes Report (CSV & PDF)
 */

$pageTitle = "Reports — NetSentry";
require_once __DIR__ . '/header.php';
require_once __DIR__ . '/functions.php';

$pdo = get_db();
$metrics = get_dashboard_metrics($pdo);
$netInfo = get_network_gateway_info();

// Fetch summary tables for reports
$devices = get_subnet_devices($pdo, 100);
$recentEvents = get_recent_network_events($pdo, 20);
$scanLogs = $pdo->query("SELECT * FROM network_scans ORDER BY id DESC LIMIT 10")->fetchAll();
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
    <div>
        <h3 class="mb-0 text-white fw-bold">
            <i class="bi bi-file-earmark-bar-graph text-info me-2"></i>Network Reports & Inventory Audits
        </h3>
        <p class="text-muted small mb-0">Generate network inventory reports, availability metrics, and change history</p>
    </div>
    <div class="d-flex gap-2">
        <a href="export_csv.php" class="btn btn-primary btn-sm">
            <i class="bi bi-file-earmark-spreadsheet me-1"></i> Download CSV Report
        </a>
        <a href="export_pdf.php" target="_blank" class="btn btn-danger btn-sm">
            <i class="bi bi-file-earmark-pdf me-1"></i> Print / Export PDF
        </a>
    </div>
</div>

<!-- Report Summary KPI Cards -->
<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="noc-card p-3 text-center border-primary">
            <div class="text-muted small">TOTAL INVENTORY</div>
            <div class="fs-3 fw-bold text-white"><?php echo $metrics['total']; ?></div>
            <div class="text-info small">Monitored Hosts</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="noc-card p-3 text-center border-success">
            <div class="text-muted small">CURRENT AVAILABILITY</div>
            <div class="fs-3 fw-bold text-success"><?php echo $metrics['uptime']; ?>%</div>
            <div class="text-success small"><?php echo $metrics['online']; ?> of <?php echo $metrics['total']; ?> Online</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="noc-card p-3 text-center border-warning">
            <div class="text-muted small">NETWORK CHANGES (24H)</div>
            <div class="fs-3 fw-bold text-warning"><?php echo $metrics['new_devices']; ?></div>
            <div class="text-muted small">New Devices Discovered</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="noc-card p-3 text-center border-info">
            <div class="text-muted small">AVERAGE LAN LATENCY</div>
            <div class="fs-3 fw-bold text-info"><?php echo $metrics['avg_latency']; ?> ms</div>
            <div class="text-muted small">Gateway & Host Ping</div>
        </div>
    </div>
</div>

<!-- Report 1: Network Inventory Report -->
<div class="noc-card mb-4">
    <div class="noc-card-header d-flex justify-content-between align-items-center">
        <span><i class="bi bi-boxes me-2 text-primary"></i>1. Network Inventory Report</span>
        <a href="export_csv.php?type=inventory" class="btn btn-sm btn-outline-info py-0 px-2">Export CSV</a>
    </div>
    <div class="noc-card-body p-0">
        <div class="table-responsive">
            <table class="noc-table align-middle mb-0">
                <thead>
                    <tr>
                        <th>Device Name</th>
                        <th>IP Address</th>
                        <th>MAC Address</th>
                        <th>Device Type</th>
                        <th>Vendor / Manufacturer</th>
                        <th>Status</th>
                        <th>First Seen</th>
                        <th>Last Seen</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($devices as $d): ?>
                        <tr>
                            <td class="fw-bold text-white"><?php echo htmlspecialchars($d['hostname'] ?: 'LAN Device'); ?></td>
                            <td class="font-monospace text-info"><?php echo htmlspecialchars($d['ip_address']); ?></td>
                            <td class="font-monospace text-muted small"><?php echo htmlspecialchars($d['mac_address'] ?: 'Unknown'); ?></td>
                            <td><span class="badge bg-secondary"><?php echo htmlspecialchars($d['device_type'] ?: 'Unknown'); ?></span></td>
                            <td class="small text-muted"><?php echo htmlspecialchars($d['vendor'] ?: 'Unknown'); ?></td>
                            <td>
                                <?php echo ($d['status'] === 'Online') ? '<span class="badge bg-success">🟢 Online</span>' : '<span class="badge bg-danger">🔴 Offline</span>'; ?>
                            </td>
                            <td class="small text-muted"><?php echo htmlspecialchars($d['first_seen'] ?: 'N/A'); ?></td>
                            <td class="small text-muted"><?php echo htmlspecialchars($d['last_seen'] ?: 'N/A'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Report 2: Network Availability & Uptime Report -->
<div class="noc-card mb-4">
    <div class="noc-card-header d-flex justify-content-between align-items-center">
        <span><i class="bi bi-activity me-2 text-success"></i>2. Network Availability & Latency Audit</span>
        <a href="export_csv.php?type=availability" class="btn btn-sm btn-outline-success py-0 px-2">Export CSV</a>
    </div>
    <div class="noc-card-body p-3">
        <div class="row g-3 align-items-center">
            <div class="col-md-4">
                <div class="p-3 bg-dark border border-secondary rounded">
                    <div class="text-muted small">ONLINE HOSTS</div>
                    <div class="fs-4 fw-bold text-success"><?php echo $metrics['online']; ?></div>
                    <div class="text-muted small">Responding to ARP / ICMP probes</div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="p-3 bg-dark border border-secondary rounded">
                    <div class="text-muted small">OFFLINE HOSTS</div>
                    <div class="fs-4 fw-bold text-danger"><?php echo $metrics['offline']; ?></div>
                    <div class="text-muted small">Retained in network history</div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="p-3 bg-dark border border-secondary rounded">
                    <div class="text-muted small">AVG ROUNDTRIP LATENCY</div>
                    <div class="fs-4 fw-bold text-info"><?php echo $metrics['avg_latency']; ?> ms</div>
                    <div class="text-muted small">Subnet Gateway response baseline</div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Report 3: Network Changes & Detection Report -->
<div class="noc-card mb-4">
    <div class="noc-card-header d-flex justify-content-between align-items-center">
        <span><i class="bi bi-bell-fill me-2 text-warning"></i>3. Network Changes & Event Log</span>
        <a href="events.php" class="small text-info text-decoration-none">Full Event Stream</a>
    </div>
    <div class="noc-card-body p-0">
        <div class="table-responsive">
            <table class="noc-table align-middle mb-0">
                <thead>
                    <tr>
                        <th>Timestamp</th>
                        <th>Event Type</th>
                        <th>IP Address</th>
                        <th>Device Hostname</th>
                        <th>Change Details</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($recentEvents)): ?>
                        <tr><td colspan="5" class="text-center text-muted py-3">No change events logged.</td></tr>
                    <?php else: ?>
                        <?php foreach (array_slice($recentEvents, 0, 10) as $evt): ?>
                            <tr>
                                <td class="small text-muted font-monospace"><?php echo htmlspecialchars($evt['timestamp']); ?></td>
                                <td>
                                    <?php
                                    $t = $evt['event_type'];
                                    if ($t === 'NEW_DEVICE') echo '<span class="badge bg-warning text-dark">⚠ NEW DEVICE</span>';
                                    elseif ($t === 'DEVICE_OFFLINE') echo '<span class="badge bg-danger">🔴 OFFLINE</span>';
                                    elseif ($t === 'DEVICE_RECONNECTED') echo '<span class="badge bg-success">🟢 RECONNECTED</span>';
                                    else echo '<span class="badge bg-info text-dark">' . htmlspecialchars($t) . '</span>';
                                    ?>
                                </td>
                                <td class="font-monospace text-info"><?php echo htmlspecialchars($evt['ip_address']); ?></td>
                                <td class="text-white small"><?php echo htmlspecialchars($evt['hostname'] ?: 'LAN Host'); ?></td>
                                <td class="small"><?php echo htmlspecialchars($evt['message']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
