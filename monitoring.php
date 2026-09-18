<?php
/**
 * NetSentry - Continuous LAN Monitoring & Ping Engine
 * Section 14: Periodic ping monitoring, latency measurement, packet loss, online/offline detection, configurable intervals
 */

$pageTitle = "Monitoring — NetSentry";
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/functions.php';

$pdo = get_db();
$devices = get_subnet_devices($pdo, 100);

// Get current monitoring settings
$stmtInt = $pdo->query("SELECT setting_value FROM settings WHERE setting_key = 'ping_interval'");
$pingInterval = (int)($stmtInt->fetchColumn() ?: 30);
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
    <div>
        <h3 class="mb-0 text-white fw-bold">
            <i class="bi bi-activity text-danger me-2"></i>Continuous LAN Device Monitor
        </h3>
        <p class="text-muted small mb-0">Automated ICMP ping telemetry, response time monitoring, and packet loss tracking</p>
    </div>
    <div class="d-flex gap-2 align-items-center">
        <!-- Interval Selector -->
        <div class="input-group input-group-sm" style="width: 220px;">
            <span class="input-group-text bg-dark border-secondary text-muted"><i class="bi bi-stopwatch"></i></span>
            <select id="monitorIntervalSelect" class="form-select form-bg-dark border-secondary text-white" onchange="changeMonitoringInterval(this.value)">
                <option value="10" <?php echo $pingInterval === 10 ? 'selected' : ''; ?>>Interval: 10 Seconds</option>
                <option value="30" <?php echo $pingInterval === 30 ? 'selected' : ''; ?>>Interval: 30 Seconds</option>
                <option value="60" <?php echo $pingInterval === 60 ? 'selected' : ''; ?>>Interval: 1 Minute</option>
                <option value="300" <?php echo $pingInterval === 300 ? 'selected' : ''; ?>>Interval: 5 Minutes</option>
            </select>
        </div>

        <button id="toggleMonitorBtn" class="btn btn-sm btn-success" onclick="toggleAutoMonitoring()">
            <i class="bi bi-pause-fill me-1"></i> Live: ON
        </button>
        <button id="pingAllBtn" class="btn btn-sm btn-primary" onclick="pingAllDevices()">
            <i class="bi bi-broadcast me-1"></i> Ping All Now
        </button>
    </div>
</div>

<!-- Monitoring Status Banner -->
<div class="noc-card p-3 mb-4">
    <div class="row align-items-center g-3">
        <div class="col-md-3">
            <div class="text-muted small">MONITORED HOSTS</div>
            <div class="fs-4 fw-bold text-white"><?php echo count($devices); ?> Devices</div>
        </div>
        <div class="col-md-3">
            <div class="text-muted small">MONITORING STATUS</div>
            <div id="monitorStatusBadge" class="text-success fw-bold"><i class="bi bi-broadcast me-1"></i> Continuous Probing Active</div>
        </div>
        <div class="col-md-3">
            <div class="text-muted small">NEXT AUTOMATIC CYCLE</div>
            <div id="countdownText" class="text-info font-monospace fw-bold">30s</div>
        </div>
        <div class="col-md-3 text-md-end">
            <div class="text-muted small">LAST COMPLETED CYCLE</div>
            <div id="lastCycleTime" class="text-white small font-monospace"><?php echo date('H:i:s'); ?></div>
        </div>
    </div>
</div>

<!-- Live Devices Monitoring Grid -->
<div class="row g-3" id="monitoringGrid">
    <?php if (empty($devices)): ?>
        <div class="col-12">
            <div class="noc-card p-5 text-center text-muted">
                <i class="bi bi-hdd-network fs-1 d-block mb-3"></i>
                <p>No devices in network inventory to monitor.</p>
                <a href="scanner.php" class="btn btn-warning btn-sm">Scan Network First</a>
            </div>
        </div>
    <?php else: ?>
        <?php foreach ($devices as $d): ?>
            <div class="col-md-6 col-lg-4 col-xl-3" id="dev-card-<?php echo $d['id']; ?>">
                <div class="noc-card p-3 h-100 border-secondary">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <div>
                            <a href="device_detail.php?id=<?php echo $d['id']; ?>" class="fw-bold text-white text-decoration-none">
                                <?php echo htmlspecialchars($d['hostname'] ?: 'LAN Device'); ?>
                            </a>
                            <div class="font-monospace text-info small" style="font-size:0.75rem;"><?php echo htmlspecialchars($d['ip_address']); ?></div>
                        </div>
                        <span id="badge-<?php echo $d['id']; ?>" class="badge <?php echo ($d['status'] === 'Online') ? 'bg-success' : 'bg-danger'; ?>">
                            <?php echo ($d['status'] === 'Online') ? '🟢 Online' : '🔴 Offline'; ?>
                        </span>
                    </div>

                    <div class="d-flex justify-content-between text-muted small my-2">
                        <span>Latency: <strong id="lat-<?php echo $d['id']; ?>" class="text-white"><?php echo round((float)$d['latency'], 1); ?> ms</strong></span>
                        <span>Packet Loss: <strong id="loss-<?php echo $d['id']; ?>" class="<?php echo ($d['packet_loss'] > 0) ? 'text-danger' : 'text-success'; ?>"><?php echo (int)$d['packet_loss']; ?>%</strong></span>
                    </div>

                    <div class="progress bg-dark border border-secondary mb-2" style="height: 6px;">
                        <?php
                        $lat = (float)$d['latency'];
                        $pct = min(100, max(5, ($lat / 200.0) * 100));
                        $barColor = ($lat < 15) ? 'bg-success' : (($lat < 60) ? 'bg-warning' : 'bg-danger');
                        if ($d['status'] === 'Offline') { $pct = 100; $barColor = 'bg-danger'; }
                        ?>
                        <div id="bar-<?php echo $d['id']; ?>" class="progress-bar <?php echo $barColor; ?>" style="width: <?php echo $pct; ?>%;"></div>
                    </div>

                    <div class="d-flex justify-content-between align-items-center mt-2">
                        <small class="text-muted" style="font-size:0.7rem;" id="time-<?php echo $d['id']; ?>">Last checked: <?php echo htmlspecialchars($d['last_seen'] ?: 'Just now'); ?></small>
                        <button class="btn btn-outline-info btn-sm py-0 px-2" style="font-size:0.72rem;" onclick="pingIndividualDevice('<?php echo htmlspecialchars($d['ip_address']); ?>', <?php echo $d['id']; ?>)">
                            <i class="bi bi-broadcast"></i> Test
                        </button>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<script>
let monitorIntervalSeconds = <?php echo $pingInterval; ?>;
let countdown = monitorIntervalSeconds;
let isMonitoringActive = true;
let monitorTimer = null;
let countdownTimer = null;

function updateCountdown() {
    if (!isMonitoringActive) return;
    countdown--;
    if (countdown <= 0) {
        countdown = monitorIntervalSeconds;
        pingAllDevices();
    }
    document.getElementById('countdownText').innerText = `${countdown}s`;
}

function startCountdown() {
    if (countdownTimer) clearInterval(countdownTimer);
    countdownTimer = setInterval(updateCountdown, 1000);
}

function toggleAutoMonitoring() {
    const btn = document.getElementById('toggleMonitorBtn');
    const badge = document.getElementById('monitorStatusBadge');
    if (isMonitoringActive) {
        isMonitoringActive = false;
        btn.className = 'btn btn-sm btn-secondary';
        btn.innerHTML = '<i class="bi bi-play-fill me-1"></i> Live: PAUSED';
        badge.className = 'text-warning fw-bold';
        badge.innerHTML = '<i class="bi bi-pause-circle me-1"></i> Monitoring Paused';
    } else {
        isMonitoringActive = true;
        countdown = monitorIntervalSeconds;
        btn.className = 'btn btn-sm btn-success';
        btn.innerHTML = '<i class="bi bi-pause-fill me-1"></i> Live: ON';
        badge.className = 'text-success fw-bold';
        badge.innerHTML = '<i class="bi bi-broadcast me-1"></i> Continuous Probing Active';
    }
}

function changeMonitoringInterval(newVal) {
    monitorIntervalSeconds = parseInt(newVal);
    countdown = monitorIntervalSeconds;
    fetch(`api/monitor.php?action=set_interval&val=${newVal}`, { method: 'POST' });
}

function pingIndividualDevice(ip, id) {
    const badge = document.getElementById(`badge-${id}`);
    const latText = document.getElementById(`lat-${id}`);
    const lossText = document.getElementById(`loss-${id}`);
    const bar = document.getElementById(`bar-${id}`);
    const timeText = document.getElementById(`time-${id}`);

    badge.className = 'badge bg-secondary';
    badge.innerText = 'Pinging...';

    fetch('api/devices.php?action=ping&ip=' + encodeURIComponent(ip) + '&id=' + id)
        .then(res => res.json())
        .then(data => {
            if (data.status === 'Online') {
                badge.className = 'badge bg-success';
                badge.innerText = '🟢 Online';
                latText.innerText = `${data.latency} ms`;
                lossText.innerText = '0%';
                lossText.className = 'text-success';
                bar.className = 'progress-bar bg-success';
                bar.style.width = Math.min(100, Math.max(10, (data.latency / 100) * 100)) + '%';
            } else {
                badge.className = 'badge bg-danger';
                badge.innerText = '🔴 Offline';
                latText.innerText = '-';
                lossText.innerText = '100%';
                lossText.className = 'text-danger';
                bar.className = 'progress-bar bg-danger';
                bar.style.width = '100%';
            }
            timeText.innerText = 'Last checked: Just now';
        });
}

function pingAllDevices() {
    const btn = document.getElementById('pingAllBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="bi bi-arrow-repeat spin-icon me-1"></i> Pinging...';

    fetch('api/monitor.php?action=ping_all', { method: 'POST' })
        .then(res => res.json())
        .then(data => {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-broadcast me-1"></i> Ping All Now';
            document.getElementById('lastCycleTime').innerText = new Date().toLocaleTimeString();

            if (data.results) {
                data.results.forEach(d => {
                    const id = d.id;
                    const badge = document.getElementById(`badge-${id}`);
                    const latText = document.getElementById(`lat-${id}`);
                    const lossText = document.getElementById(`loss-${id}`);
                    const bar = document.getElementById(`bar-${id}`);
                    const timeText = document.getElementById(`time-${id}`);

                    if (badge) {
                        if (d.status === 'Online') {
                            badge.className = 'badge bg-success';
                            badge.innerText = '🟢 Online';
                            if (latText) latText.innerText = `${d.latency} ms`;
                            if (lossText) { lossText.innerText = '0%'; lossText.className = 'text-success'; }
                            if (bar) { bar.className = 'progress-bar bg-success'; bar.style.width = Math.min(100, Math.max(10, (d.latency / 100) * 100)) + '%'; }
                        } else {
                            badge.className = 'badge bg-danger';
                            badge.innerText = '🔴 Offline';
                            if (latText) latText.innerText = '-';
                            if (lossText) { lossText.innerText = '100%'; lossText.className = 'text-danger'; }
                            if (bar) { bar.className = 'progress-bar bg-danger'; bar.style.width = '100%'; }
                        }
                        if (timeText) timeText.innerText = 'Last checked: Just now';
                    }
                });
            }
        })
        .catch(err => {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-broadcast me-1"></i> Ping All Now';
        });
}

startCountdown();
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
