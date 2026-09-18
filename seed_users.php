<?php
/**
 * NetSentry - PHP User Seeder Script
 * Seeds the database with default RBAC users (Admin, Analyst, Viewer).
 */

require_once __DIR__ . '/db.php';

$pdo = get_db();

function create_or_update_user($pdo, $username, $email, $password, $role) {
    // Check if user exists by username or email
    $stmt = $pdo->prepare("SELECT id, username, email FROM user WHERE username = :u OR email = :e");
    $stmt->execute([':u' => $username, ':e' => $email]);
    $user = $stmt->fetch();

    $hashedPassword = password_hash($password, PASSWORD_BCRYPT);

    if (!$user) {
        // Insert new user
        $stmtInsert = $pdo->prepare("
            INSERT INTO user (username, email, password, role, image_file)
            VALUES (:u, :e, :p, :r, 'default.jpg')
        ");
        $stmtInsert->execute([
            ':u' => $username,
            ':e' => $email,
            ':p' => $hashedPassword,
            ':r' => $role
        ]);
        echo "Created {$role} user: {$username} ({$email})\n";
    } else {
        // Update existing user role, email, and password
        $stmtUpdate = $pdo->prepare("
            UPDATE user SET email = :e, role = :r, password = :p WHERE id = :id
        ");
        $stmtUpdate->execute([
            ':e'  => $email,
            ':r'  => $role,
            ':p'  => $hashedPassword,
            ':id' => $user['id']
        ]);
        echo "Updated {$username} to {$role} role.\n";
    }
}

echo "[NetSentry Seeder] Seeding RBAC users into database...\n";

// Seed 3 RBAC Users
create_or_update_user($pdo, 'Admin', 'admin@example.com', 'admin123', 'admin');
create_or_update_user($pdo, 'Analyst', 'analyst@example.com', 'analyst123', 'analyst');
create_or_update_user($pdo, 'Viewer', 'viewer@example.com', 'viewer123', 'viewer');

echo "✅ Database seeding complete!\n";
if (php_sapi_name() !== 'cli') {
    echo "<p><a href='login.php'>Click here to Login</a></p>";
}
