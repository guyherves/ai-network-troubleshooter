<?php
$pageTitle = "System & Offline Device Alerts";
require_once __DIR__ . '/header.php';
$pdo = get_db();

$stmt = $pdo->query("SELECT * FROM alert ORDER BY id DESC LIMIT 100");
$alerts = $stmt->fetchAll();
?>

<div class="d-flex align-items-center justify-content-between mb-4">
    <div>
        <h4 class="fw-bold text-white"><i class="bi bi-bell me-2 text-warning"></i>Offline Device & Security Alerts</h4>
        <p class="text-muted mb-0 small">Active alerts for device state transitions and network anomalies</p>
    </div>
    <div>
        <button class="btn btn-outline-danger btn-sm" onclick="clearAllAlerts()">
            <i class="bi bi-trash me-1"></i> Clear All Alerts
        </button>
    </div>
</div>

<div class="card bg-dark text-white border-secondary shadow">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-dark table-hover mb-0 align-middle">
                <thead class="table-secondary">
                    <tr>
                        <th>Severity</th>
                        <th>Category</th>
                        <th>Message</th>
                        <th>Timestamp</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($alerts)): ?>
                        <tr><td colspan="6" class="text-center py-4 text-success"><i class="bi bi-check-circle fs-3 d-block mb-1"></i>All clear — no system alerts logged!</td></tr>
                    <?php else: ?>
                        <?php foreach ($alerts as $a): ?>
                            <tr>
                                <td>
                                    <span class="badge <?php echo $a['severity'] === 'High' ? 'bg-danger' : ($a['severity'] === 'Medium' ? 'bg-warning text-dark' : 'bg-info'); ?>">
                                        <?php echo htmlspecialchars($a['severity']); ?>
                                    </span>
                                </td>
                                <td><span class="badge bg-secondary"><?php echo htmlspecialchars($a['category']); ?></span></td>
                                <td><?php echo htmlspecialchars($a['message']); ?></td>
                                <td class="small text-muted"><?php echo htmlspecialchars($a['timestamp']); ?></td>
                                <td>
                                    <span class="badge <?php echo $a['status'] === 'Resolved' ? 'bg-success' : 'bg-danger'; ?>">
                                        <?php echo htmlspecialchars($a['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($a['status'] === 'Unresolved'): ?>
                                        <button class="btn btn-sm btn-outline-success" onclick="acknowledgeAlert(<?php echo $a['id']; ?>)">
                                            <i class="bi bi-check2"></i> Resolve
                                        </button>
                                    <?php else: ?>
                                        <span class="text-muted small">Resolved</span>
                                    <?php endif; ?>
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
async function acknowledgeAlert(id) {
    const formData = new FormData();
    formData.append('id', id);
    await fetch('api/alerts.php?action=acknowledge', { method: 'POST', body: formData });
    location.reload();
}

async function clearAllAlerts() {
    if (confirm("Clear all alert records?")) {
        await fetch('api/alerts.php?action=clear', { method: 'POST' });
        location.reload();
    }
}
</script>

<?php require_once __DIR__ . '/footer.php'; ?>
