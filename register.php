<?php
require_once __DIR__ . '/includes/db.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if ($username && $email && $password) {
        $pdo = get_db();
        
        // Check duplicate
        $stmt = $pdo->prepare("SELECT id FROM user WHERE username = :u OR email = :e");
        $stmt->execute([':u' => $username, ':e' => $email]);
        if ($stmt->fetch()) {
            $error = 'Username or email already exists!';
        } else {
            $hashed = password_hash($password, PASSWORD_BCRYPT);
            $stmtInsert = $pdo->prepare("
                INSERT INTO user (username, email, password, role, image_file)
                VALUES (:u, :e, :p, 'admin', 'default.jpg')
            ");
            $stmtInsert->execute([':u' => $username, ':e' => $email, ':p' => $hashed]);
            $success = 'Account created successfully! You can now login.';
        }
    } else {
        $error = 'Please fill out all fields.';
    }
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NetSentry PHP - Register</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        body {
            background-color: var(--bg-dark, #0f172a);
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
        }
        .auth-card {
            width: 100%;
            max-width: 450px;
            background: rgba(30, 41, 59, 0.85);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 12px;
        }
    </style>
</head>
<body>
    <div class="auth-card shadow-lg p-4 text-white">
        <div class="text-center mb-4">
            <div style="font-size: 2.5rem;">📡</div>
            <h3 class="fw-bold mt-2">Create Account</h3>
            <p class="text-muted small">Register an admin account for NetSentry</p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger py-2"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success py-2">
                <?php echo htmlspecialchars($success); ?> <a href="login.php" class="alert-link">Login here</a>
            </div>
        <?php endif; ?>

        <form method="POST" action="register.php">
            <div class="mb-3">
                <label class="form-label text-muted small">Username</label>
                <input type="text" class="form-control bg-dark text-white border-secondary" name="username" required>
            </div>
            <div class="mb-3">
                <label class="form-label text-muted small">Email Address</label>
                <input type="email" class="form-control bg-dark text-white border-secondary" name="email" required>
            </div>
            <div class="mb-3">
                <label class="form-label text-muted small">Password</label>
                <input type="password" class="form-control bg-dark text-white border-secondary" name="password" required>
            </div>
            <button type="submit" class="btn btn-primary w-100 py-2 mt-2">Create Account</button>
        </form>

        <div class="mt-4 text-center">
            <small class="text-muted">Already have an account? <a href="login.php" class="text-info">Login Here</a></small>
        </div>
    </div>
</body>
</html>
