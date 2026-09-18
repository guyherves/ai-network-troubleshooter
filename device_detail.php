<?php
/**
 * NetSentry - Device Detail & Diagnostic Drilldown Page
 * Section 8: Metadata, Latency/Availability Charts, Live Ping, Port Scan, Notes, Trusted Toggle
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

require_login();
$pdo = get_db();

$deviceId = (int)($_GET['id'] ?? 0);
$deviceIp = trim($_GET['ip'] ?? '');

$device = null;
if ($deviceId > 0) {
    $stmt = $pdo->prepare("SELECT * FROM lan_device WHERE id = :id");
    $stmt->execute([':id' => $deviceId]);
    $device = $stmt->fetch();
} elseif (!empty($deviceIp)) {
    $stmt = $pdo->prepare("SELECT * FROM lan_device WHERE ip_address = :ip");
    $stmt->execute([':ip' => $deviceIp]);
    $device = $stmt->fetch();
}

if (!$device) {
    header("Location: network_devices.php");
    exit();
}

// Handle Note / Trusted Update
$successMsg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_device_info'])) {
    $notes    = trim($_POST['notes'] ?? '');
    $isTrust  = isset($_POST['is_trusted']) ? 1 : 0;
    $devType  = trim($_POST['device_type'] ?? 'Unknown');
    $hostname = trim($_POST['hostname'] ?? '');

    $stmtUp = $pdo->prepare("
        UPDATE lan_device SET 
            notes = :notes,
            is_trusted = :trust,
            device_type = :dtype,
            hostname = :host
        WHERE id = :id
    ");
    $stmtUp->execute([
        ':notes' => $notes,
        ':trust' => $isTrust,
        ':dtype' => $devType,
        ':host'  => $hostname,
        ':id'    => $device['id']
    ]);

    // Refresh device data
    $stmt = $pdo->prepare("SELECT * FROM lan_device WHERE id = :id");
    $stmt->execute([':id' => $device['id']]);
    $device = $stmt->fetch();

    $successMsg = 'Device notes & classification updated successfully!';
}

// Handle Delete Device
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_device'])) {
    $stmtDel = $pdo->prepare("DELETE FROM lan_device WHERE id = :id");
    $stmtDel->execute([':id' => $device['id']]);
    header("Location: network_devices.php?deleted=1");
    exit();
}

// Fetch device ping history for Chart.js
$stmtLogs = $pdo->prepare("
    SELECT DATE_FORMAT(timestamp, '%H:%i:%s') AS time_label, latency, packet_loss, status 
    FROM ping_history 
    WHERE device_id = :id OR ip_address = :ip 
    ORDER BY id DESC LIMIT 20
");
$stmtLogs->execute([':id' => $device['id'], ':ip' => $device['ip_address']]);
$pingHistory = array_reverse($stmtLogs->fetchAll());

if (empty($pingHistory)) {
    // Fallback to sample history for chart visual baseline
    $pingHistory = [
        ['time_label' => '12:00', 'latency' => round((float)$device['latency'], 1), 'status' => $device['status']],
        ['time_label' => '12:05', 'latency' => round((float)$device['latency'] * 1.1, 1), 'status' => $device['status']],
        ['time_label' => '12:10', 'latency' => round((float)$device['latency'] * 0.9, 1), 'status' => $device['status']]
    ];
}

$chartLabels = array_column($pingHistory, 'time_label');
$chartLatency = array_map(function($r) { return round((float)$r['latency'], 1); }, $pingHistory);

// Fetch device events history
$stmtEvents = $pdo->prepare("
    SELECT * FROM network_events 
    WHERE device_id = :id OR ip_address = :ip 
    ORDER BY id DESC LIMIT 8
");
$stmtEvents->execute([':id' => $device['id'], ':ip' => $device['ip_address']]);
$deviceEvents = $stmtEvents->fetchAll();

$pageTitle = "Device: " . ($device['hostname'] ?: $device['ip_address']);
include_once __DIR__ . '/header.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1">
                <li class="breadcrumb-item"><a href="index.php" class="text-decoration-none">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="network_devices.php" class="text-decoration-none">Devices</a></li>
                <li class="breadcrumb-item active text-white" aria-current="page"><?php echo htmlspecialchars($device['hostname'] ?: $device['ip_address']); ?></li>
            </ol>
        </nav>
        <h2 class="fw-bold mb-0 text-white">
            <i class="bi bi-hdd-network text-primary me-2"></i>
            <?php echo htmlspecialchars($device['hostname'] ?: 'LAN Device'); ?>
            <span id="topStatusBadge" class="badge <?php echo $device['status'] === 'Online' ? 'bg-success' : 'bg-danger'; ?> fs-6 ms-2">
                <?php echo $device['status'] === 'Online' ? '🟢 Online' : '🔴 Offline'; ?>
            </span>
            <?php if (!empty($device['is_trusted'])): ?>
                <span class="badge bg-info text-dark fs-6 ms-1"><i class="bi bi-shield-check"></i> Trusted</span>
            <?php endif; ?>
        </h2>
    </div>
    <div class="d-flex gap-2">
        <a href="network_devices.php" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i> Back to Devices
        </a>
        <button id="livePingBtn" class="btn btn-warning btn-sm fw-bold" onclick="runLivePing('<?php echo htmlspecialchars($device['ip_address']); ?>', <?php echo $device['id']; ?>)">
            <i class="bi bi-broadcast me-1"></i> Ping Device Now
        </button>
        <button class="btn btn-outline-info btn-sm" onclick="runPortScan('<?php echo htmlspecialchars($device['ip_address']); ?>')">
            <i class="bi bi-search me-1"></i> Scan Common Ports
        </button>
    </div>
</div>

<?php if (!empty($successMsg)): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="bi bi-check-circle-fill me-2"></i><?php echo htmlspecialchars($successMsg); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<!-- Overview Metadata KPI Cards (Section 8) -->
<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="noc-card p-3 h-100">
            <div class="text-muted small">IP ADDRESS</div>
            <div class="fs-4 fw-bold text-info font-monospace mt-1"><?php echo htmlspecialchars($device['ip_address']); ?></div>
            <div class="text-muted small mt-1"><i class="bi bi-ethernet me-1"></i> Subnet Member</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="noc-card p-3 h-100">
            <div class="text-muted small">MAC ADDRESS</div>
            <div class="fs-5 fw-bold text-white font-monospace mt-1"><?php echo htmlspecialchars($device['mac_address'] ?: 'Unknown'); ?></div>
            <div class="text-muted small mt-1"><i class="bi bi-cpu me-1"></i> <?php echo htmlspecialchars($device['vendor'] ?: 'Hardware OUI'); ?></div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="noc-card p-3 h-100">
            <div class="text-muted small">DEVICE TYPE</div>
            <div class="fs-4 fw-bold text-warning mt-1"><?php echo htmlspecialchars($device['device_type'] ?: 'Unknown'); ?></div>
            <div class="text-muted small mt-1"><i class="bi bi-laptop me-1"></i> OS: <?php echo htmlspecialchars($device['operating_system'] ?: 'Unknown'); ?></div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="noc-card p-3 h-100">
            <div class="text-muted small">ROUNDTRIP LATENCY</div>
            <div id="liveLatencyVal" class="fs-4 fw-bold text-success mt-1"><?php echo round((float)$device['latency'], 1); ?> ms</div>
            <div class="text-muted small mt-1"><i class="bi bi-activity me-1"></i> Loss: <?php echo (int)$device['packet_loss']; ?>%</div>
        </div>
    </div>
</div>

<div class="row g-4">
    <!-- Left Column: Latency Chart & Port Scan Results -->
    <div class="col-lg-7">
        <!-- Latency Chart (Section 8) -->
        <div class="noc-card mb-4">
            <div class="noc-card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-graph-up me-2 text-primary"></i>Latency Response History (ms)</span>
                <span class="live-badge">Ping Telemetry</span>
            </div>
            <div class="noc-card-body p-3" style="height: 280px;">
                <canvas id="deviceLatencyChart"></canvas>
            </div>
        </div>

        <!-- Port Scan Results Container -->
        <div class="noc-card mb-4" id="portScanCard" style="display:none;">
            <div class="noc-card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-search me-2 text-info"></i>Port Scan Diagnostics (<span id="portScanTarget"></span>)</span>
                <span id="portScanBadge" class="badge bg-secondary">Scanning...</span>
            </div>
            <div class="noc-card-body p-3" id="portScanBody">
                <div class="text-center text-muted py-3">
                    <i class="bi bi-arrow-repeat spin-icon fs-3 d-block mb-2"></i>
                    Probing standard networking ports...
                </div>
            </div>
        </div>

        <!-- Device Event History -->
        <div class="noc-card">
            <div class="noc-card-header">
                <span><i class="bi bi-clock-history me-2 text-warning"></i>Device Network Events</span>
            </div>
            <div class="noc-card-body p-0">
                <?php if (empty($deviceEvents)): ?>
                    <div class="text-center text-muted py-4 small">No specific change events recorded for this device.</div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="noc-table align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Timestamp</th>
                                    <th>Event</th>
                                    <th>Severity</th>
                                    <th>Details</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($deviceEvents as $evt): ?>
                                    <tr>
                                        <td class="small text-muted font-monospace"><?php echo htmlspecialchars($evt['timestamp']); ?></td>
                                        <td><span class="badge bg-warning text-dark"><?php echo htmlspecialchars($evt['event_type']); ?></span></td>
                                        <td><span class="badge bg-info text-dark"><?php echo htmlspecialchars($evt['severity']); ?></span></td>
                                        <td class="small"><?php echo htmlspecialchars($evt['message']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Right Column: Device Metadata & Notes Editor -->
    <div class="col-lg-5">
        <!-- Edit Device Inventory & Notes (Section 8 & 12) -->
        <div class="noc-card mb-4">
            <div class="noc-card-header">
                <span><i class="bi bi-pencil-square me-2 text-warning"></i>Device Inventory & Notes</span>
            </div>
            <div class="noc-card-body p-3">
                <form method="POST" action="device_detail.php?id=<?php echo $device['id']; ?>">
                    <input type="hidden" name="update_device_info" value="1">
                    
                    <div class="mb-3">
                        <label class="form-label text-muted small fw-bold">DEVICE HOSTNAME</label>
                        <input type="text" name="hostname" class="form-control form-bg-dark border-secondary text-white" value="<?php echo htmlspecialchars($device['hostname'] ?: ''); ?>" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label text-muted small fw-bold">DEVICE CLASSIFICATION</label>
                        <select name="device_type" class="form-select form-bg-dark border-secondary text-white">
                            <?php
                            $types = ['Computer', 'Laptop', 'Router', 'Switch', 'Access Point', 'Smartphone', 'Printer', 'Server', 'IoT Device', 'Unknown'];
                            foreach ($types as $tp) {
                                $sel = ($device['device_type'] === $tp) ? 'selected' : '';
                                echo "<option value=\"$tp\" $sel>$tp</option>";
                            }
                            ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label text-muted small fw-bold">ADMINISTRATOR NOTES</label>
                        <textarea name="notes" class="form-control form-bg-dark border-secondary text-white" rows="3" placeholder="e.g. Main office computer, Administrator laptop, Network printer..."><?php echo htmlspecialchars($device['notes'] ?: ''); ?></textarea>
                    </div>

                    <div class="form-check mb-4">
                        <input class="form-check-input" type="checkbox" name="is_trusted" id="trustCheck" value="1" <?php echo !empty($device['is_trusted']) ? 'checked' : ''; ?>>
                        <label class="form-check-label text-white small" for="trustCheck">
                            Mark device as <strong>Trusted Device</strong>
                        </label>
                    </div>

                    <div class="d-flex justify-content-between">
                        <button type="submit" class="btn btn-warning btn-sm fw-bold">
                            <i class="bi bi-check-lg me-1"></i> Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Metadata & Timestamps Card -->
        <div class="noc-card mb-4">
            <div class="noc-card-header">
                <span><i class="bi bi-info-circle me-2 text-info"></i>Audit Telemetry</span>
            </div>
            <div class="noc-card-body p-3">
                <ul class="list-group list-group-flush bg-transparent">
                    <li class="list-group-item bg-transparent text-muted small d-flex justify-content-between px-0">
                        <span>Device Database ID:</span>
                        <strong class="text-white">#<?php echo $device['id']; ?></strong>
                    </li>
                    <li class="list-group-item bg-transparent text-muted small d-flex justify-content-between px-0">
                        <span>Manufacturer Vendor:</span>
                        <strong class="text-white"><?php echo htmlspecialchars($device['vendor'] ?: 'Unknown'); ?></strong>
                    </li>
                    <li class="list-group-item bg-transparent text-muted small d-flex justify-content-between px-0">
                        <span>Operating System:</span>
                        <strong class="text-white"><?php echo htmlspecialchars($device['operating_system'] ?: 'Unknown'); ?></strong>
                    </li>
                    <li class="list-group-item bg-transparent text-muted small d-flex justify-content-between px-0">
                        <span>First Discovered:</span>
                        <strong class="text-white font-monospace"><?php echo htmlspecialchars($device['first_seen'] ?: 'N/A'); ?></strong>
                    </li>
                    <li class="list-group-item bg-transparent text-muted small d-flex justify-content-between px-0">
                        <span>Last Seen Active:</span>
                        <strong class="text-white font-monospace"><?php echo htmlspecialchars($device['last_seen'] ?: 'N/A'); ?></strong>
                    </li>
                </ul>

                <hr class="border-secondary my-3">

                <!-- Danger Zone / Delete -->
                <form method="POST" action="device_detail.php?id=<?php echo $device['id']; ?>" onsubmit="return confirm('Are you sure you want to remove this device from inventory?');">
                    <input type="hidden" name="delete_device" value="1">
                    <button type="submit" class="btn btn-outline-danger btn-sm w-100">
                        <i class="bi bi-trash me-1"></i> Remove Device from Inventory
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
// Chart.js Latency Response
const chartLabels = <?php echo json_encode($chartLabels); ?>;
const chartLatency = <?php echo json_encode($chartLatency); ?>;

const ctxD = document.getElementById('deviceLatencyChart').getContext('2d');
const latencyChart = new Chart(ctxD, {
    type: 'line',
    data: {
        labels: chartLabels.length ? chartLabels : ['12:00', '12:05', '12:10'],
        datasets: [{
            label: 'Latency (ms)',
            data: chartLatency.length ? chartLatency : [4.5, 3.8, 4.2],
            borderColor: '#3b82f6',
            backgroundColor: 'rgba(59, 130, 246, 0.15)',
            fill: true,
            tension: 0.3,
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

// Live Ping Action
function runLivePing(ip, id) {
    const btn = document.getElementById('livePingBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="bi bi-arrow-repeat spin-icon me-1"></i> Pinging...';

    fetch('api/devices.php?action=ping&ip=' + encodeURIComponent(ip) + '&id=' + id)
        .then(res => res.json())
        .then(data => {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-broadcast me-1"></i> Ping Device Now';
            
            const latencyVal = document.getElementById('liveLatencyVal');
            const topBadge = document.getElementById('topStatusBadge');
            
            // Generate current timestamp for chart label (HH:MM:SS)
            const now = new Date();
            const timeLabel = now.getHours().toString().padStart(2, '0') + ':' + 
                              now.getMinutes().toString().padStart(2, '0') + ':' + 
                              now.getSeconds().toString().padStart(2, '0');

            if (data.status === 'Online') {
                latencyVal.innerText = `${data.latency} ms`;
                latencyVal.classList.remove('text-danger');
                latencyVal.classList.add('text-success');
                
                topBadge.className = 'badge bg-success fs-6 ms-2';
                topBadge.innerHTML = '🟢 Online';

                // Add to chart
                latencyChart.data.labels.push(timeLabel);
                latencyChart.data.datasets[0].data.push(data.latency);
                
                alert(`Ping Success! Host ${ip} responded in ${data.latency} ms.`);
            } else {
                latencyVal.innerText = 'Unreachable';
                latencyVal.classList.remove('text-success');
                latencyVal.classList.add('text-danger');
                
                topBadge.className = 'badge bg-danger fs-6 ms-2';
                topBadge.innerHTML = '🔴 Offline';
                
                // Add failed point to chart (represented as 0 or skipped)
                latencyChart.data.labels.push(timeLabel);
                latencyChart.data.datasets[0].data.push(0);

                alert(`Ping Failed! Host ${ip} is unreachable.`);
            }
            
            // Keep chart at max 20 points
            if (latencyChart.data.labels.length > 20) {
                latencyChart.data.labels.shift();
                latencyChart.data.datasets[0].data.shift();
            }
            latencyChart.update();
        })
        .catch(err => {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-broadcast me-1"></i> Ping Device Now';
            alert('Ping request error: ' + err.message);
        });
}

// Live Port Scan Action
function runPortScan(ip) {
    const card = document.getElementById('portScanCard');
    const targetSpan = document.getElementById('portScanTarget');
    const badge = document.getElementById('portScanBadge');
    const body = document.getElementById('portScanBody');

    card.style.display = 'block';
    targetSpan.innerText = ip;
    badge.className = 'badge bg-warning text-dark';
    badge.innerText = 'Probing...';
    body.innerHTML = '<div class="text-center text-muted py-3"><i class="bi bi-arrow-repeat spin-icon fs-3 d-block mb-2"></i>Probing standard ports (80, 443, 22, 21, 23, 53, 445, 3389)...</div>';

    fetch('api/port_scan.php?ip=' + encodeURIComponent(ip))
        .then(res => res.json())
        .then(data => {
            badge.className = 'badge bg-success';
            badge.innerText = 'Completed';

            if (data.open_ports && data.open_ports.length > 0) {
                let html = '<div class="table-responsive"><table class="table table-dark table-sm mb-0"><thead><tr><th>Port</th><th>Service</th><th>State</th></tr></thead><tbody>';
                data.open_ports.forEach(p => {
                    html += `<tr><td class="font-monospace text-info">${p.port}</td><td>${p.service}</td><td><span class="badge bg-success">OPEN</span></td></tr>`;
                });
                html += '</tbody></table></div>';
                body.innerHTML = html;
            } else {
                body.innerHTML = '<div class="text-center text-muted py-2"><i class="bi bi-shield-check text-success me-1"></i> No standard open management ports detected on this host.</div>';
            }
        })
        .catch(err => {
            badge.className = 'badge bg-danger';
            badge.innerText = 'Failed';
            body.innerHTML = `<div class="text-danger small">Port scan failed: ${err.message}</div>`;
        });
}
</script>

<?php require_once __DIR__ . '/footer.php'; ?>
