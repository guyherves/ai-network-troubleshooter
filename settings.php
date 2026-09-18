<?php
$pageTitle = "System Configuration & Settings";
require_once __DIR__ . '/includes/header.php';
require_admin();

$settingsFile = __DIR__ . '/settings.json';
$settings = [
    'system_name' => 'NetSentry LAN Monitor',
    'org_name'    => 'LAN Asset & Offline Monitor',
    'ping_interval' => 10,
    'latency_threshold' => 150,
    'firewall_dry_run' => true
];

if (file_exists($settingsFile)) {
    $loaded = json_decode(file_get_contents($settingsFile), true);
    if (is_array($loaded)) {
        $settings = array_merge($settings, $loaded);
    }
}

$msg = '';
$msgType = 'info';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $settings['system_name'] = trim($_POST['system_name'] ?? $settings['system_name']);
    $settings['org_name']    = trim($_POST['org_name'] ?? $settings['org_name']);
    $settings['ping_interval'] = (int)($_POST['ping_interval'] ?? $settings['ping_interval']);
    $settings['latency_threshold'] = (int)($_POST['latency_threshold'] ?? $settings['latency_threshold']);
    $settings['firewall_dry_run'] = isset($_POST['firewall_dry_run']);

    file_put_contents($settingsFile, json_encode($settings, JSON_PRETTY_PRINT));
    $msg = "System settings saved successfully!";
    $msgType = "success";
}
?>

<div class="mb-4">
    <h4 class="fw-bold text-white"><i class="bi bi-gear me-2 text-primary"></i>System Configuration</h4>
    <p class="text-muted small">Configure network thresholds, system title, and background monitoring intervals</p>
</div>

<?php if ($msg): ?>
    <div class="alert alert-<?php echo $msgType; ?> alert-dismissible fade show" role="alert">
        <?php echo htmlspecialchars($msg); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="card bg-dark text-white border-secondary p-4 shadow">
    <form method="POST" action="settings.php">
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label text-muted small">System Name</label>
                <input type="text" class="form-control bg-secondary text-white border-dark" name="system_name" value="<?php echo htmlspecialchars($settings['system_name']); ?>" required>
            </div>
            <div class="col-md-6">
                <label class="form-label text-muted small">Organization Subtitle</label>
                <input type="text" class="form-control bg-secondary text-white border-dark" name="org_name" value="<?php echo htmlspecialchars($settings['org_name']); ?>" required>
            </div>
            <div class="col-md-6">
                <label class="form-label text-muted small">Background Ping Interval (Seconds)</label>
                <input type="number" class="form-control bg-secondary text-white border-dark" name="ping_interval" value="<?php echo (int)$settings['ping_interval']; ?>" required>
            </div>
            <div class="col-md-6">
                <label class="form-label text-muted small">High Latency Warning Threshold (ms)</label>
                <input type="number" class="form-control bg-secondary text-white border-dark" name="latency_threshold" value="<?php echo (int)$settings['latency_threshold']; ?>" required>
            </div>
            <div class="col-12 mt-4">
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" name="firewall_dry_run" id="dryRunCheck" <?php echo $settings['firewall_dry_run'] ? 'checked' : ''; ?>>
                    <label class="form-check-label text-white" for="dryRunCheck">
                        Firewall DRY RUN Mode (simulate blocking without modifying OS firewall)
                    </label>
                </div>
                <small class="text-warning d-block mt-1">
                    <i class="bi bi-exclamation-triangle me-1"></i>
                    When DRY RUN is <strong>disabled</strong>, NetSentry will execute real <code>netsh advfirewall</code> (Windows) or <code>iptables</code> (Linux) commands to block/unblock IPs.
                </small>
            </div>
        </div>
        <button type="submit" class="btn btn-primary mt-4 py-2 px-4">
            <i class="bi bi-save me-1"></i> Save Configuration
        </button>
    </form>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
