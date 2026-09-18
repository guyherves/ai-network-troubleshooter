<?php
/**
 * NetSentry - PHP Database Auto-Importer
 * Automatically imports db_setup.sql into your AMPPS MySQL database
 */

$host = '127.0.0.1';
$user = 'root';
$pass = 'mysql'; // Default AMPPS MySQL password
$sqlFile = __DIR__ . '/db_setup.sql';

if (!file_exists($sqlFile)) {
    die("Error: SQL file 'db_setup.sql' not found.\n");
}

echo "[NetSentry Setup] Importing database schema into MySQL...\n";

try {
    // 1. Connect to MySQL without choosing a database
    $pdo = new PDO("mysql:host={$host};charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
} catch (PDOException $e) {
    // Fallback: try empty password if 'mysql' fails
    try {
        $pdo = new PDO("mysql:host={$host};charset=utf8mb4", $user, '', [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]);
    } catch (PDOException $ex) {
        die("Connection to MySQL server failed: " . $ex->getMessage() . "\nMake sure AMPPS MySQL service is running.\n");
    }
}

try {
    // 2. Read and execute db_setup.sql
    $sql = file_get_contents($sqlFile);
    $pdo->exec($sql);

    echo "✅ Database 'network_troubleshooting' and all tables imported successfully!\n";
    if (php_sapi_name() !== 'cli') {
        echo "<p><a href='login.php'>Click here to go to Login Page</a></p>";
    }
} catch (PDOException $e) {
    echo "❌ Error importing SQL script: " . $e->getMessage() . "\n";
}
