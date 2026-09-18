<?php
$pageTitle = "User Profile Settings";
require_once __DIR__ . '/header.php';

$pdo = get_db();
$user = get_current_user_data();

$msg = '';
$msgType = 'info';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $newPassword = trim($_POST['new_password'] ?? '');

    if ($email) {
        if ($newPassword) {
            $hashed = password_hash($newPassword, PASSWORD_BCRYPT);
            $stmt = $pdo->prepare("UPDATE user SET email = :email, password = :pwd WHERE id = :id");
            $stmt->execute([':email' => $email, ':pwd' => $hashed, ':id' => $user['id']]);
        } else {
            $stmt = $pdo->prepare("UPDATE user SET email = :email WHERE id = :id");
            $stmt->execute([':email' => $email, ':id' => $user['id']]);
        }
        $msg = "Profile updated successfully!";
        $msgType = "success";
        $user = get_current_user_data(); // Refresh
    }
}
?>

<div class="mb-4">
    <h4 class="fw-bold text-white"><i class="bi bi-person-gear me-2 text-primary"></i>User Profile</h4>
    <p class="text-muted small">Manage your account information and security credentials</p>
</div>

<?php if ($msg): ?>
    <div class="alert alert-<?php echo $msgType; ?> alert-dismissible fade show" role="alert">
        <?php echo htmlspecialchars($msg); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="row g-4">
    <div class="col-md-5">
        <div class="card bg-dark text-white border-secondary p-4 shadow text-center">
            <img src="app/static/profile_pics/default.jpg" class="rounded-circle mx-auto mb-3" style="width: 100px; height: 100px; object-fit: cover; border: 3px solid var(--primary);">
            <h5 class="fw-bold mb-1"><?php echo htmlspecialchars($user['username']); ?></h5>
            <span class="badge bg-primary mb-3 text-capitalize" style="width: fit-content; margin: 0 auto;"><?php echo htmlspecialchars($user['role']); ?> Role</span>
            <p class="text-muted small mb-0"><i class="bi bi-envelope me-1"></i><?php echo htmlspecialchars($user['email']); ?></p>
        </div>
    </div>

    <div class="col-md-7">
        <div class="card bg-dark text-white border-secondary p-4 shadow">
            <h5 class="mb-3 text-info"><i class="bi bi-pencil-square me-2"></i>Edit Details</h5>
            <form method="POST" action="profile.php">
                <div class="mb-3">
                    <label class="form-label text-muted small">Username</label>
                    <input type="text" class="form-control bg-secondary text-white border-dark" value="<?php echo htmlspecialchars($user['username']); ?>" disabled>
                    <small class="text-muted">Username cannot be changed.</small>
                </div>
                <div class="mb-3">
                    <label class="form-label text-muted small">Email Address</label>
                    <input type="email" class="form-control bg-secondary text-white border-dark" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                </div>
                <div class="mb-3">
                    <label class="form-label text-muted small">New Password (leave blank to keep current)</label>
                    <input type="password" class="form-control bg-secondary text-white border-dark" name="new_password" placeholder="••••••••">
                </div>
                <button type="submit" class="btn btn-primary py-2 px-4">
                    <i class="bi bi-save me-1"></i> Save Profile Changes
                </button>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
