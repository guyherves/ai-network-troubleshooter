<?php
/**
 * NetSentry - Dedicated Network Inventory Page
 * Section 12: All Discovered, Trusted, Unknown, Offline, Recently Discovered, Administrator Notes & Asset Tagging
 */

$pageTitle = "Network Inventory — NetSentry";
require_once __DIR__ . '/header.php';
require_once __DIR__ . '/functions.php';

$pdo = get_db();

$tab = $_GET['tab'] ?? 'all';

// Handle Note / Asset Update POST
$successMsg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_inventory'])) {
    $devId    = (int)$_POST['device_id'];
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
        ':id'    => $devId
    ]);

    $successMsg = 'Inventory details updated successfully!';
}

// Filter query based on active tab
$sql = "SELECT * FROM lan_device WHERE ip_address NOT LIKE '224.%' AND ip_address NOT LIKE '239.%' AND ip_address NOT LIKE '%.255'";

if ($tab === 'trusted') {
    $sql .= " AND is_trusted = 1";
} elseif ($tab === 'unknown') {
    $sql .= " AND (device_type = 'Unknown' OR hostname IS NULL OR hostname = 'Unknown')";
} elseif ($tab === 'offline') {
    $sql .= " AND status = 'Offline'";
} elseif ($tab === 'recent') {
    $sql .= " AND first_seen >= NOW() - INTERVAL 24 HOUR";
}

$sql .= " ORDER BY CASE WHEN status = 'Online' THEN 1 ELSE 2 END, id DESC";
$devices = $pdo->query($sql)->fetchAll();

// Counts for tabs
$countAll     = (int)$pdo->query("SELECT COUNT(*) FROM lan_device")->fetchColumn();
$countTrusted = (int)$pdo->query("SELECT COUNT(*) FROM lan_device WHERE is_trusted = 1")->fetchColumn();
$countUnknown = (int)$pdo->query("SELECT COUNT(*) FROM lan_device WHERE device_type = 'Unknown' OR hostname IS NULL")->fetchColumn();
$countOffline = (int)$pdo->query("SELECT COUNT(*) FROM lan_device WHERE status = 'Offline'")->fetchColumn();
$countRecent  = (int)$pdo->query("SELECT COUNT(*) FROM lan_device WHERE first_seen >= NOW() - INTERVAL 24 HOUR")->fetchColumn();
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
    <div>
        <h3 class="mb-0 text-white fw-bold">
            <i class="bi bi-boxes text-purple me-2"></i>LAN Asset & Hardware Inventory
        </h3>
        <p class="text-muted small mb-0">Manage network asset tags, device classifications, administrator notes, and trusted device lists</p>
    </div>
    <div class="d-flex gap-2">
        <a href="export_csv.php" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-file-earmark-spreadsheet me-1"></i> Export Inventory CSV
        </a>
        <a href="scanner.php" class="btn btn-warning btn-sm fw-bold">
            <i class="bi bi-radar me-1"></i> Scan LAN
        </a>
    </div>
</div>

<?php if (!empty($successMsg)): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="bi bi-check-circle-fill me-2"></i><?php echo htmlspecialchars($successMsg); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<!-- Inventory Navigation Tabs (Section 12) -->
<ul class="nav nav-pills gap-2 mb-4 border-bottom border-secondary pb-3">
    <li class="nav-item">
        <a class="nav-link <?php echo $tab === 'all' ? 'active' : 'text-muted'; ?>" href="inventory.php?tab=all">
            <i class="bi bi-collection me-1"></i> All Discovered <span class="badge bg-secondary ms-1"><?php echo $countAll; ?></span>
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?php echo $tab === 'trusted' ? 'active bg-info text-dark' : 'text-muted'; ?>" href="inventory.php?tab=trusted">
            <i class="bi bi-patch-check-fill me-1"></i> Trusted Devices <span class="badge bg-info text-dark ms-1"><?php echo $countTrusted; ?></span>
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?php echo $tab === 'unknown' ? 'active bg-warning text-dark' : 'text-muted'; ?>" href="inventory.php?tab=unknown">
            <i class="bi bi-question-circle me-1"></i> Unknown Devices <span class="badge bg-warning text-dark ms-1"><?php echo $countUnknown; ?></span>
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?php echo $tab === 'offline' ? 'active bg-danger' : 'text-muted'; ?>" href="inventory.php?tab=offline">
            <i class="bi bi-x-circle me-1"></i> Offline Devices <span class="badge bg-danger ms-1"><?php echo $countOffline; ?></span>
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?php echo $tab === 'recent' ? 'active bg-primary' : 'text-muted'; ?>" href="inventory.php?tab=recent">
            <i class="bi bi-stars me-1"></i> Recently Discovered <span class="badge bg-primary ms-1"><?php echo $countRecent; ?></span>
        </a>
    </li>
</ul>

<!-- Devices Inventory Table -->
<div class="noc-card">
    <div class="noc-card-header d-flex justify-content-between align-items-center">
        <span><i class="bi bi-table me-2 text-primary"></i>Inventory Records (<?php echo count($devices); ?> Items)</span>
        <span class="small text-muted">Click "Edit Notes" to update asset tag and description</span>
    </div>
    <div class="noc-card-body p-0">
        <?php if (empty($devices)): ?>
            <div class="text-center text-muted py-5">
                <i class="bi bi-boxes fs-1 d-block mb-3"></i>
                <p>No inventory records found under this filter tab.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="noc-table align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Status</th>
                            <th>Hostname</th>
                            <th>IP Address</th>
                            <th>MAC Address</th>
                            <th>Type</th>
                            <th>Vendor</th>
                            <th>Administrator Notes</th>
                            <th>Trusted</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($devices as $d): ?>
                            <tr>
                                <td>
                                    <?php echo ($d['status'] === 'Online') ? '<span class="badge bg-success">🟢 Online</span>' : '<span class="badge bg-danger">🔴 Offline</span>'; ?>
                                </td>
                                <td>
                                    <a href="device_detail.php?id=<?php echo $d['id']; ?>" class="fw-bold text-white text-decoration-none">
                                        <?php echo htmlspecialchars($d['hostname'] ?: 'LAN Host'); ?>
                                    </a>
                                </td>
                                <td class="font-monospace text-info"><?php echo htmlspecialchars($d['ip_address']); ?></td>
                                <td class="font-monospace text-muted small"><?php echo htmlspecialchars($d['mac_address'] ?: 'Unknown'); ?></td>
                                <td><span class="badge bg-secondary"><?php echo htmlspecialchars($d['device_type'] ?: 'Unknown'); ?></span></td>
                                <td class="small text-muted"><?php echo htmlspecialchars($d['vendor'] ?: 'Unknown'); ?></td>
                                <td>
                                    <?php if (!empty($d['notes'])): ?>
                                        <span class="text-warning small"><i class="bi bi-sticky me-1"></i><?php echo htmlspecialchars($d['notes']); ?></span>
                                    <?php else: ?>
                                        <span class="text-muted small italic">No notes</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($d['is_trusted'])): ?>
                                        <span class="badge bg-info text-dark"><i class="bi bi-shield-check me-1"></i> Trusted</span>
                                    <?php else: ?>
                                        <span class="badge bg-dark border border-secondary text-muted">Standard</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <button class="btn btn-sm btn-outline-warning py-0 px-2" data-bs-toggle="modal" data-bs-target="#editModal-<?php echo $d['id']; ?>">
                                        <i class="bi bi-pencil-square"></i> Edit Notes
                                    </button>
                                </td>
                            </tr>

                            <!-- Edit Device Notes Modal (Section 12) -->
                            <div class="modal fade" id="editModal-<?php echo $d['id']; ?>" tabindex="-1">
                                <div class="modal-dialog">
                                    <div class="modal-content form-bg-dark border-secondary text-white">
                                        <form method="POST" action="inventory.php?tab=<?php echo htmlspecialchars($tab); ?>">
                                            <input type="hidden" name="device_id" value="<?php echo $d['id']; ?>">
                                            <div class="modal-header border-secondary">
                                                <h5 class="modal-title"><i class="bi bi-pencil-square text-warning me-2"></i>Edit Inventory Details</h5>
                                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                            </div>
                                            <div class="modal-body">
                                                <div class="mb-3">
                                                    <label class="form-label small text-muted">IP & MAC Address</label>
                                                    <div class="font-monospace text-info"><?php echo htmlspecialchars($d['ip_address']); ?> &bull; <?php echo htmlspecialchars($d['mac_address'] ?: 'Unknown'); ?></div>
                                                </div>
                                                <div class="mb-3">
                                                    <label class="form-label small text-muted">Hostname / Device Name</label>
                                                    <input type="text" name="hostname" class="form-control form-bg-dark border-secondary text-white" value="<?php echo htmlspecialchars($d['hostname'] ?: ''); ?>" required>
                                                </div>
                                                <div class="mb-3">
                                                    <label class="form-label small text-muted">Device Type</label>
                                                    <select name="device_type" class="form-select form-bg-dark border-secondary text-white">
                                                        <?php
                                                        $opts = ['Computer', 'Laptop', 'Router', 'Switch', 'Access Point', 'Smartphone', 'Printer', 'Server', 'IoT Device', 'Unknown'];
                                                        foreach ($opts as $o) {
                                                            $sel = ($d['device_type'] === $o) ? 'selected' : '';
                                                            echo "<option value=\"$o\" $sel>$o</option>";
                                                        }
                                                        ?>
                                                    </select>
                                                </div>
                                                <div class="mb-3">
                                                    <label class="form-label small text-muted">Administrator Notes (e.g. "Main office computer", "Network printer")</label>
                                                    <textarea name="notes" class="form-control form-bg-dark border-secondary text-white" rows="3" placeholder="Enter custom note for this asset..."><?php echo htmlspecialchars($d['notes'] ?: ''); ?></textarea>
                                                </div>
                                                <div class="form-check mb-2">
                                                    <input class="form-check-input" type="checkbox" name="is_trusted" id="trust-<?php echo $d['id']; ?>" value="1" <?php echo !empty($d['is_trusted']) ? 'checked' : ''; ?>>
                                                    <label class="form-check-label text-white small" for="trust-<?php echo $d['id']; ?>">
                                                        Mark as <strong>Trusted Device</strong> (Suppress unverified device alerts)
                                                    </label>
                                                </div>
                                            </div>
                                            <div class="modal-footer border-secondary">
                                                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                                                <button type="submit" name="save_inventory" class="btn btn-warning btn-sm">Save Changes</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
