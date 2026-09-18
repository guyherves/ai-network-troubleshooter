<?php
/**
 * NetSentry PHP API - Composite Network Health Score Calculation Endpoint
 */

require_once __DIR__ . '/../db.php';
require_login();

$pdo = get_db();

try {
    // 1. Device Availability (Max 25 pts)
    $stmtOn = $pdo->query("SELECT COUNT(*) FROM lan_device WHERE status = 'Online'");
    $online = (int)$stmtOn->fetchColumn();

    $stmtTot = $pdo->query("SELECT COUNT(*) FROM lan_device");
    $totalDevices = (int)$stmtTot->fetchColumn();

    $availRatio = ($totalDevices > 0) ? ($online / $totalDevices) : 1;
    $availScore = round($availRatio * 25, 1);

    // 2. Network Latency Score (Max 20 pts)
    $stmtLat = $pdo->query("SELECT AVG(latency) FROM network_log WHERE latency < 9000 AND latency > 0 AND timestamp >= NOW() - INTERVAL 1 HOUR");
    $avgLat = $stmtLat->fetchColumn();
    $avgLat = ($avgLat !== false && $avgLat !== null) ? (float)$avgLat : 15.0;

    if ($avgLat <= 20) {
        $latScore = 20;
    } elseif ($avgLat <= 50) {
        $latScore = 17;
    } elseif ($avgLat <= 100) {
        $latScore = 12;
    } elseif ($avgLat <= 200) {
        $latScore = 7;
    } else {
        $latScore = 2;
    }

    // 3. Packet Loss Score (Max 15 pts)
    $stmtLoss = $pdo->query("SELECT AVG(packet_loss) FROM network_log WHERE timestamp >= NOW() - INTERVAL 1 HOUR");
    $avgLoss = $stmtLoss->fetchColumn();
    $avgLoss = ($avgLoss !== false && $avgLoss !== null) ? (float)$avgLoss : 0.0;

    if ($avgLoss == 0) {
        $lossScore = 15;
    } elseif ($avgLoss <= 2) {
        $lossScore = 10;
    } elseif ($avgLoss <= 5) {
        $lossScore = 5;
    } else {
        $lossScore = 0;
    }

    // 4. Bandwidth Health Score (Max 15 pts)
    $stmtBw = $pdo->query("SELECT COUNT(*) FROM network_log WHERE bandwidth_mbps IS NOT NULL AND bandwidth_mbps > 0");
    $bwCount = (int)$stmtBw->fetchColumn();
    $bwScore = ($bwCount > 0) ? 15 : 12; // 12 baseline

    // 5. Threat Security Score (Max 15 pts)
    $stmtCrit = $pdo->query("SELECT COUNT(*) FROM security_event WHERE severity = 'Critical' AND timestamp >= NOW() - INTERVAL 24 HOUR");
    $critCount = (int)$stmtCrit->fetchColumn();

    $secScore = max(0, 15 - ($critCount * 5));

    // 6. Firewall & Policy Protection Score (Max 10 pts)
    $fwScore = 10;

    // Total score
    $totalScore = round($availScore + $latScore + $lossScore + $bwScore + $secScore + $fwScore);
    $totalScore = min(100, max(0, $totalScore));

    // Determine Letter Grade & Color
    if ($totalScore >= 95) {
        $grade = 'A+'; $color = '#00d084'; $statusText = 'Optimal Network Health';
    } elseif ($totalScore >= 88) {
        $grade = 'A'; $color = '#00d084'; $statusText = 'Excellent Network Health';
    } elseif ($totalScore >= 80) {
        $grade = 'A-'; $color = '#10b981'; $statusText = 'Good Network Health';
    } elseif ($totalScore >= 70) {
        $grade = 'B'; $color = '#f59e0b'; $statusText = 'Moderate Health / Minor Issues';
    } elseif ($totalScore >= 60) {
        $grade = 'C'; $color = '#f97316'; $statusText = 'Degraded Network Performance';
    } else {
        $grade = 'F'; $color = '#ef4444'; $statusText = 'Critical Network Threats / Downtime';
    }

    json_response([
        'status'       => 'success',
        'health_score' => $totalScore,
        'grade'        => $grade,
        'color'        => $color,
        'status_text'  => $statusText,
        'breakdown'    => [
            'device_availability' => ['score' => $availScore, 'max' => 25, 'label' => 'Device Availability (' . $online . '/' . $totalDevices . ' online)'],
            'latency'             => ['score' => $latScore, 'max' => 20, 'label' => 'Latency (' . round($avgLat, 1) . ' ms avg)'],
            'packet_loss'         => ['score' => $lossScore, 'max' => 15, 'label' => 'Packet Loss (' . round($avgLoss, 1) . '% avg)'],
            'bandwidth'           => ['score' => $bwScore, 'max' => 15, 'label' => 'Bandwidth Stability'],
            'security_posture'    => ['score' => $secScore, 'max' => 15, 'label' => 'Security Posture (' . $critCount . ' critical threats)'],
            'firewall_status'     => ['score' => $fwScore, 'max' => 10, 'label' => 'Firewall & IPS Defense Active']
        ]
    ]);
} catch (Exception $e) {
    json_response(['status' => 'error', 'message' => $e->getMessage()], 500);
}
