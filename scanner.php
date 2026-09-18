<?php
/**
 * NetSentry - Dedicated Network Scanner Page
 * Section 13: Interface Selection, Detected Network Subnet, Start Scan Button, Progress Bar, Results, History
 */

$pageTitle = "Network Scanner — NetSentry";
require_once __DIR__ . '/header.php';
require_once __DIR__ . '/functions.php';

$pdo = get_db();
$netInfo = get_network_gateway_info();

// Fetch recent scan history from network_scans table
$recentScans = $pdo->query("SELECT * FROM network_scans ORDER BY id DESC LIMIT 8")->fetchAll();
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
    <div>
        <h3 class="mb-0 text-white fw-bold">
            <i class="bi bi-radar text-warning me-2"></i>LAN Subnet Scanner & Device Discovery
        </h3>
        <p class="text-muted small mb-0">Dynamic ARP & ICMP network discovery engine for active subnet device identification</p>
    </div>
    <div class="d-flex gap-2">
        <a href="network_devices.php" class="btn btn-outline-info btn-sm">
            <i class="bi bi-hdd-network me-1"></i> View All Devices
        </a>
        <a href="topology.php" class="btn btn-outline-success btn-sm">
            <i class="bi bi-diagram-3 me-1"></i> Topology View
        </a>
    </div>
</div>

<div class="row g-4">
    <!-- Scanner Control Console -->
    <div class="col-lg-5">
        <div class="noc-card h-100">
            <div class="noc-card-header">
                <span><i class="bi bi-sliders me-2 text-warning"></i>Scanner Console</span>
                <span class="badge bg-primary">Dynamic Discovery</span>
            </div>
            <div class="noc-card-body p-3">
                <form id="scanForm" onsubmit="startSubnetScan(event)">
                    <div class="mb-3">
                        <label class="form-label text-muted small fw-bold">ACTIVE NETWORK INTERFACE</label>
                        <select id="scanInterface" class="form-select form-bg-dark border-secondary text-white">
                            <option value="<?php echo htmlspecialchars($netInfo['interface_name']); ?>" selected>
                                <?php echo htmlspecialchars($netInfo['interface_name']); ?> (<?php echo htmlspecialchars($netInfo['interface_ip']); ?>)
                            </option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label text-muted small fw-bold">DETECTED LOCAL SUBNET</label>
                        <div class="input-group">
                            <span class="input-group-text bg-dark border-secondary text-info"><i class="bi bi-diagram-2"></i></span>
                            <input type="text" id="scanSubnet" class="form-control form-bg-dark border-secondary text-white font-monospace" value="<?php echo htmlspecialchars($netInfo['subnet']); ?>" required>
                        </div>
                        <div class="form-text text-muted" style="font-size:0.75rem;">
                            Auto-detected from default gateway (<code><?php echo htmlspecialchars($netInfo['gateway_ip']); ?></code>). You can customize the CIDR subnet if needed.
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label text-muted small fw-bold">SCAN METHOD</label>
                        <div class="d-flex gap-3">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="scanMethod" id="method1" value="fast" checked>
                                <label class="form-check-label text-white small" for="method1">
                                    ARP + ICMP Sweep (Fast)
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="scanMethod" id="method2" value="full">
                                <label class="form-check-label text-white small" for="method2">
                                    Full Subnet Probe
                                </label>
                            </div>
                        </div>
                    </div>

                    <button type="submit" id="startScanBtn" class="btn btn-warning w-100 py-2 fw-bold text-dark fs-6">
                        <i class="bi bi-play-circle-fill me-1"></i> START NETWORK SCAN
                    </button>
                </form>

                <!-- Progress Bar Section -->
                <div id="scanProgressContainer" class="mt-4" style="display:none;">
                    <div class="d-flex justify-content-between text-muted small mb-1">
                        <span id="scanStatusText"><i class="bi bi-arrow-repeat spin-icon me-1"></i> Probing network hosts...</span>
                        <span id="scanPercentText" class="text-warning fw-bold">0%</span>
                    </div>
                    <div class="progress bg-dark border border-secondary" style="height: 12px; border-radius:6px;">
                        <div id="scanProgressBar" class="progress-bar progress-bar-striped progress-bar-animated bg-warning" role="progressbar" style="width: 0%;"></div>
                    </div>
                </div>

                <!-- Live Activity Feed / Terminal Log -->
                <div class="mt-4">
                    <label class="form-label text-muted small fw-bold">SCAN ACTIVITY LOG</label>
                    <div id="scanTerminal" class="p-2 font-monospace text-success bg-dark border border-secondary rounded" style="height: 140px; overflow-y: auto; font-size: 0.75rem; background: #0B1120 !important;">
                        <div>[Ready] NetSentry LAN discovery engine initialized.</div>
                        <div>[Ready] Detected Subnet: <?php echo htmlspecialchars($netInfo['subnet']); ?></div>
                        <div>[Ready] Gateway: <?php echo htmlspecialchars($netInfo['gateway_ip']); ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Scan Results & History Table -->
    <div class="col-lg-7">
        <!-- Live Discovered Results Container -->
        <div class="noc-card mb-4">
            <div class="noc-card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-check-circle-fill me-2 text-success"></i>Latest Scan Discovered Devices</span>
                <span id="discoveredCountBadge" class="status-badge status-online">Scan Ready</span>
            </div>
            <div class="noc-card-body p-0" id="scanResultsContainer" style="max-height: 280px; overflow-y: auto;">
                <div class="text-center text-muted py-5">
                    <i class="bi bi-radar text-warning fs-1 d-block mb-2"></i>
                    <p class="mb-0">Click <strong>"START NETWORK SCAN"</strong> to discover all connected devices on the LAN.</p>
                </div>
            </div>
        </div>

        <!-- Recent Network Scans History -->
        <div class="noc-card">
            <div class="noc-card-header">
                <span><i class="bi bi-clock-history me-2 text-info"></i>Recent Network Scan History</span>
            </div>
            <div class="noc-card-body p-0">
                <?php if (empty($recentScans)): ?>
                    <div class="text-center text-muted py-4 small">No previous scans recorded.</div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="noc-table align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Timestamp</th>
                                    <th>Subnet Range</th>
                                    <th>Devices Found</th>
                                    <th>New Devices</th>
                                    <th>Duration</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentScans as $sc): ?>
                                    <tr>
                                        <td class="small text-muted"><?php echo htmlspecialchars($sc['timestamp']); ?></td>
                                        <td class="font-monospace text-info"><?php echo htmlspecialchars($sc['subnet_range']); ?></td>
                                        <td class="fw-bold text-white"><?php echo (int)$sc['total_devices_found']; ?></td>
                                        <td>
                                            <?php if ((int)$sc['new_devices_count'] > 0): ?>
                                                <span class="badge bg-warning text-dark">+<?php echo (int)$sc['new_devices_count']; ?> New</span>
                                            <?php else: ?>
                                                <span class="text-muted small">0</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="small text-muted"><?php echo round((float)$sc['duration_seconds'], 2); ?>s</td>
                                        <td><span class="badge bg-success">Completed</span></td>
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
function appendTerminalLog(msg) {
    const term = document.getElementById('scanTerminal');
    const d = new Date().toLocaleTimeString();
    term.innerHTML += `<div>[${d}] ${msg}</div>`;
    term.scrollTop = term.scrollHeight;
}

function startSubnetScan(event) {
    event.preventDefault();
    const subnet = document.getElementById('scanSubnet').value;
    const btn = document.getElementById('startScanBtn');
    const pContainer = document.getElementById('scanProgressContainer');
    const pBar = document.getElementById('scanProgressBar');
    const pText = document.getElementById('scanPercentText');
    const pStatus = document.getElementById('scanStatusText');

    btn.disabled = true;
    btn.innerHTML = '<i class="bi bi-arrow-repeat spin-icon me-1"></i> SCANNING NETWORK...';
    pContainer.style.display = 'block';

    appendTerminalLog(`Starting ARP & ICMP discovery scan on ${subnet}...`);

    let progress = 10;
    pBar.style.width = '10%';
    pText.innerText = '10%';
    pStatus.innerHTML = '<i class="bi bi-arrow-repeat spin-icon me-1"></i> Querying network interface...';

    const interval = setInterval(() => {
        if (progress < 85) {
            progress += 15;
            pBar.style.width = progress + '%';
            pText.innerText = progress + '%';
            if (progress === 40) appendTerminalLog('Probing IP addresses 1 to 100...');
            if (progress === 70) appendTerminalLog('Probing IP addresses 101 to 254 & checking ARP cache...');
        }
    }, 400);

    fetch('api/scanner.php?action=scan&subnet=' + encodeURIComponent(subnet), { method: 'POST' })
        .then(res => res.json())
        .then(data => {
            clearInterval(interval);
            pBar.style.width = '100%';
            pText.innerText = '100%';
            pStatus.innerText = 'Scan Complete!';
            appendTerminalLog(`Scan finished! Found ${data.total_found} active devices in ${data.duration}s.`);
            if (data.new_devices > 0) {
                appendTerminalLog(`⚠ ALERT: ${data.new_devices} NEW device(s) detected!`);
            }

            // Render discovered devices in results table
            renderScanResults(data.devices);

            document.getElementById('discoveredCountBadge').innerText = `${data.total_found} Devices Discovered`;

            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-arrow-repeat me-1"></i> SCAN AGAIN';
        })
        .catch(err => {
            clearInterval(interval);
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-play-circle-fill me-1"></i> START NETWORK SCAN';
            appendTerminalLog(`Scan failed: ${err.message}`);
            alert('Scan failed. Please check network connectivity.');
        });
}

function renderScanResults(devices) {
    const container = document.getElementById('scanResultsContainer');
    if (!devices || devices.length === 0) {
        container.innerHTML = '<div class="text-center text-muted py-4">No active devices responded on this subnet.</div>';
        return;
    }

    let html = `
        <table class="noc-table align-middle mb-0">
            <thead>
                <tr>
                    <th>Status</th>
                    <th>Device</th>
                    <th>IP Address</th>
                    <th>MAC Address</th>
                    <th>Type</th>
                    <th>Latency</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
    `;

    devices.forEach(d => {
        html += `
            <tr>
                <td><span class="badge bg-success">🟢 Online</span></td>
                <td class="fw-bold text-white">${d.hostname || 'LAN Device'}</td>
                <td class="font-monospace text-info">${d.ip_address}</td>
                <td class="font-monospace text-muted small">${d.mac_address || 'Unknown'}</td>
                <td><span class="badge bg-secondary">${d.device_type || 'Unknown'}</span></td>
                <td class="text-success small fw-bold">${d.latency || 1.0} ms</td>
                <td>
                    <a href="network_devices.php?q=${encodeURIComponent(d.ip_address)}" class="btn btn-sm btn-outline-info py-0 px-2">
                        View
                    </a>
                </td>
            </tr>
        `;
    });

    html += '</tbody></table>';
    container.innerHTML = html;
}
</script>

<?php require_once __DIR__ . '/footer.php'; ?>
