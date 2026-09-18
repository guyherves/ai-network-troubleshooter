<?php
require_once __DIR__ . '/db.php';
require_login();

$user = get_current_user_data();
$currentPage = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NetSentry PHP - <?php echo htmlspecialchars($pageTitle ?? 'NOC Dashboard'); ?></title>
    <meta name="description" content="NetSentry Network Monitoring and Intrusion Detection System">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="app/static/css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <script>
        const savedTheme = localStorage.getItem('noc_theme') || 'dark';
        document.documentElement.setAttribute('data-theme', savedTheme);
    </script>
</head>
<body>

    <!-- ===== SIDEBAR ===== -->
    <aside class="noc-sidebar">
        <div class="sidebar-brand">
            <div class="brand-icon">🌐</div>
            <div class="brand-text">
                NetSentry
                <small>LAN Management & Monitoring</small>
            </div>
        </div>

        <div class="sidebar-nav">
            <div class="sidebar-section-label">Core Operations</div>
            <a href="index.php" class="<?php echo ($currentPage == 'index.php' || $currentPage == 'dashboard.php') ? 'active' : ''; ?>">
                <i class="bi bi-speedometer2 text-primary"></i> Dashboard
            </a>
            <a href="network_devices.php" class="<?php echo ($currentPage == 'network_devices.php' || $currentPage == 'device_detail.php') ? 'active' : ''; ?>">
                <i class="bi bi-hdd-network text-info"></i> Network Devices
            </a>
            <a href="scanner.php" class="<?php echo ($currentPage == 'scanner.php') ? 'active' : ''; ?>">
                <i class="bi bi-radar text-warning"></i> Network Scanner
            </a>
            <a href="topology.php" class="<?php echo ($currentPage == 'topology.php') ? 'active' : ''; ?>">
                <i class="bi bi-diagram-3 text-success"></i> Topology
            </a>
            <a href="monitoring.php" class="<?php echo ($currentPage == 'monitoring.php') ? 'active' : ''; ?>">
                <i class="bi bi-activity text-danger"></i> Monitoring
            </a>

            <div class="sidebar-section-label">Asset & Change Logs</div>
            <a href="inventory.php" class="<?php echo ($currentPage == 'inventory.php') ? 'active' : ''; ?>">
                <i class="bi bi-boxes text-purple"></i> Network Inventory
            </a>
            <a href="events.php" class="<?php echo ($currentPage == 'events.php' || $currentPage == 'security_events.php') ? 'active' : ''; ?>">
                <i class="bi bi-bell-fill text-warning"></i> Network Events
            </a>
            <a href="reports.php" class="<?php echo ($currentPage == 'reports.php' || $currentPage == 'history.php') ? 'active' : ''; ?>">
                <i class="bi bi-file-earmark-bar-graph text-info"></i> Reports
            </a>

            <?php if (($user['role'] ?? '') === 'admin'): ?>
            <div class="sidebar-section-label">Administration</div>
            <a href="users.php" class="<?php echo ($currentPage == 'users.php') ? 'active' : ''; ?>">
                <i class="bi bi-people text-light"></i> Users & Roles
            </a>
            <a href="settings.php" class="<?php echo ($currentPage == 'settings.php') ? 'active' : ''; ?>">
                <i class="bi bi-gear text-secondary"></i> Settings
            </a>
            <?php endif; ?>

            <div class="sidebar-section-label">Session</div>
            <a href="logout.php" class="text-danger">
                <i class="bi bi-box-arrow-right"></i> Logout
            </a>
        </div>

        <div class="sidebar-footer">
            <div class="d-flex align-items-center gap-2">
                <span class="sidebar-status-dot"></span>
                <div>
                    <div style="font-size:0.78rem; color: var(--text-bright); font-weight:600;">
                        <?php echo htmlspecialchars($user['username'] ?? 'Guest'); ?>
                    </div>
                    <div style="font-size:0.68rem; color: var(--text-muted); text-transform: capitalize;">
                        <?php echo htmlspecialchars($user['role'] ?? 'User'); ?>
                    </div>
                </div>
            </div>
        </div>
    </aside>

    <!-- ===== TOP BAR ===== -->
    <header class="noc-topbar">
        <div class="topbar-title">
            <i class="bi bi-circle-fill text-success me-2" style="font-size:0.5rem;"></i>
            <?php echo htmlspecialchars($pageTitle ?? 'Dashboard'); ?>
        </div>
        <div class="topbar-right">
            <span class="topbar-badge"><i class="bi bi-clock me-1"></i> <span id="liveClock"></span></span>
            <button id="themeToggle" class="btn btn-sm btn-link text-muted" style="text-decoration:none;"><i class="bi bi-sun-fill" id="themeIcon"></i></button>

            <!-- USER DROPDOWN -->
            <div class="dropdown">
                <a href="#" class="d-flex align-items-center gap-2 text-decoration-none" data-bs-toggle="dropdown">
                    <img src="app/static/profile_pics/default.jpg" class="topbar-avatar">
                    <div style="text-align:left;">
                        <div style="font-size:0.8rem; color: var(--text-bright); font-weight:600;"><?php echo htmlspecialchars($user['username']); ?></div>
                        <div style="font-size:0.68rem; color: var(--text-muted); text-transform: capitalize;"><?php echo htmlspecialchars($user['role']); ?></div>
                    </div>
                    <i class="bi bi-chevron-down" style="font-size:0.65rem; color: var(--text-muted);"></i>
                </a>
                <ul class="dropdown-menu dropdown-menu-end shadow">
                    <li><a class="dropdown-item" href="profile.php"><i class="bi bi-person-gear me-2"></i> Profile Settings</a></li>
                    <?php if (($user['role'] ?? '') === 'admin'): ?>
                    <li><a class="dropdown-item" href="users.php"><i class="bi bi-people me-2"></i> User Management</a></li>
                    <li><a class="dropdown-item" href="settings.php"><i class="bi bi-gear me-2"></i> System Settings</a></li>
                    <?php endif; ?>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item text-danger" href="logout.php"><i class="bi bi-box-arrow-right me-2"></i> Logout</a></li>
                </ul>
            </div>
        </div>
    </header>

    <main class="noc-content">
