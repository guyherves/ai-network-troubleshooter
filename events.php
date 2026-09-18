<?php
/**
 * NetSentry - Network Events & Change Detection Log Page
 * Section 9 & 10: New Device Detection, Device Offline Events, Status Changes, Audit Logs
 */

$pageTitle = "Network Events — NetSentry";
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/functions.php';

$pdo = get_db();

$filterType = trim($_GET['type'] ?? '');
$searchQuery = trim($_GET['q'] ?? '');

$sql = "SELECT * FROM network_events WHERE 1=1";
$params = [];

if (!empty($filterType)) {
    $sql .= " AND event_type = :type";
    $params[':type'] = $filterType;
}

if (!empty($searchQuery)) {
    $sql .= " AND (ip_address LIKE :q OR hostname LIKE :q OR message LIKE :q)";
    $params[':q'] = "%{$searchQuery}%";
}

$sql .= " ORDER BY id DESC LIMIT 150";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$events = $stmt->fetchAll();

// Counts for filter pills
$totalEvents   = (int)$pdo->query("SELECT COUNT(*) FROM network_events")->fetchColumn();
$newDevCount   = (int)$pdo->query("SELECT COUNT(*) FROM network_events WHERE event_type = 'NEW_DEVICE'")->fetchColumn();
$offlineCount  = (int)$pdo->query("SELECT COUNT(*) FROM network_events WHERE event_type = 'DEVICE_OFFLINE'")->fetchColumn();
$reconnCount   = (int)$pdo->query("SELECT COUNT(*) FROM network_events WHERE event_type = 'DEVICE_RECONNECTED'")->fetchColumn();
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
    <div>
        <h3 class="mb-0 text-white fw-bold">
            <i class="bi bi-bell-fill text-warning me-2"></i>Network Events & Change Detection Log
        </h3>
        <p class="text-muted small mb-0">Automated event stream tracking new connections, offline transitions, and network state changes</p>
    </div>
    <div class="d-flex gap-2">
        <a href="scanner.php" class="btn btn-warning btn-sm">
            <i class="bi bi-radar me-1"></i> Scan Network
        </a>
        <a href="export_csv.php" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-file-earmark-spreadsheet me-1"></i> Export Event CSV
        </a>
    </div>
</div>

<!-- Event Type Filter Buttons -->
<div class="d-flex flex-wrap gap-2 mb-3">
    <a href="events.php" class="btn btn-sm <?php echo empty($filterType) ? 'btn-primary' : 'btn-dark border-secondary'; ?>">
        All Events <span class="badge bg-secondary ms-1"><?php echo $totalEvents; ?></span>
    </a>
    <a href="events.php?type=NEW_DEVICE" class="btn btn-sm <?php echo ($filterType === 'NEW_DEVICE') ? 'btn-warning text-dark' : 'btn-dark border-warning text-warning'; ?>">
        ⚠ New Devices <span class="badge bg-warning text-dark ms-1"><?php echo $newDevCount; ?></span>
    </a>
    <a href="events.php?type=DEVICE_OFFLINE" class="btn btn-sm <?php echo ($filterType === 'DEVICE_OFFLINE') ? 'btn-danger' : 'btn-dark border-danger text-danger'; ?>">
        🔴 Device Offline <span class="badge bg-danger ms-1"><?php echo $offlineCount; ?></span>
    </a>
    <a href="events.php?type=DEVICE_RECONNECTED" class="btn btn-sm <?php echo ($filterType === 'DEVICE_RECONNECTED') ? 'btn-success' : 'btn-dark border-success text-success'; ?>">
        🟢 Reconnected <span class="badge bg-success ms-1"><?php echo $reconnCount; ?></span>
    </a>
</div>

<!-- Search Bar -->
<div class="noc-card p-3 mb-4">
    <form method="GET" action="events.php" class="row g-2 align-items-center">
        <?php if (!empty($filterType)): ?>
            <input type="hidden" name="type" value="<?php echo htmlspecialchars($filterType); ?>">
        <?php endif; ?>
        <div class="col-md-9">
            <div class="input-group input-group-sm">
                <span class="input-group-text bg-dark border-secondary text-muted"><i class="bi bi-search"></i></span>
                <input type="text" name="q" class="form-control form-bg-dark border-secondary text-white" placeholder="Search events by IP, Hostname, or keyword..." value="<?php echo htmlspecialchars($searchQuery); ?>">
            </div>
        </div>
        <div class="col-md-3 d-flex gap-2">
            <button type="submit" class="btn btn-primary btn-sm px-3">Search</button>
            <a href="events.php" class="btn btn-outline-secondary btn-sm">Reset</a>
            <button type="button" class="btn btn-outline-info btn-sm ms-auto" onclick="location.reload();">
                <i class="bi bi-arrow-clockwise"></i>
            </button>
        </div>
    </form>
</div>

<!-- Events Table Card -->
<div class="noc-card">
    <div class="noc-card-header d-flex justify-content-between align-items-center">
        <span><i class="bi bi-list-ul me-2 text-primary"></i>Event Records (<?php echo count($events); ?> Logged)</span>
        <span class="live-badge">Live Feed</span>
    </div>
    <div class="noc-card-body p-0">
        <?php if (empty($events)): ?>
            <div class="text-center text-muted py-5">
                <i class="bi bi-shield-check text-success fs-1 d-block mb-2"></i>
                <p>No network change events recorded matching criteria.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="noc-table align-middle mb-0">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Timestamp</th>
                            <th>Event Type</th>
                            <th>Severity</th>
                            <th>IP Address</th>
                            <th>Device Hostname</th>
                            <th>Event Description</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($events as $idx => $e): ?>
                            <tr>
                                <td class="text-muted"><?php echo $idx + 1; ?></td>
                                <td class="small text-muted font-monospace"><?php echo htmlspecialchars($e['timestamp']); ?></td>
                                <td>
                                    <?php
                                    $t = $e['event_type'];
                                    if ($t === 'NEW_DEVICE') {
                                        echo '<span class="badge bg-warning text-dark"><i class="bi bi-star-fill me-1"></i> NEW DEVICE</span>';
                                    } elseif ($t === 'DEVICE_OFFLINE') {
                                        echo '<span class="badge bg-danger"><i class="bi bi-exclamation-triangle-fill me-1"></i> OFFLINE</span>';
                                    } elseif ($t === 'DEVICE_RECONNECTED') {
                                        echo '<span class="badge bg-success"><i class="bi bi-check-circle-fill me-1"></i> RECONNECTED</span>';
                                    } else {
                                        echo '<span class="badge bg-info text-dark">' . htmlspecialchars($t) . '</span>';
                                    }
                                    ?>
                                </td>
                                <td>
                                    <?php
                                    $sev = $e['severity'];
                                    $sBadge = ($sev === 'Danger') ? 'bg-danger' : (($sev === 'Warning') ? 'bg-warning text-dark' : 'bg-info text-dark');
                                    ?>
                                    <span class="badge <?php echo $sBadge; ?>"><?php echo htmlspecialchars($sev); ?></span>
                                </td>
                                <td>
                                    <a href="network_devices.php?q=<?php echo urlencode($e['ip_address']); ?>" class="font-monospace text-info fw-bold text-decoration-none">
                                        <?php echo htmlspecialchars($e['ip_address']); ?>
                                    </a>
                                </td>
                                <td class="text-white small fw-bold"><?php echo htmlspecialchars($e['hostname'] ?: 'LAN Host'); ?></td>
                                <td class="small"><?php echo htmlspecialchars($e['message']); ?></td>
                                <td>
                                    <?php if (!empty($e['device_id'])): ?>
                                        <a href="device_detail.php?id=<?php echo (int)$e['device_id']; ?>" class="btn btn-sm btn-outline-info py-0 px-2">
                                            View Device
                                        </a>
                                    <?php else: ?>
                                        <a href="network_devices.php?q=<?php echo urlencode($e['ip_address']); ?>" class="btn btn-sm btn-outline-secondary py-0 px-2">
                                            Lookup
                                        </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
