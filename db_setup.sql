-- Database creation for NetSentry: Network Monitoring and Intrusion Prevention System
-- Import this file into phpMyAdmin, AMPPS MySQL, or MySQL Workbench

CREATE DATABASE IF NOT EXISTS network_troubleshooting;
USE network_troubleshooting;

-- Table structure for table `user`
DROP TABLE IF EXISTS `user`;
CREATE TABLE IF NOT EXISTS `user` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(20) NOT NULL,
  `email` varchar(120) NOT NULL,
  `image_file` varchar(20) NOT NULL DEFAULT 'default.jpg',
  `password` varchar(255) NOT NULL,
  `role` varchar(20) NOT NULL DEFAULT 'admin',
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table structure for table `network_log`
DROP TABLE IF EXISTS `network_log`;
CREATE TABLE IF NOT EXISTS `network_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `timestamp` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `target_ip` varchar(50) NOT NULL,
  `latency` float DEFAULT NULL,
  `packet_loss` float DEFAULT NULL,
  `status` varchar(20) NOT NULL,
  `bandwidth_mbps` float DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table structure for table `diagnosis_history`
DROP TABLE IF EXISTS `diagnosis_history`;
CREATE TABLE IF NOT EXISTS `diagnosis_history` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `timestamp` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `target_ip` varchar(50) NOT NULL,
  `issue_detected` varchar(100) NOT NULL,
  `recommendation` text NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table structure for table `watchlist`
DROP TABLE IF EXISTS `watchlist`;
CREATE TABLE IF NOT EXISTS `watchlist` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ip_address` varchar(50) NOT NULL,
  `added_on` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ip_address` (`ip_address`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table structure for table `alert`
DROP TABLE IF EXISTS `alert`;
CREATE TABLE IF NOT EXISTS `alert` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `timestamp` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `severity` varchar(20) NOT NULL,
  `category` varchar(50) NOT NULL,
  `message` text NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'Unresolved',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table structure for table `ids_event`
DROP TABLE IF EXISTS `ids_event`;
CREATE TABLE IF NOT EXISTS `ids_event` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table structure for table `lan_device`
DROP TABLE IF EXISTS `lan_device`;
CREATE TABLE IF NOT EXISTS `lan_device` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ip_address` varchar(50) NOT NULL,
  `mac_address` varchar(50) DEFAULT NULL,
  `hostname` varchar(100) DEFAULT NULL,
  `device_type` varchar(50) NOT NULL DEFAULT 'Workstation',
  `status` varchar(20) NOT NULL DEFAULT 'Online',
  `first_seen` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `last_seen` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `is_monitored` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ip_address` (`ip_address`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table structure for table `security_event`
DROP TABLE IF EXISTS `security_event`;
CREATE TABLE IF NOT EXISTS `security_event` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table structure for table `traffic_snapshot`
DROP TABLE IF EXISTS `traffic_snapshot`;
CREATE TABLE IF NOT EXISTS `traffic_snapshot` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- Sample Data Insertion
-- --------------------------------------------------------

-- Default Admin User (Password is 'admin123' using PHP password_hash / bcrypt)
INSERT INTO `user` (`id`, `username`, `email`, `image_file`, `password`, `role`) VALUES
(1, 'admin', 'admin@network.local', 'default.jpg', '$2y$10$e/18J481xP3R82y9N7Z12u8/R4Y627QdJmB0W6V4B8.2V0Y3R2WqG', 'admin');

-- Sample Network Logs
INSERT INTO `network_log` (`timestamp`, `target_ip`, `latency`, `packet_loss`, `status`, `bandwidth_mbps`) VALUES
(NOW() - INTERVAL 2 HOUR, '192.168.1.1', 12.5, 0.0, 'Online', NULL),
(NOW() - INTERVAL 1 HOUR, '192.168.1.1', 450.0, 5.0, 'Online', NULL),
(NOW(), '192.168.1.100', 9999.0, 100.0, 'Offline', NULL);

-- Sample Rule-Based Diagnosis History
INSERT INTO `diagnosis_history` (`timestamp`, `target_ip`, `issue_detected`, `recommendation`) VALUES
(NOW() - INTERVAL 1 HOUR, '192.168.1.1', 'Network Congestion / High Bandwidth Usage', 'Consider traffic shaping or QoS to limit bandwidth hogs.'),
(NOW(), '192.168.1.100', 'Device Offline / Unreachable', 'Check physical connection, power, or if the device is turned off.');

-- Seed Sample Threat Events
INSERT INTO `security_event` (`timestamp`, `src_ip`, `dest_ip`, `attack_type`, `severity`, `description`, `action_taken`, `confidence`, `model_used`) VALUES
(NOW() - INTERVAL 25 MINUTE, '192.168.1.45', '192.168.1.1', 'Port Scan (SYN Scan)', 'High', 'Multiple TCP SYN packets across ports 21-8080 detected within 5 seconds', 'Blocked', 0.98, 'NetSentry IDS'),
(NOW() - INTERVAL 18 MINUTE, '192.168.1.20', '192.168.1.1', 'DDoS / Syn Flood', 'Critical', 'Abnormal high volume of incomplete TCP handshakes (>1500 pkts/sec)', 'Blocked', 0.99, 'NetSentry IDS'),
(NOW() - INTERVAL 10 MINUTE, '192.168.1.50', '192.168.1.10', 'Suspicious Port Activity', 'Medium', 'Connection established on known Trojan port 4444', 'Logged', 0.92, 'NetSentry IDS'),
(NOW() - INTERVAL 2 MINUTE, '192.168.1.105', '192.168.1.1', 'SSH Brute Force Attempt', 'High', 'Failed authentication threshold exceeded (12 attempts in 30s)', 'Blocked', 0.96, 'NetSentry IDS');
