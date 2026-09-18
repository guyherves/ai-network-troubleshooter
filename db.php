<?php
/**
 * NetSentry - Database Connection and Application Helper Functions
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Database Credentials for AMPPS MySQL
define('DB_HOST', '127.0.0.1');
define('DB_NAME', 'network_troubleshooting');
define('DB_USER', 'root');
define('DB_PASS', 'mysql'); // AMPPS default password is 'mysql' or empty ''

function get_db() {
    static $pdo = null;
    if ($pdo === null) {
        $passwords = [DB_PASS, '']; // Try configured password, then empty
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        // Step 1: Connect to MySQL server (without specifying database)
        $connected = false;
        foreach ($passwords as $pw) {
            try {
                $pdo = new PDO("mysql:host=" . DB_HOST . ";charset=utf8mb4", DB_USER, $pw, $options);
                $connected = true;
                break;
            } catch (PDOException $e) {
                continue;
            }
        }
        if (!$connected) {
            die("MySQL connection failed. Ensure AMPPS MySQL is running.");
        }

        // Step 2: Create database if it doesn't exist
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci");
        $pdo->exec("USE `" . DB_NAME . "`");

        // Step 3: Auto-create all required tables if they don't exist
        ensure_tables($pdo);
    }
    return $pdo;
}

/**
 * Create all required tables if they don't exist (idempotent).
 */
function ensure_tables($pdo) {
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);

    if (!in_array('user', $tables)) {
        $pdo->exec("CREATE TABLE `user` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `username` varchar(20) NOT NULL,
            `email` varchar(120) NOT NULL,
            `image_file` varchar(20) NOT NULL DEFAULT 'default.jpg',
            `password` varchar(255) NOT NULL,
            `role` varchar(20) NOT NULL DEFAULT 'admin',
            PRIMARY KEY (`id`),
            UNIQUE KEY `username` (`username`),
            UNIQUE KEY `email` (`email`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        // Seed default admin (password: admin123)
        $pdo->exec("INSERT INTO `user` (`username`, `email`, `password`, `role`) VALUES
            ('admin', 'admin@network.local', '\$2y\$10\$e/18J481xP3R82y9N7Z12u8/R4Y627QdJmB0W6V4B8.2V0Y3R2WqG', 'admin')");
    }

    if (!in_array('network_log', $tables)) {
        $pdo->exec("CREATE TABLE `network_log` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `timestamp` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `target_ip` varchar(50) NOT NULL,
            `latency` float DEFAULT NULL,
            `packet_loss` float DEFAULT NULL,
            `status` varchar(20) NOT NULL,
            `bandwidth_mbps` float DEFAULT NULL,
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } else {
        // Ensure bandwidth_mbps column exists on existing tables
        try {
            $cols = $pdo->query("SHOW COLUMNS FROM `network_log` LIKE 'bandwidth_mbps'")->fetchAll();
            if (empty($cols)) {
                $pdo->exec("ALTER TABLE `network_log` ADD COLUMN `bandwidth_mbps` float DEFAULT NULL");
            }
        } catch (Exception $e) {}
    }

    if (!in_array('diagnosis_history', $tables)) {
        $pdo->exec("CREATE TABLE `diagnosis_history` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `timestamp` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `target_ip` varchar(50) NOT NULL,
            `issue_detected` varchar(100) NOT NULL,
            `recommendation` text NOT NULL,
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }

    if (!in_array('watchlist', $tables)) {
        $pdo->exec("CREATE TABLE `watchlist` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `ip_address` varchar(50) NOT NULL,
            `added_on` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `ip_address` (`ip_address`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }

    if (!in_array('alert', $tables)) {
        $pdo->exec("CREATE TABLE `alert` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `timestamp` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `severity` varchar(20) NOT NULL,
            `category` varchar(50) NOT NULL,
            `message` text NOT NULL,
            `status` varchar(20) NOT NULL DEFAULT 'Unresolved',
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }

    if (!in_array('ids_event', $tables)) {
        $pdo->exec("CREATE TABLE `ids_event` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `timestamp` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `src_ip` varchar(50) NOT NULL,
            `target_port` varchar(50) NOT NULL,
            `event_type` varchar(100) NOT NULL,
            `severity_level` varchar(50) NOT NULL,
            `severity_text` varchar(50) NOT NULL,
            `action_taken` varchar(50) NOT NULL,
            `action_text` varchar(50) NOT NULL,
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }

    if (!in_array('lan_device', $tables)) {
        $pdo->exec("CREATE TABLE `lan_device` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `hostname` varchar(100) DEFAULT NULL,
            `ip_address` varchar(50) NOT NULL,
            `mac_address` varchar(50) DEFAULT NULL,
            `device_type` varchar(50) NOT NULL DEFAULT 'Unknown',
            `vendor` varchar(100) DEFAULT 'Unknown',
            `operating_system` varchar(100) DEFAULT 'Unknown',
            `status` varchar(20) NOT NULL DEFAULT 'Online',
            `latency` float DEFAULT 0.0,
            `packet_loss` float DEFAULT 0.0,
            `is_trusted` tinyint(1) NOT NULL DEFAULT 0,
            `notes` text DEFAULT NULL,
            `first_seen` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `last_seen` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `is_monitored` tinyint(1) NOT NULL DEFAULT 1,
            `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `ip_address` (`ip_address`),
            KEY `idx_mac` (`mac_address`),
            KEY `idx_status` (`status`),
            KEY `idx_last_seen` (`last_seen`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } else {
        // Ensure all required columns exist on lan_device
        $deviceCols = $pdo->query("SHOW COLUMNS FROM `lan_device`")->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array('vendor', $deviceCols)) {
            $pdo->exec("ALTER TABLE `lan_device` ADD COLUMN `vendor` varchar(100) DEFAULT 'Unknown'");
        }
        if (!in_array('operating_system', $deviceCols)) {
            $pdo->exec("ALTER TABLE `lan_device` ADD COLUMN `operating_system` varchar(100) DEFAULT 'Unknown'");
        }
        if (!in_array('latency', $deviceCols)) {
            $pdo->exec("ALTER TABLE `lan_device` ADD COLUMN `latency` float DEFAULT 0.0");
        }
        if (!in_array('packet_loss', $deviceCols)) {
            $pdo->exec("ALTER TABLE `lan_device` ADD COLUMN `packet_loss` float DEFAULT 0.0");
        }
        if (!in_array('is_trusted', $deviceCols)) {
            $pdo->exec("ALTER TABLE `lan_device` ADD COLUMN `is_trusted` tinyint(1) NOT NULL DEFAULT 0");
        }
        if (!in_array('notes', $deviceCols)) {
            $pdo->exec("ALTER TABLE `lan_device` ADD COLUMN `notes` text DEFAULT NULL");
        }
        if (!in_array('created_at', $deviceCols)) {
            $pdo->exec("ALTER TABLE `lan_device` ADD COLUMN `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP");
        }
        if (!in_array('updated_at', $deviceCols)) {
            $pdo->exec("ALTER TABLE `lan_device` ADD COLUMN `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
        }
    }

    if (!in_array('network_interfaces', $tables)) {
        $pdo->exec("CREATE TABLE `network_interfaces` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `interface_name` varchar(100) NOT NULL,
            `ip_address` varchar(50) DEFAULT NULL,
            `subnet_mask` varchar(50) DEFAULT NULL,
            `cidr_subnet` varchar(50) DEFAULT NULL,
            `default_gateway` varchar(50) DEFAULT NULL,
            `mac_address` varchar(50) DEFAULT NULL,
            `status` varchar(20) NOT NULL DEFAULT 'Up',
            `is_active` tinyint(1) NOT NULL DEFAULT 1,
            `last_detected` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }

    if (!in_array('network_scans', $tables)) {
        $pdo->exec("CREATE TABLE `network_scans` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `timestamp` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `interface_name` varchar(100) DEFAULT 'Default Interface',
            `subnet_range` varchar(50) NOT NULL,
            `total_devices_found` int(11) NOT NULL DEFAULT 0,
            `new_devices_count` int(11) NOT NULL DEFAULT 0,
            `duration_seconds` float NOT NULL DEFAULT 0.0,
            `scan_type` varchar(50) NOT NULL DEFAULT 'ARP / Subnet Scan',
            `triggered_by` varchar(50) NOT NULL DEFAULT 'Admin',
            `status` varchar(20) NOT NULL DEFAULT 'Completed',
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }

    if (!in_array('device_status_history', $tables)) {
        $pdo->exec("CREATE TABLE `device_status_history` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `device_id` int(11) DEFAULT NULL,
            `ip_address` varchar(50) NOT NULL,
            `previous_status` varchar(20) NOT NULL,
            `new_status` varchar(20) NOT NULL,
            `timestamp` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `reason` text DEFAULT NULL,
            PRIMARY KEY (`id`),
            KEY `idx_dev_hist` (`device_id`),
            KEY `idx_ip_hist` (`ip_address`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }

    if (!in_array('ping_history', $tables)) {
        $pdo->exec("CREATE TABLE `ping_history` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `device_id` int(11) DEFAULT NULL,
            `ip_address` varchar(50) NOT NULL,
            `timestamp` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `latency` float DEFAULT 0.0,
            `packet_loss` float DEFAULT 0.0,
            `status` varchar(20) NOT NULL DEFAULT 'Online',
            PRIMARY KEY (`id`),
            KEY `idx_ping_dev` (`device_id`),
            KEY `idx_ping_ip` (`ip_address`),
            KEY `idx_ping_time` (`timestamp`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }

    if (!in_array('network_events', $tables)) {
        $pdo->exec("CREATE TABLE `network_events` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `timestamp` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `event_type` varchar(50) NOT NULL,
            `severity` varchar(20) NOT NULL DEFAULT 'Info',
            `device_id` int(11) DEFAULT NULL,
            `ip_address` varchar(50) NOT NULL,
            `hostname` varchar(100) DEFAULT NULL,
            `message` text NOT NULL,
            `is_read` tinyint(1) NOT NULL DEFAULT 0,
            PRIMARY KEY (`id`),
            KEY `idx_event_type` (`event_type`),
            KEY `idx_event_time` (`timestamp`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }

    if (!in_array('device_notes', $tables)) {
        $pdo->exec("CREATE TABLE `device_notes` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `device_id` int(11) NOT NULL,
            `user_id` int(11) DEFAULT 1,
            `note_text` text NOT NULL,
            `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `idx_notes_dev` (`device_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }

    if (!in_array('device_inventory', $tables)) {
        $pdo->exec("CREATE TABLE `device_inventory` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `device_id` int(11) NOT NULL,
            `asset_tag` varchar(50) DEFAULT NULL,
            `location` varchar(100) DEFAULT NULL,
            `owner` varchar(100) DEFAULT NULL,
            `department` varchar(100) DEFAULT NULL,
            `notes` text DEFAULT NULL,
            `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uk_device_inv` (`device_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }

    if (!in_array('system_logs', $tables)) {
        $pdo->exec("CREATE TABLE `system_logs` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `timestamp` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `user_id` int(11) DEFAULT NULL,
            `username` varchar(50) DEFAULT 'System',
            `action` varchar(100) NOT NULL,
            `ip_address` varchar(50) DEFAULT NULL,
            `details` text DEFAULT NULL,
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }

    if (!in_array('settings', $tables)) {
        $pdo->exec("CREATE TABLE `settings` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `setting_key` varchar(50) NOT NULL,
            `setting_value` text NOT NULL,
            `description` varchar(255) DEFAULT NULL,
            `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uk_setting_key` (`setting_key`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        // Seed default settings
        $pdo->exec("INSERT INTO `settings` (`setting_key`, `setting_value`, `description`) VALUES
            ('ping_interval', '30', 'Continuous ping monitoring interval in seconds'),
            ('auto_scan_enabled', '1', 'Enable automatic periodic network scans'),
            ('auto_scan_interval', '300', 'Periodic network scan interval in seconds'),
            ('alert_new_device', '1', 'Generate alert when new device is detected'),
            ('alert_device_offline', '1', 'Generate alert when known device goes offline'),
            ('default_subnet', 'auto', 'Default subnet to scan (auto for dynamic detection)')");
    }

    if (!in_array('bandwidth_snapshot', $tables)) {
        $pdo->exec("CREATE TABLE `bandwidth_snapshot` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `timestamp` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `bytes_sent` bigint(20) NOT NULL DEFAULT 0,
            `bytes_recv` bigint(20) NOT NULL DEFAULT 0,
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }

    if (!in_array('security_event', $tables)) {
        $pdo->exec("CREATE TABLE `security_event` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `timestamp` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `src_ip` varchar(50) NOT NULL,
            `dest_ip` varchar(50) DEFAULT '192.168.1.1',
            `attack_type` varchar(100) NOT NULL,
            `severity` varchar(20) NOT NULL,
            `description` text DEFAULT NULL,
            `action_taken` varchar(50) NOT NULL DEFAULT 'Logged',
            `confidence` float NOT NULL DEFAULT 0.95,
            `model_used` varchar(50) NOT NULL DEFAULT 'NetSentry IDS',
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }

    if (!in_array('traffic_snapshot', $tables)) {
        $pdo->exec("CREATE TABLE `traffic_snapshot` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `timestamp` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `packets_in` bigint(20) NOT NULL DEFAULT 0,
            `packets_out` bigint(20) NOT NULL DEFAULT 0,
            `tcp_count` int(11) NOT NULL DEFAULT 0,
            `udp_count` int(11) NOT NULL DEFAULT 0,
            `icmp_count` int(11) NOT NULL DEFAULT 0,
            `http_count` int(11) NOT NULL DEFAULT 0,
            `dropped` int(11) NOT NULL DEFAULT 0,
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }
}

// Helper: Check if user is logged in
function is_logged_in() {
    return isset($_SESSION['user_id']);
}

// Helper: Require login redirect
function require_login() {
    if (!is_logged_in()) {
        $isApi = (!empty($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false) 
              || (strpos($_SERVER['REQUEST_URI'] ?? '', '/api/') !== false);
        if ($isApi) {
            http_response_code(401);
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
            exit();
        }
        header("Location: login.php");
        exit();
    }
}

// Helper: Require admin role
function require_admin() {
    require_login();
    if (($_SESSION['user_role'] ?? '') !== 'admin') {
        http_response_code(403);
        die("403 Unauthorized: Admin access required.");
    }
}

// Helper: Get logged-in user array
function get_current_user_data() {
    if (!is_logged_in()) return null;
    $pdo = get_db();
    $stmt = $pdo->prepare("SELECT id, username, email, image_file, role FROM user WHERE id = :id");
    $stmt->execute([':id' => $_SESSION['user_id']]);
    return $stmt->fetch();
}

// Helper: JSON response
function json_response($data, $status = 200) {
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit();
}
