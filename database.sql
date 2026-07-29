-- Database creation for AI-Based Network Troubleshooting System
-- You can import this file into phpMyAdmin or MySQL Workbench

CREATE DATABASE IF NOT EXISTS network_troubleshooting;
USE network_troubleshooting;

-- Table structure for table `user`
DROP TABLE IF EXISTS `user`;
CREATE TABLE IF NOT EXISTS `user` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(20) NOT NULL,
  `email` varchar(120) NOT NULL,
  `image_file` varchar(20) NOT NULL DEFAULT 'default.jpg',
  `password` varchar(60) NOT NULL,
  `role` varchar(20) NOT NULL DEFAULT 'admin',
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table structure for table `network_log`
DROP TABLE IF EXISTS `network_log`;
CREATE TABLE IF NOT EXISTS `network_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `timestamp` datetime NOT NULL,
  `target_ip` varchar(50) NOT NULL,
  `latency` float DEFAULT NULL,
  `packet_loss` float DEFAULT NULL,
  `status` varchar(20) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table structure for table `diagnosis_history`
DROP TABLE IF EXISTS `diagnosis_history`;
CREATE TABLE IF NOT EXISTS `diagnosis_history` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `timestamp` datetime NOT NULL,
  `target_ip` varchar(50) NOT NULL,
  `issue_detected` varchar(100) NOT NULL,
  `recommendation` text NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- Sample Data Insertion
-- --------------------------------------------------------

-- 1. Insert a default Admin user (password is 'admin123' hashed using bcrypt)
-- Note: The bcrypt hash below is for 'admin123'. You can change the password later via the app.
INSERT INTO `user` (`id`, `username`, `email`, `image_file`, `password`, `role`) VALUES
(1, 'admin', 'admin@network.local', 'default.jpg', '$2b$12$KkQ/f1/HlK/N.r.0sU7tN.7mG.q5tZtZ1Wqj/5zTqC5sZ/L1Xq0P2', 'admin');

-- 2. Insert sample Network Logs
INSERT INTO `network_log` (`timestamp`, `target_ip`, `latency`, `packet_loss`, `status`) VALUES
(NOW() - INTERVAL 2 HOUR, '192.168.1.1', 12.5, 0.0, 'Online'),
(NOW() - INTERVAL 1 HOUR, '192.168.1.1', 450.0, 5.0, 'Online'),
(NOW(), '192.168.1.100', 9999.0, 100.0, 'Offline');

-- 3. Insert sample Diagnosis History
INSERT INTO `diagnosis_history` (`timestamp`, `target_ip`, `issue_detected`, `recommendation`) VALUES
(NOW() - INTERVAL 1 HOUR, '192.168.1.1', 'Network Congestion / High Bandwidth Usage', 'Consider traffic shaping or QoS to limit bandwidth hogs.'),
(NOW(), '192.168.1.100', 'Device Offline / Unreachable', 'Check physical connection, power, or if the device is turned off.');
