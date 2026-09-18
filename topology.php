<?php
/**
 * NetSentry - Interactive Network Topology Page
 * Section 11: Dynamic visual topology canvas (Internet -> Gateway Router -> LAN Hosts)
 */

$pageTitle = "Network Topology — NetSentry";
require_once __DIR__ . '/header.php';
require_once __DIR__ . '/functions.php';

$pdo = get_db();
$devices = get_subnet_devices($pdo, 60);
$netInfo = get_network_gateway_info();
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
    <div>
        <h3 class="mb-0 text-white fw-bold">
            <i class="bi bi-diagram-3 text-success me-2"></i>Network Topology Map
        </h3>
        <p class="text-muted small mb-0">Visual hierarchical layout of WAN gateway and connected subnet infrastructure</p>
    </div>
    <div class="d-flex gap-2 align-items-center">
        <span class="badge bg-dark border border-secondary text-muted"><i class="bi bi-circle-fill text-success me-1"></i> Online</span>
        <span class="badge bg-dark border border-secondary text-muted"><i class="bi bi-circle-fill text-danger me-1"></i> Offline</span>
        <button class="btn btn-outline-info btn-sm" onclick="renderTopologyCanvas();">
            <i class="bi bi-arrows-fullscreen me-1"></i> Recenter Map
        </button>
        <a href="scanner.php" class="btn btn-warning btn-sm">
            <i class="bi bi-radar me-1"></i> Scan Network
        </a>
    </div>
</div>

<!-- Main Topology Canvas Card -->
<div class="noc-card mb-4">
    <div class="noc-card-header d-flex justify-content-between align-items-center">
        <span><i class="bi bi-bezier2 me-2 text-primary"></i>Live Subnet Architecture</span>
        <span class="small text-muted">Click any host icon to drill into device details</span>
    </div>
    <div class="noc-card-body p-2">
        <div id="topoWrapper" style="height: 520px; background: #0B1120; border-radius: 8px; position: relative; overflow: hidden;">
            <canvas id="mainTopoCanvas" style="width:100%; height:100%; display:block; cursor: pointer;"></canvas>
            
            <!-- Floating Map Info Badge -->
            <div style="position: absolute; bottom: 15px; left: 15px; background: rgba(15, 23, 42, 0.85); border: 1px solid #334155; border-radius: 6px; padding: 8px 12px; font-size: 0.75rem;" class="text-muted">
                <div>Subnet: <strong class="text-white"><?php echo htmlspecialchars($netInfo['subnet']); ?></strong></div>
                <div>Gateway: <strong class="text-success"><?php echo htmlspecialchars($netInfo['gateway_ip']); ?></strong></div>
                <div>Monitored Nodes: <strong class="text-info"><?php echo count($devices); ?></strong></div>
            </div>
        </div>
    </div>
</div>

<!-- Device Quick Summary Grid below topology -->
<div class="row g-3">
    <?php foreach (array_slice($devices, 0, 6) as $d): ?>
        <div class="col-md-4 col-lg-2">
            <a href="device_detail.php?id=<?php echo $d['id']; ?>" class="text-decoration-none">
                <div class="noc-card p-2 text-center h-100 hover-elevate border-secondary">
                    <div class="fs-4 mb-1">
                        <?php
                        $t = $d['device_type'] ?: 'Unknown';
                        if ($t === 'Router') echo '🌐';
                        elseif ($t === 'Smartphone') echo '📱';
                        elseif ($t === 'Printer') echo '🖨️';
                        elseif ($t === 'Laptop') echo '💻';
                        elseif ($t === 'Server') echo '🖥️';
                        else echo '💻';
                        ?>
                    </div>
                    <div class="fw-bold text-white small text-truncate"><?php echo htmlspecialchars($d['hostname'] ?: 'LAN Host'); ?></div>
                    <div class="font-monospace text-info small" style="font-size:0.7rem;"><?php echo htmlspecialchars($d['ip_address']); ?></div>
                    <div class="mt-1">
                        <?php if ($d['status'] === 'Online'): ?>
                            <span class="badge bg-success" style="font-size:0.6rem;">Online</span>
                        <?php else: ?>
                            <span class="badge bg-danger" style="font-size:0.6rem;">Offline</span>
                        <?php endif; ?>
                    </div>
                </div>
            </a>
        </div>
    <?php endforeach; ?>
</div>

<script>
const devicesList = <?php echo json_encode($devices); ?>;
const gatewayIp = <?php echo json_encode($netInfo['gateway_ip']); ?>;

function renderTopologyCanvas() {
    const canvas = document.getElementById('mainTopoCanvas');
    const wrapper = document.getElementById('topoWrapper');
    if (!canvas || !wrapper) return;

    canvas.width = wrapper.clientWidth;
    canvas.height = wrapper.clientHeight;

    const ctx = canvas.getContext('2d');
    const w = canvas.width;
    const h = canvas.height;

    ctx.clearRect(0, 0, w, h);

    // 1. Root WAN Cloud
    const nodes = [
        { id: 'wan', label: 'Internet WAN Cloud', sublabel: 'Public Gateway', x: w / 2, y: 50, color: '#00c2e0', isCloud: true, icon: '🌐' }
    ];

    // 2. Gateway Router
    const gatewayDevice = devicesList.find(d => d.ip_address === gatewayIp || (d.hostname && d.hostname.includes('Gateway')) || d.device_type === 'Router') || {
        id: 'gateway',
        hostname: 'Gateway Router',
        ip_address: gatewayIp,
        status: 'Online',
        device_type: 'Router'
    };

    const isGwOnline = (gatewayDevice.status === 'Online');
    nodes.push({
        id: gatewayDevice.id,
        label: gatewayDevice.hostname || 'Gateway Router',
        sublabel: gatewayDevice.ip_address,
        ip: gatewayDevice.ip_address,
        x: w / 2,
        y: 160,
        color: isGwOnline ? '#00d084' : '#ef4444',
        isGateway: true,
        icon: '🖧'
    });

    // 3. Subnet Devices (Divided in dynamic tiers if many devices)
    const subDevices = devicesList.filter(d => d.ip_address !== gatewayDevice.ip_address);
    const count = subDevices.length;
    const startX = 80;
    const endX = w - 80;
    const availableWidth = endX - startX;

    // Use 2 rows if more than 8 devices
    const rows = count > 8 ? 2 : 1;
    const perRow = Math.ceil(count / rows);

    subDevices.forEach((dev, idx) => {
        const isOnline = (dev.status === 'Online');
        const rowIdx = Math.floor(idx / perRow);
        const colIdx = idx % perRow;
        const rowCount = (rowIdx === 0) ? Math.min(perRow, count) : (count - perRow);
        
        const spacing = rowCount > 1 ? availableWidth / (rowCount - 1) : 0;
        const posX = rowCount === 1 ? w / 2 : startX + colIdx * spacing;
        const posY = rows === 1 ? 360 : (280 + rowIdx * 130);

        let icon = '💻';
        if (dev.device_type === 'Smartphone') icon = '📱';
        else if (dev.device_type === 'Printer') icon = '🖨️';
        else if (dev.device_type === 'Laptop') icon = '💻';
        else if (dev.device_type === 'Server') icon = '🖥️';

        nodes.push({
            id: dev.id,
            label: dev.hostname || dev.ip_address,
            sublabel: dev.ip_address,
            ip: dev.ip_address,
            status: dev.status,
            x: posX,
            y: posY,
            color: isOnline ? '#00d084' : '#ef4444',
            icon: icon
        });
    });

    window.topoNodes = nodes;

    // Draw Links / Connections
    ctx.lineWidth = 2;

    // WAN -> Gateway
    ctx.strokeStyle = '#3b82f6';
    ctx.setLineDash([6, 4]);
    ctx.beginPath();
    ctx.moveTo(nodes[0].x, nodes[0].y + 24);
    ctx.lineTo(nodes[1].x, nodes[1].y - 24);
    ctx.stroke();
    ctx.setLineDash([]);

    // Gateway -> Devices
    nodes.slice(2).forEach(n => {
        ctx.strokeStyle = (n.color === '#00d084') ? 'rgba(0, 208, 132, 0.45)' : 'rgba(239, 68, 68, 0.35)';
        ctx.beginPath();
        ctx.moveTo(nodes[1].x, nodes[1].y + 22);
        ctx.lineTo(n.x, n.y - 20);
        ctx.stroke();
    });

    // Draw Nodes
    nodes.forEach(n => {
        const radius = n.isCloud ? 26 : (n.isGateway ? 22 : 18);

        // Outer Glow Ring
        ctx.beginPath();
        ctx.arc(n.x, n.y, radius + 5, 0, Math.PI * 2);
        ctx.fillStyle = n.color + '22';
        ctx.fill();

        // Node Circle Body
        ctx.beginPath();
        ctx.arc(n.x, n.y, radius, 0, Math.PI * 2);
        ctx.fillStyle = '#0F172A';
        ctx.fill();
        ctx.lineWidth = 2.5;
        ctx.strokeStyle = n.color;
        ctx.stroke();

        // Icon inside circle
        ctx.font = `${radius * 0.9}px sans-serif`;
        ctx.textAlign = 'center';
        ctx.textBaseline = 'middle';
        ctx.fillText(n.icon, n.x, n.y);

        // Labels
        ctx.textBaseline = 'alphabetic';
        ctx.fillStyle = '#f8fafc';
        ctx.font = 'bold 11px Outfit, sans-serif';
        ctx.fillText(n.label.length > 15 ? n.label.substr(0, 13) + '...' : n.label, n.x, n.y + radius + 15);

        if (n.sublabel) {
            ctx.fillStyle = '#94a3b8';
            ctx.font = '10px JetBrains Mono, monospace';
            ctx.fillText(n.sublabel, n.x, n.y + radius + 27);
        }
    });
}

window.addEventListener('load', renderTopologyCanvas);
window.addEventListener('resize', renderTopologyCanvas);

// Interactive Click Listener
const topoCanvas = document.getElementById('mainTopoCanvas');
if (topoCanvas) {
    topoCanvas.addEventListener('click', function(e) {
        const rect = this.getBoundingClientRect();
        const clickX = e.clientX - rect.left;
        const clickY = e.clientY - rect.top;

        if (window.topoNodes) {
            window.topoNodes.forEach(node => {
                if (node.id && node.id !== 'wan') {
                    const radius = node.isGateway ? 22 : 18;
                    const dist = Math.hypot(clickX - node.x, clickY - node.y);
                    if (dist <= radius + 8) {
                        window.location.href = 'device_detail.php?id=' + encodeURIComponent(node.id);
                    }
                }
            });
        }
    });
}
</script>

<?php require_once __DIR__ . '/footer.php'; ?>
