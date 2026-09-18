<?php
$pageTitle = "Ping & Diagnosis History";
require_once __DIR__ . '/includes/header.php';
$pdo = get_db();

$stmt = $pdo->query("SELECT * FROM diagnosis_history ORDER BY id DESC LIMIT 50");
$records = $stmt->fetchAll();
?>

<div class="mb-4">
    <h4 class="fw-bold text-white"><i class="bi bi-clock-history me-2 text-primary"></i>Ping & Diagnosis History</h4>
    <p class="text-muted small">Log of previous network state evaluations and rule recommendations</p>
</div>

<div class="card bg-dark text-white border-secondary shadow">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-dark table-hover mb-0 align-middle">
                <thead class="table-secondary">
                    <tr>
                        <th>ID</th>
                        <th>Timestamp</th>
                        <th>Target IP</th>
                        <th>Issue Detected</th>
                        <th>Recommendation</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($records)): ?>
                        <tr><td colspan="5" class="text-center py-4 text-muted">No diagnosis history recorded yet.</td></tr>
                    <?php else: ?>
                        <?php foreach ($records as $r): ?>
                            <tr>
                                <td class="text-muted">#<?php echo $r['id']; ?></td>
                                <td class="small text-muted"><?php echo htmlspecialchars($r['timestamp']); ?></td>
                                <td class="font-monospace fw-bold"><?php echo htmlspecialchars($r['target_ip']); ?></td>
                                <td>
                                    <span class="badge bg-warning text-dark"><?php echo htmlspecialchars($r['issue_detected']); ?></span>
                                </td>
                                <td class="small"><?php echo htmlspecialchars($r['recommendation']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
