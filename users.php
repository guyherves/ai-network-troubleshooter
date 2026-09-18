<?php
$pageTitle = "User Management & RBAC";
require_once __DIR__ . '/includes/header.php';
require_admin();

$pdo = get_db();
$msg = '';
$msgType = 'info';

// Handle user actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'create') {
        $username = trim($_POST['username'] ?? '');
        $email    = trim($_POST['email'] ?? '');
        $password = trim($_POST['password'] ?? '');
        $role     = trim($_POST['role'] ?? 'viewer');

        if ($username && $email && $password) {
            $hashed = password_hash($password, PASSWORD_BCRYPT);
            try {
                $stmt = $pdo->prepare("INSERT INTO user (username, email, password, role, image_file) VALUES (:u, :e, :p, :r, 'default.jpg')");
                $stmt->execute([':u' => $username, ':e' => $email, ':p' => $hashed, ':r' => $role]);
                $msg = "New {$role} user '{$username}' created!";
                $msgType = "success";
            } catch (Exception $e) {
                $msg = "Error creating user: Username or Email already exists.";
                $msgType = "danger";
            }
        }
    }

    if ($action === 'delete') {
        $userId = (int)($_POST['user_id'] ?? 0);
        if ($userId > 1) { // Prevent deleting default admin #1
            $stmt = $pdo->prepare("DELETE FROM user WHERE id = :id");
            $stmt->execute([':id' => $userId]);
            $msg = "User deleted successfully.";
            $msgType = "success";
        } else {
            $msg = "Cannot delete primary admin account.";
            $msgType = "warning";
        }
    }
}

$stmtUsers = $pdo->query("SELECT * FROM user ORDER BY id ASC");
$allUsers = $stmtUsers->fetchAll();
?>

<div class="d-flex align-items-center justify-content-between mb-4">
    <div>
        <h4 class="fw-bold text-white"><i class="bi bi-people me-2 text-primary"></i>User Management & RBAC</h4>
        <p class="text-muted mb-0 small">Manage system user accounts, roles, and administrative permissions</p>
    </div>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addUserModal">
        <i class="bi bi-person-plus me-1"></i> Add New User
    </button>
</div>

<?php if ($msg): ?>
    <div class="alert alert-<?php echo $msgType; ?> alert-dismissible fade show" role="alert">
        <?php echo htmlspecialchars($msg); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="card bg-dark text-white border-secondary shadow mb-4">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-dark table-hover mb-0 align-middle">
                <thead class="table-secondary">
                    <tr>
                        <th>ID</th>
                        <th>User</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($allUsers as $u): ?>
                        <tr>
                            <td class="text-muted">#<?php echo $u['id']; ?></td>
                            <td class="fw-bold text-white">
                                <i class="bi bi-person-circle me-2 text-info"></i><?php echo htmlspecialchars($u['username']); ?>
                            </td>
                            <td class="text-muted"><?php echo htmlspecialchars($u['email']); ?></td>
                            <td>
                                <span class="badge <?php echo $u['role'] === 'admin' ? 'bg-primary' : ($u['role'] === 'analyst' ? 'bg-info' : 'bg-secondary'); ?>">
                                    <?php echo htmlspecialchars($u['role']); ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($u['id'] > 1): ?>
                                    <form method="POST" action="users.php" class="d-inline" onsubmit="return confirm('Delete this user account?');">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger py-0 px-2"><i class="bi bi-trash"></i> Delete</button>
                                    </form>
                                <?php else: ?>
                                    <span class="text-muted small">System Admin</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal: Add New User -->
<div class="modal fade" id="addUserModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content bg-dark text-white border-secondary">
            <div class="modal-header border-secondary">
                <h5 class="modal-title"><i class="bi bi-person-plus me-2 text-primary"></i>Create New Account</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="users.php">
                <input type="hidden" name="action" value="create">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label text-muted small">Username</label>
                        <input type="text" class="form-control bg-secondary text-white border-dark" name="username" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-muted small">Email Address</label>
                        <input type="email" class="form-control bg-secondary text-white border-dark" name="email" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-muted small">Password</label>
                        <input type="password" class="form-control bg-secondary text-white border-dark" name="password" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-muted small">Role</label>
                        <select class="form-select bg-secondary text-white border-dark" name="role">
                            <option value="admin">Admin (Full Access)</option>
                            <option value="analyst">Analyst (Read & Diagnostic)</option>
                            <option value="viewer" selected>Viewer (Read Only)</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer border-secondary">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create User</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
