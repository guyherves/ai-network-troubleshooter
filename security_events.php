<?php
/**
 * NetSentry - Security Events & Intrusion Detection Logs Page
 */
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

require_login();
$pdo = get_db();

// Trigger live IDS check scan on load
try {
    $idsEngine = __DIR__ . '/api/ids_engine.php';
} catch (Exception $e) {}

$severityFilter = trim($_GET['severity'] ?? '');
$searchQuery    = trim($_GET['q'] ?? '');

$sql = "SELECT * FROM security_event WHERE 1=1";
$params = [];

if (!empty($severityFilter)) {
    $sql .= " AND severity = :sev";
    $params[':sev'] = $severityFilter;
}

if (!empty($searchQuery)) {
    $sql .= " AND (src_ip LIKE :q OR dest_ip LIKE :q OR attack_type LIKE :q OR description LIKE :q)";
    $params[':q'] = "%{$searchQuery}%";
}

$sql .= " ORDER BY id DESC LIMIT 100";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$events = $stmt->fetchAll();

// Counts for filter pills
$critCount = (int)$pdo->query("SELECT COUNT(*) FROM security_event WHERE severity = 'Critical'")->fetchColumn();
$highCount = (int)$pdo->query("SELECT COUNT(*) FROM security_event WHERE severity = 'High'")->fetchColumn();
$medCount  = (int)$pdo->query("SELECT COUNT(*) FROM security_event WHERE severity = 'Medium'")->fetchColumn();
$lowCount  = (int)$pdo->query("SELECT COUNT(*) FROM security_event WHERE severity = 'Low'")->fetchColumn();
$totalCount = (int)$pdo->query("SELECT COUNT(*) FROM security_event")->fetchColumn();

include_once __DIR__ . '/header.php';
?>

<div class="container-fluid px-4 py-3">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold mb-1"><i class="bi bi-shield-lock-fill text-danger me-2"></i>Security & Intrusion Detection Events</h2>
            <p class="text-muted mb-0">Real-time threat logs analyzed by NetSentry network security & active connection scanner</p>
        </div>
        <div class="d-flex gap-2">
            <button class="btn btn-outline-danger" onclick="runIdsScan();">
                <i class="bi bi-radar me-1"></i> Run Live Threat Audit
            </button>
            <a href="index.php" class="btn btn-outline-secondary">
                <i class="bi bi-speedometer2 me-1"></i> Dashboard
            </a>
        </div>
    </div>

    <!-- Severity Filter Buttons -->
    <div class="d-flex flex-wrap gap-2 mb-4">
        <a href="security_events.php" class="btn btn-sm <?php echo empty($severityFilter) ? 'btn-primary' : 'btn-dark border-secondary'; ?>">
            All Events <span class="badge bg-secondary ms-1"><?php echo $totalCount; ?></span>
        </a>
        <a href="security_events.php?severity=Critical" class="btn btn-sm <?php echo ($severityFilter === 'Critical') ? 'btn-danger' : 'btn-dark border-danger text-danger'; ?>">
            Critical <span class="badge bg-danger ms-1"><?php echo $critCount; ?></span>
        </a>
        <a href="security_events.php?severity=High" class="btn btn-sm <?php echo ($severityFilter === 'High') ? 'btn-warning text-dark' : 'btn-dark border-warning text-warning'; ?>">
            High <span class="badge bg-warning text-dark ms-1"><?php echo $highCount; ?></span>
        </a>
        <a href="security_events.php?severity=Medium" class="btn btn-sm <?php echo ($severityFilter === 'Medium') ? 'btn-info text-dark' : 'btn-dark border-info text-info'; ?>">
            Medium <span class="badge bg-info text-dark ms-1"><?php echo $medCount; ?></span>
        </a>
        <a href="security_events.php?severity=Low" class="btn btn-sm <?php echo ($severityFilter === 'Low') ? 'btn-secondary' : 'btn-dark border-secondary'; ?>">
            Low <span class="badge bg-secondary ms-1"><?php echo $lowCount; ?></span>
        </a>
    </div>

    <!-- Events Table Card -->
    <div class="card bg-dark border-secondary p-3">
        <!-- Search bar -->
        <form method="GET" action="security_events.php" class="mb-3">
            <?php if (!empty($severityFilter)): ?>
                <input type="hidden" name="severity" value="<?php echo htmlspecialchars($severityFilter); ?>">
            <?php endif; ?>
            <div class="input-group">
                <span class="input-group-text bg-dark border-secondary text-muted"><i class="bi bi-search"></i></span>
                <input type="text" name="q" class="form-bg-dark border-secondary text-white form-control" placeholder="Search by IP, Attack Type, or Keyword..." value="<?php echo htmlspecialchars($searchQuery); ?>">
                <button type="submit" class="btn btn-primary">Search</button>
            </div>
        </form>

        <div class="table-responsive">
            <table class="table table-dark table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Timestamp</th>
                        <th>Source IP</th>
                        <th>Target IP</th>
                        <th>Attack Type</th>
                        <th>Severity</th>
                        <th>Description</th>
                        <th>Action Taken</th>
                        <th>Detection Engine</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($events)): ?>
                        <tr>
                            <td colspan="10" class="text-center text-muted py-4">
                                <i class="bi bi-shield-check text-success fs-3 d-block mb-2"></i>
                                No security threat events found matching filter criteria.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($events as $index => $e): ?>
                            <tr>
                                <td class="text-muted"><?php echo $index + 1; ?></td>
                                <td class="small text-muted"><?php echo htmlspecialchars($e['timestamp']); ?></td>
                                <td>
                                    <a href="device_detail.php?ip=<?php echo urlencode($e['src_ip']); ?>" class="text-info fw-bold font-monospace text-decoration-none">
                                        <?php echo htmlspecialchars($e['src_ip']); ?>
                                    </a>
                                </td>
                                <td class="font-monospace small text-muted"><?php echo htmlspecialchars($e['dest_ip']); ?></td>
                                <td class="fw-bold text-warning"><?php echo htmlspecialchars($e['attack_type']); ?></td>
                                <td>
                                    <?php
                                    $s = $e['severity'];
                                    $badge = ($s === 'Critical') ? 'bg-danger' : (($s === 'High') ? 'bg-warning text-dark' : (($s === 'Medium') ? 'bg-info text-dark' : 'bg-secondary'));
                                    ?>
                                    <span class="badge <?php echo $badge; ?>"><?php echo htmlspecialchars($s); ?></span>
                                </td>
                                <td class="small"><?php echo htmlspecialchars($e['description']); ?></td>
                                <td>
                                    <?php if ($e['action_taken'] === 'Blocked'): ?>
                                        <span class="badge bg-danger"><i class="bi bi-shield-x me-1"></i> Blocked</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary"><i class="bi bi-eye me-1"></i> Logged</span>
                                    <?php endif; ?>
                                </td>
                                <td class="small text-info"><span class="badge bg-dark border border-secondary">NetSentry IDS</span></td>
                                <td>
                                    <a href="firewall_manager.php?block_ip=<?php echo urlencode($e['src_ip']); ?>" class="btn btn-sm btn-outline-danger" title="Block IP on Firewall">
                                        <i class="bi bi-shield-slash"></i> Block IP
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
function runIdsScan() {
    fetch('api/ids_engine.php')
        .then(res => res.json())
        .then(data => {
            alert('Live IDS Audit complete! Detected ' + data.new_detections + ' new threat events.');
            location.reload();
        });
}
</script>

<?php include_once __DIR__ . '/footer.php'; ?>
