<?php
$pageTitle = "IPS Firewall Rule Manager";
require_once __DIR__ . '/header.php';
require_once __DIR__ . '/firewall_manager.php';
require_admin();

$msg = '';
$msgType = 'info';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ip = trim($_POST['ip_address'] ?? '');
    $action = $_POST['action'] ?? '';

    if ($ip) {
        if ($action === 'block') {
            if (block_ip($ip)) {
                $msg = "Firewall rule created: Blocked IP {$ip}";
                $msgType = "success";
            } else {
                $msg = "Failed to block IP {$ip}";
                $msgType = "danger";
            }
        } elseif ($action === 'unblock') {
            if (unblock_ip($ip)) {
                $msg = "Firewall rule removed: Unblocked IP {$ip}";
                $msgType = "success";
            } else {
                $msg = "Failed to unblock IP {$ip}";
                $msgType = "danger";
            }
        }
    }
}
?>

<div class="mb-4">
    <h4 class="fw-bold text-white"><i class="bi bi-shield-slash me-2 text-danger"></i>IPS OS Firewall Manager</h4>
    <p class="text-muted small">Execute host OS firewall blocking commands (Windows Advanced Firewall / Linux iptables)</p>
</div>

<?php if ($msg): ?>
    <div class="alert alert-<?php echo $msgType; ?> alert-dismissible fade show" role="alert">
        <?php echo htmlspecialchars($msg); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="row g-4">
    <div class="col-md-6">
        <div class="card bg-dark text-white border-secondary p-4 shadow">
            <h5 class="mb-3 text-danger"><i class="bi bi-slash-circle me-2"></i>Block Malicious IP</h5>
            <form method="POST" action="firewall.php">
                <input type="hidden" name="action" value="block">
                <div class="mb-3">
                    <label class="form-label text-muted small">IP Address to Block</label>
                    <input type="text" class="form-control bg-secondary text-white border-dark font-monospace" name="ip_address" placeholder="e.g. 192.168.1.150" required>
                </div>
                <button type="submit" class="btn btn-danger w-100 py-2">
                    <i class="bi bi-shield-x me-1"></i> Add Firewall Block Rule
                </button>
            </form>
        </div>
    </div>

    <div class="col-md-6">
        <div class="card bg-dark text-white border-secondary p-4 shadow">
            <h5 class="mb-3 text-success"><i class="bi bi-check-circle me-2"></i>Unblock IP Address</h5>
            <form method="POST" action="firewall.php">
                <input type="hidden" name="action" value="unblock">
                <div class="mb-3">
                    <label class="form-label text-muted small">IP Address to Unblock</label>
                    <input type="text" class="form-control bg-secondary text-white border-dark font-monospace" name="ip_address" placeholder="e.g. 192.168.1.150" required>
                </div>
                <button type="submit" class="btn btn-success w-100 py-2">
                    <i class="bi bi-shield-check me-1"></i> Remove Firewall Block Rule
                </button>
            </form>
        </div>
    </div>
</div>

<?php
$settingsFile = __DIR__ . '/settings.json';
$isDryRun = true;
if (file_exists($settingsFile)) {
    $s = json_decode(file_get_contents($settingsFile), true);
    if (is_array($s) && isset($s['firewall_dry_run'])) {
        $isDryRun = (bool)$s['firewall_dry_run'];
    }
}
?>
<div class="card bg-dark text-white border-secondary mt-4 p-3 shadow">
    <h6 class="text-muted mb-1"><i class="bi bi-info-circle me-1"></i> Configuration Mode</h6>
    <?php if ($isDryRun): ?>
        <small class="text-warning">
            <i class="bi bi-exclamation-triangle me-1"></i>
            Currently running in <code>DRY RUN</code> mode — firewall commands are <strong>simulated</strong> (no real OS changes).
            Change this in <a href="settings.php" class="text-info">System Settings</a>.
        </small>
    <?php else: ?>
        <small class="text-success">
            <i class="bi bi-shield-fill-check me-1"></i>
            <strong>LIVE MODE</strong> — Real <code>netsh advfirewall</code> / <code>iptables</code> commands will be executed.
            Change this in <a href="settings.php" class="text-info">System Settings</a>.
        </small>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
