<?php
/**
 * NetSentry - Connected Devices Table Page
 * Section 7: Status (🟢 Online, 🔴 Offline, 🟡 Unknown), Device, IP, MAC, Type, Latency, Last Seen, Actions
 */

$pageTitle = "Network Devices — NetSentry";
require_once __DIR__ . '/header.php';
require_once __DIR__ . '/functions.php';

$pdo = get_db();

$filterStatus = trim($_GET['status'] ?? '');
$filterType   = trim($_GET['type'] ?? '');
$searchQuery  = trim($_GET['q'] ?? '');

$sql = "SELECT * FROM lan_device WHERE ip_address NOT LIKE '224.%' AND ip_address NOT LIKE '239.%' AND ip_address NOT LIKE '%.255'";
$params = [];

if (!empty($filterStatus)) {
    $sql .= " AND status = :status";
    $params[':status'] = $filterStatus;
}
if (!empty($filterType)) {
    $sql .= " AND device_type = :dtype";
    $params[':dtype'] = $filterType;
}
if (!empty($searchQuery)) {
    $sql .= " AND (ip_address LIKE :q OR mac_address LIKE :q OR hostname LIKE :q OR vendor LIKE :q OR notes LIKE :q)";
    $params[':q'] = "%{$searchQuery}%";
}

$sql .= " ORDER BY CASE WHEN status = 'Online' THEN 1 ELSE 2 END, INET_ATON(ip_address) ASC, id DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$devices = $stmt->fetchAll();

// Counts for filter pills
$totalCount   = (int)$pdo->query("SELECT COUNT(*) FROM lan_device")->fetchColumn();
$onlineCount  = (int)$pdo->query("SELECT COUNT(*) FROM lan_device WHERE status = 'Online'")->fetchColumn();
$offlineCount = (int)$pdo->query("SELECT COUNT(*) FROM lan_device WHERE status = 'Offline'")->fetchColumn();
$unknownCount = (int)$pdo->query("SELECT COUNT(*) FROM lan_device WHERE device_type = 'Unknown' OR hostname IS NULL")->fetchColumn();

// Unique device types for dropdown filter
$deviceTypesList = $pdo->query("SELECT DISTINCT device_type FROM lan_device WHERE device_type IS NOT NULL AND device_type != '' ORDER BY device_type ASC")->fetchAll(PDO::FETCH_COLUMN);
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
    <div>
        <h3 class="mb-0 text-white fw-bold">
            <i class="bi bi-hdd-network text-info me-2"></i>Connected Network Devices
        </h3>
        <p class="text-muted small mb-0">Real-time inventory of all discovered active and offline LAN hosts</p>
    </div>
    <div class="d-flex gap-2">
        <a href="scanner.php" class="btn btn-warning btn-sm fw-bold">
            <i class="bi bi-radar me-1"></i> Scan Subnet
        </a>
        <a href="export_csv.php" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-file-earmark-spreadsheet me-1"></i> Export CSV
        </a>
        <a href="export_pdf.php" target="_blank" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-file-earmark-pdf me-1"></i> Export PDF
        </a>
    </div>
</div>

<!-- Status Filter Pills -->
<div class="d-flex flex-wrap gap-2 mb-3">
    <a href="network_devices.php" class="btn btn-sm <?php echo empty($filterStatus) ? 'btn-primary' : 'btn-dark border-secondary'; ?>">
        All Devices <span class="badge bg-secondary ms-1"><?php echo $totalCount; ?></span>
    </a>
    <a href="network_devices.php?status=Online" class="btn btn-sm <?php echo ($filterStatus === 'Online') ? 'btn-success' : 'btn-dark border-success text-success'; ?>">
        🟢 Online <span class="badge bg-success ms-1"><?php echo $onlineCount; ?></span>
    </a>
    <a href="network_devices.php?status=Offline" class="btn btn-sm <?php echo ($filterStatus === 'Offline') ? 'btn-danger' : 'btn-dark border-danger text-danger'; ?>">
        🔴 Offline <span class="badge bg-danger ms-1"><?php echo $offlineCount; ?></span>
    </a>
    <a href="network_devices.php?type=Unknown" class="btn btn-sm <?php echo ($filterType === 'Unknown') ? 'btn-warning text-dark' : 'btn-dark border-warning text-warning'; ?>">
        🟡 Unknown <span class="badge bg-warning text-dark ms-1"><?php echo $unknownCount; ?></span>
    </a>
</div>

<!-- Search & Filtering Toolbar -->
<div class="noc-card p-3 mb-4">
    <form method="GET" action="network_devices.php" class="row g-2 align-items-center">
        <?php if (!empty($filterStatus)): ?>
            <input type="hidden" name="status" value="<?php echo htmlspecialchars($filterStatus); ?>">
        <?php endif; ?>
        <div class="col-md-5">
            <div class="input-group input-group-sm">
                <span class="input-group-text bg-dark border-secondary text-muted"><i class="bi bi-search"></i></span>
                <input type="text" name="q" class="form-control form-bg-dark border-secondary text-white" placeholder="Search by IP, MAC, Hostname, Vendor, or Notes..." value="<?php echo htmlspecialchars($searchQuery); ?>">
            </div>
        </div>
        <div class="col-md-3">
            <select name="type" class="form-select form-select-sm form-bg-dark border-secondary text-white" onchange="this.form.submit()">
                <option value="">All Device Types</option>
                <?php foreach ($deviceTypesList as $typeOpt): ?>
                    <option value="<?php echo htmlspecialchars($typeOpt); ?>" <?php echo ($filterType === $typeOpt) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($typeOpt); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-4 d-flex gap-2">
            <button type="submit" class="btn btn-primary btn-sm px-3">Filter</button>
            <a href="network_devices.php" class="btn btn-outline-secondary btn-sm">Reset</a>
            <button type="button" class="btn btn-outline-info btn-sm ms-auto" onclick="location.reload();">
                <i class="bi bi-arrow-clockwise"></i> Refresh
            </button>
        </div>
    </form>
</div>

<!-- Connected Devices Table (Section 7) -->
<div class="noc-card">
    <div class="noc-card-header d-flex justify-content-between align-items-center">
        <span><i class="bi bi-list-columns-reverse me-2 text-primary"></i>Device List (<?php echo count($devices); ?> Found)</span>
        <span class="live-badge">Real-Time</span>
    </div>
    <div class="noc-card-body p-0">
        <?php if (empty($devices)): ?>
            <div class="text-center text-muted py-5">
                <i class="bi bi-hdd-network text-muted fs-1 d-block mb-3"></i>
                <p class="mb-1">No matching devices found in inventory.</p>
                <a href="scanner.php" class="btn btn-warning btn-sm mt-2">Scan Local Network</a>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="noc-table align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Status</th>
                            <th>Device</th>
                            <th>IP Address</th>
                            <th>MAC Address</th>
                            <th>Type</th>
                            <th>Vendor</th>
                            <th>Latency</th>
                            <th>Last Seen</th>
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
                                    <?php if (!empty($d['is_trusted'])): ?>
                                        <span class="badge bg-info text-dark ms-1" style="font-size:0.65rem;" title="Trusted Device"><i class="bi bi-patch-check-fill"></i> Trusted</span>
                                    <?php endif; ?>
                                    <?php if (!empty($d['notes'])): ?>
                                        <div class="small text-muted" style="font-size:0.72rem;"><i class="bi bi-sticky me-1"></i><?php echo htmlspecialchars($d['notes']); ?></div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="device_detail.php?id=<?php echo $d['id']; ?>" class="font-monospace text-info text-decoration-none fw-bold">
                                        <?php echo htmlspecialchars($d['ip_address']); ?>
                                    </a>
                                </td>
                                <td class="font-monospace text-muted small"><?php echo htmlspecialchars($d['mac_address'] ?: 'Unknown'); ?></td>
                                <td>
                                    <?php
                                    $t = $d['device_type'] ?: 'Unknown';
                                    $tBadge = ($t === 'Router') ? 'bg-primary' : (($t === 'Computer' || $t === 'Laptop') ? 'bg-info text-dark' : (($t === 'Smartphone') ? 'bg-warning text-dark' : (($t === 'Printer') ? 'bg-purple' : 'bg-secondary')));
                                    ?>
                                    <span class="badge <?php echo $tBadge; ?>"><?php echo htmlspecialchars($t); ?></span>
                                </td>
                                <td class="small text-muted"><?php echo htmlspecialchars($d['vendor'] ?: 'Unknown'); ?></td>
                                <td>
                                    <?php if ($d['status'] === 'Online'): ?>
                                        <span class="text-success small fw-bold"><i class="bi bi-activity me-1"></i><?php echo round((float)$d['latency'], 1); ?> ms</span>
                                    <?php else: ?>
                                        <span class="text-muted small">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="small text-muted"><?php echo htmlspecialchars($d['last_seen'] ?: 'N/A'); ?></td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <a href="device_detail.php?id=<?php echo $d['id']; ?>" class="btn btn-outline-info py-0 px-2" title="View Details">
                                            <i class="bi bi-eye"></i> Details
                                        </a>
                                        <button class="btn btn-outline-warning py-0 px-2" onclick="pingTarget('<?php echo htmlspecialchars($d['ip_address']); ?>')" title="Ping Device">
                                            <i class="bi bi-broadcast"></i> Ping
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
function pingTarget(ip) {
    const btn = event.target.closest('button');
    const origHtml = btn.innerHTML;
    btn.innerHTML = '<i class="bi bi-arrow-repeat spin-icon"></i>';
    btn.disabled = true;

    fetch('api/devices.php?action=ping&ip=' + encodeURIComponent(ip))
        .then(res => res.json())
        .then(data => {
            btn.innerHTML = origHtml;
            btn.disabled = false;
            if (data.status === 'Online') {
                alert(`Ping Success! Response time for ${ip}: ${data.latency} ms`);
            } else {
                alert(`Ping Failed! Target ${ip} is unreachable.`);
            }
            location.reload();
        })
        .catch(err => {
            btn.innerHTML = origHtml;
            btn.disabled = false;
            alert('Ping request failed.');
        });
}
</script>

<?php require_once __DIR__ . '/footer.php'; ?>
