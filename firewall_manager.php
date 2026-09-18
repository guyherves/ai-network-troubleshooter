<?php
/**
 * NetSentry - PHP Firewall Manager (IPS)
 * Manages native OS firewall rules (Windows Advanced Firewall / Linux iptables)
 * DRY_RUN mode is controlled via settings.json (configurable from Settings page)
 */

/**
 * Check if firewall dry run mode is enabled from settings.json
 */
function is_firewall_dry_run() {
    $settingsFile = __DIR__ . '/settings.json';
    if (file_exists($settingsFile)) {
        $settings = json_decode(file_get_contents($settingsFile), true);
        if (is_array($settings) && isset($settings['firewall_dry_run'])) {
            return (bool)$settings['firewall_dry_run'];
        }
    }
    return true; // Default to dry run for safety
}

function block_ip($ip) {
    if (empty($ip) || $ip === 'Live Interface' || strpos($ip, '127.') === 0 || substr($ip, -2) === '.1' || substr($ip, -4) === '.254') {
        error_log("[WARNING] Skipping firewall block for local/gateway IP: " . $ip);
        return false;
    }

    $dryRun = is_firewall_dry_run();
    error_log("[FIREWALL MANAGER] Attempting to block malicious IP: " . $ip . " (DRY_RUN: " . ($dryRun ? 'true' : 'false') . ")");

    // Log the action to the alert table
    try {
        $pdo = get_db();
        $mode = $dryRun ? '[DRY RUN] ' : '';
        $pdo->prepare("
            INSERT INTO alert (timestamp, severity, category, message, status)
            VALUES (NOW(), 'High', 'Firewall', :msg, 'Unresolved')
        ")->execute([':msg' => "{$mode}Firewall block rule created for IP: {$ip}"]);
    } catch (Exception $e) {
        error_log("Alert log error: " . $e->getMessage());
    }

    if ($dryRun) {
        error_log("[DRY RUN] Would execute firewall block for IP: " . $ip);
        return true;
    }

    $isWindows = (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN');

    if ($isWindows) {
        $cmd = 'netsh advfirewall firewall add rule name="NetSentry_Block_' . escapeshellarg($ip) . '" dir=in action=block remoteip=' . escapeshellarg($ip);
    } else {
        $cmd = 'sudo iptables -A INPUT -s ' . escapeshellarg($ip) . ' -j DROP';
    }

    exec($cmd, $output, $resultCode);
    if ($resultCode === 0) {
        error_log("[FIREWALL MANAGER] Successfully blocked IP: " . $ip);
        return true;
    } else {
        error_log("[FIREWALL MANAGER] Failed to block IP: " . $ip);
        return false;
    }
}

function unblock_ip($ip) {
    if (empty($ip) || strpos($ip, '127.') === 0) {
        return false;
    }

    $dryRun = is_firewall_dry_run();
    error_log("[FIREWALL MANAGER] Attempting to unblock IP: " . $ip . " (DRY_RUN: " . ($dryRun ? 'true' : 'false') . ")");

    // Log the action to the alert table
    try {
        $pdo = get_db();
        $mode = $dryRun ? '[DRY RUN] ' : '';
        $pdo->prepare("
            INSERT INTO alert (timestamp, severity, category, message, status)
            VALUES (NOW(), 'Low', 'Firewall', :msg, 'Unresolved')
        ")->execute([':msg' => "{$mode}Firewall unblock rule removed for IP: {$ip}"]);
    } catch (Exception $e) {
        error_log("Alert log error: " . $e->getMessage());
    }

    if ($dryRun) {
        error_log("[DRY RUN] Would remove firewall rule for IP: " . $ip);
        return true;
    }

    $isWindows = (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN');

    if ($isWindows) {
        $cmd = 'netsh advfirewall firewall delete rule name="NetSentry_Block_' . escapeshellarg($ip) . '"';
    } else {
        $cmd = 'sudo iptables -D INPUT -s ' . escapeshellarg($ip) . ' -j DROP';
    }

    exec($cmd, $output, $resultCode);
    return ($resultCode === 0);
}
