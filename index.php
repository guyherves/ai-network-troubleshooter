<?php
/**
 * NetSentry - Main Network Operations Dashboard
 * Section 6: Top Stats Cards, Status Chart, Device Types, Latency Trend, Recent Events, Devices
 */

$pageTitle = "NOC Dashboard — NetSentry";
require_once __DIR__ . '/header.php';
require_once __DIR__ . '/functions.php';

$pdo = get_db();

// Fetch Dashboard Metrics & Telemetry
$metrics      = get_dashboard_metrics($pdo);
$recentEvents = get_recent_network_events($pdo, 6);
$devices      = get_subnet_devices($pdo, 25);
$netInfo      = get_network_gateway_info();

// Chart Data Ingestion
$deviceTypes  = get_device_type_distribution($pdo);
$statusDist   = get_device_status_distribution($pdo);
$telemetry    = get_telemetry_history($pdo, 12);

$typeLabels = [];
$typeCounts = [];
foreach ($deviceTypes as $dt) {
    $typeLabels[] = $dt['device_type'] ?: 'Unknown';
    $typeCounts[] = (int)$dt['cnt'];
}

$chartLabels = [];
$chartLatency = [];
foreach ($telemetry as $t) {
    $chartLabels[] = $t['time_label'];
    $chartLatency[] = round((float)$t['latency'], 1);
}
?>

<!-- ===== ROW 1: HEADER & LIVE CONTROLS ===== -->
<div class="d-flex flex-wrap align-items-center justify-content-between mb-4 gap-3">
    <div>
        <h3 class="mb-0 text-white fw-bold">
            <i class="bi bi-hdd-network text-primary me-2"></i>LAN Operations & Device Monitor
        </h3>
        <p class="mb-0 text-muted small">
            Subnet: <span class="badge bg-dark border border-secondary font-monospace text-info"><?php echo htmlspecialchars($netInfo['subnet']); ?></span>
            &bull; Gateway: <span class="badge bg-dark border border-secondary font-monospace text-success"><?php echo htmlspecialchars($netInfo['gateway_ip']); ?></span>
            &bull; Interface: <span class="text-white"><?php echo htmlspecialchars($netInfo['interface_name']); ?></span>
        </p>
    </div>
    <div class="d-flex gap-2 align-items-center">
        <span class="live-badge"><i class="bi bi-broadcast me-1"></i> NOC ACTIVE</span>
        <a href="scanner.php" class="btn btn-warning btn-sm fw-bold">
            <i class="bi bi-radar me-1"></i> Scan Network
        </a>
        <a href="monitoring.php" class="btn btn-outline-info btn-sm">
            <i class="bi bi-activity me-1"></i> Live Ping Monitor
        </a>
        <a href="reports.php" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-file-earmark-bar-graph me-1"></i> Reports
        </a>
    </div>
</div>

<!-- ===== ROW 2: TOP 6 STATISTICS CARDS (Section 6) ===== -->
<div class="row g-3 mb-4">
    <?php
    render_kpi_card('TOTAL DEVICES', $metrics['total'], '', 'kpi-total', 'kpi-primary', 'bi bi-hdd-network-fill', 'Discovered');
    render_kpi_card('ONLINE', $metrics['online'], '', 'kpi-online', 'kpi-success', 'bi bi-check-circle-fill', 'Reachable');
    render_kpi_card('OFFLINE', $metrics['offline'], '', 'kpi-offline', 'kpi-danger', 'bi bi-x-circle-fill', 'Unreachable');
    render_kpi_card('NEW DEVICES', $metrics['new_devices'], '', 'kpi-new', 'kpi-warning', 'bi bi-star-fill', 'Last 24 Hours');
    render_kpi_card('UNKNOWN DEVICES', $metrics['unknown_devices'], '', 'kpi-unknown', 'kpi-purple', 'bi bi-question-circle-fill', 'Unclassified');
    render_kpi_card('AVERAGE LATENCY', $metrics['avg_latency'], 'ms', 'kpi-latency', 'kpi-info', 'bi bi-speedometer2', 'Gateway Response');
    ?>
</div>

<!-- ===== ROW 3: CHARTS (Status Distribution, Device Types & Latency Trend) ===== -->
<div class="row g-3 mb-4">
    <!-- Latency History Trend -->
    <div class="col-lg-6">
        <div class="noc-card h-100">
            <div class="noc-card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-graph-up me-2 text-primary"></i>Network Latency Trend (ms)</span>
                <span class="live-badge">Real-Time</span>
            </div>
            <div class="noc-card-body" style="padding:15px; height: 260px;">
                <canvas id="latencyTrendChart"></canvas>
            </div>
        </div>
    </div>

    <!-- Device Type Distribution -->
    <div class="col-lg-3">
        <div class="noc-card h-100">
            <div class="noc-card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-pie-chart-fill me-2 text-info"></i>Device Types</span>
            </div>
            <div class="noc-card-body" style="height: 260px;">
                <canvas id="deviceTypeChart"></canvas>
            </div>
        </div>
    </div>

    <!-- Device Status Breakdown -->
    <div class="col-lg-3">
        <div class="noc-card h-100">
            <div class="noc-card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-diagram-3-fill me-2 text-success"></i>Device Status</span>
            </div>
            <div class="noc-card-body" style="height: 260px;">
                <canvas id="statusChart"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- ===== ROW 4: RECENT NETWORK EVENTS & RECENTLY DISCOVERED DEVICES ===== -->
<div class="row g-3 mb-4">
    <!-- Recent Network Events (Change Detection Log) -->
    <div class="col-lg-5">
        <div class="noc-card h-100">
            <div class="noc-card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-bell-fill me-2 text-warning"></i>Recent Network Events</span>
                <a href="events.php" class="small text-info text-decoration-none">View All Events</a>
            </div>
            <div class="noc-card-body p-2" style="max-height: 380px; overflow-y: auto;">
                <?php if (empty($recentEvents)): ?>
                    <div class="text-center text-muted py-5">
                        <i class="bi bi-shield-check text-success fs-2 d-block mb-2"></i>
                        <p class="small mb-0">No network change events recorded yet.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($recentEvents as $evt): ?>
                        <div class="p-2 mb-2 rounded border border-secondary" style="background: var(--bg-card-2);">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <?php
                                $sClass = ($evt['severity'] === 'Danger') ? 'bg-danger' : (($evt['severity'] === 'Warning') ? 'bg-warning text-dark' : 'bg-info text-dark');
                                ?>
                                <span class="badge <?php echo $sClass; ?>">
                                    <?php echo htmlspecialchars($evt['event_type']); ?>
                                </span>
                                <small class="text-muted" style="font-size:0.72rem;"><?php echo htmlspecialchars($evt['timestamp']); ?></small>
                            </div>
                            <div class="text-white small fw-bold mb-1"><?php echo htmlspecialchars($evt['message']); ?></div>
                            <div class="small text-muted font-monospace" style="font-size:0.75rem;">
                                Host: <code><?php echo htmlspecialchars($evt['ip_address']); ?></code> <?php echo (!empty($evt['hostname']) && $evt['hostname'] !== 'Unknown') ? ' (' . htmlspecialchars($evt['hostname']) . ')' : ''; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Connected LAN Devices Table -->
    <div class="col-lg-7">
        <div class="noc-card h-100">
            <div class="noc-card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-hdd-network me-2 text-primary"></i>Discovered LAN Devices</span>
                <a href="network_devices.php" class="small text-info text-decoration-none">Manage All Devices</a>
            </div>
            <div class="noc-card-body p-0">
                <?php if (empty($devices)): ?>
                    <div class="text-center text-muted py-5">
                        <i class="bi bi-hdd-network" style="font-size:3rem; opacity:0.3;"></i>
                        <p class="mt-3 mb-1">No devices discovered on LAN yet.</p>
                        <a href="scanner.php" class="btn btn-warning btn-sm mt-2">Run First Network Scan</a>
                    </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="noc-table align-middle">
                        <thead>
                            <tr>
                                <th>Status</th>
                                <th>Device</th>
                                <th>IP Address</th>
                                <th>MAC Address</th>
                                <th>Type</th>
                                <th>Latency</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($devices as $d): ?>
                                <tr>
                                    <td>
                                        <?php if ($d['status'] === 'Online'): ?>
                                            <span class="badge bg-success">🟢 Online</span>
                                        <?php elseif ($d['status'] === 'Offline'): ?>
                                            <span class="badge bg-danger">🔴 Offline</span>
                                        <?php else: ?>
                                            <span class="badge bg-warning text-dark">🟡 Unknown</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <a href="device_detail.php?id=<?php echo $d['id']; ?>" class="fw-bold text-white text-decoration-none">
                                            <?php echo htmlspecialchars($d['hostname'] ?: 'LAN Device'); ?>
                                        </a>
                                        <?php if (!empty($d['vendor']) && $d['vendor'] !== 'Unknown'): ?>
                                            <div class="small text-muted" style="font-size:0.72rem;"><?php echo htmlspecialchars($d['vendor']); ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="font-monospace text-info"><?php echo htmlspecialchars($d['ip_address']); ?></td>
                                    <td class="font-monospace text-muted small"><?php echo htmlspecialchars($d['mac_address'] ?: 'Unknown'); ?></td>
                                    <td><span class="badge bg-secondary"><?php echo htmlspecialchars($d['device_type'] ?: 'Unknown'); ?></span></td>
                                    <td>
                                        <?php if ($d['status'] === 'Online'): ?>
                                            <span class="text-success small fw-bold"><?php echo round((float)$d['latency'], 1); ?> ms</span>
                                        <?php else: ?>
                                            <span class="text-muted small">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <a href="device_detail.php?id=<?php echo $d['id']; ?>" class="btn btn-sm btn-outline-info py-0 px-2">
                                            <i class="bi bi-eye"></i> Details
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
// Chart 1: Latency Trend Line Chart
const chartLabels = <?php echo json_encode($chartLabels); ?>;
const chartLatency = <?php echo json_encode($chartLatency); ?>;

const ctxL = document.getElementById('latencyTrendChart').getContext('2d');
new Chart(ctxL, {
    type: 'line',
    data: {
        labels: chartLabels.length ? chartLabels : ['12:00', '12:05', '12:10', '12:15', '12:20'],
        datasets: [{
            label: 'Avg Latency (ms)',
            data: chartLatency.length ? chartLatency : [4.2, 5.1, 3.8, 4.5, 4.0],
            borderColor: '#3b82f6',
            backgroundColor: 'rgba(59, 130, 246, 0.15)',
            fill: true,
            tension: 0.35,
            borderWidth: 2
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
            x: { ticks: { color: '#94a3b8' }, grid: { color: '#1e293b' } },
            y: { ticks: { color: '#94a3b8' }, grid: { color: '#1e293b' }, beginAtZero: true }
        },
        plugins: { legend: { labels: { color: '#f8fafc' } } }
    }
});

// Chart 2: Device Types Doughnut Chart
const typeLabels = <?php echo json_encode($typeLabels); ?>;
const typeCounts = <?php echo json_encode($typeCounts); ?>;

new Chart(document.getElementById('deviceTypeChart'), {
    type: 'doughnut',
    data: {
        labels: typeLabels.length ? typeLabels : ['Unknown'],
        datasets: [{
            data: typeCounts.length ? typeCounts : [0],
            backgroundColor: ['#3b82f6', '#00d084', '#ffb020', '#a855f7', '#00c2e0', '#ef4444', '#94a3b8']
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { position: 'bottom', labels: { color: '#94a3b8', boxWidth: 12 } } }
    }
});

// Chart 3: Device Status Doughnut Chart
const statusDist = <?php echo json_encode($statusDist); ?>;

new Chart(document.getElementById('statusChart'), {
    type: 'doughnut',
    data: {
        labels: ['Online', 'Offline', 'Unknown'],
        datasets: [{
            data: [statusDist.Online || 0, statusDist.Offline || 0, statusDist.Unknown || 0],
            backgroundColor: ['#00d084', '#ef4444', '#ffb020']
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { position: 'bottom', labels: { color: '#94a3b8', boxWidth: 12 } } }
    }
});
</script>

<?php require_once __DIR__ . '/footer.php'; ?>
