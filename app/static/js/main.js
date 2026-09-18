let networkChart;
let serverStatsChart;
const maxDataPoints = 20;

// Toast Helper (NOC themed)
function showToast(message, type = 'primary') {
    const toastEl = document.getElementById('liveToast');
    const toastBody = document.getElementById('toastMessage');
    if(toastEl && toastBody) {
        const colorMap = { primary: '#2563EB', success: '#22C55E', danger: '#EF4444', warning: '#F59E0B', info: '#06B6D4' };
        toastEl.style.background = colorMap[type] || colorMap.primary;
        toastEl.className = 'toast align-items-center text-white border-0';
        toastBody.innerText = message;
        const toast = new bootstrap.Toast(toastEl, { delay: 3000 });
        toast.show();
    }
}

document.addEventListener('DOMContentLoaded', function() {
    const btnMonitor = document.getElementById('btnMonitor');
    if (btnMonitor) {
        btnMonitor.addEventListener('click', runDiagnostics);
    }
    
    // Initialize charts and load data for all users
    initChart();
    initServerStatsChart();
    loadWatchlist();
    loadDevicesData();
    loadRecentAlerts();
    loadDashboardStats();

    // Set up polling intervals for real-time dashboard data (5s updates)
    setInterval(loadDevicesData, 5000);
    setInterval(loadRecentAlerts, 5000);
    setInterval(loadDashboardStats, 5000);
    
    // Theme Toggle Logic
    const themeBtn = document.getElementById('themeToggle');
    const themeIcon = document.getElementById('themeIcon');
    if (themeBtn && themeIcon) {
        const currentTheme = document.documentElement.getAttribute('data-theme');
        if (currentTheme === 'light') {
            themeIcon.className = 'bi bi-moon-fill';
        } else {
            themeIcon.className = 'bi bi-sun-fill';
        }
        
        themeBtn.addEventListener('click', () => {
            let theme = document.documentElement.getAttribute('data-theme');
            if (theme === 'dark') {
                document.documentElement.setAttribute('data-theme', 'light');
                localStorage.setItem('noc_theme', 'light');
                themeIcon.className = 'bi bi-moon-fill';
            } else {
                document.documentElement.setAttribute('data-theme', 'dark');
                localStorage.setItem('noc_theme', 'dark');
                themeIcon.className = 'bi bi-sun-fill';
            }
            
            // Trigger chart update if we want colors to redraw, though ChartJS 
            // won't automatically recolor unless we force a rebuild, 
            // but for now it's okay.
        });
    }

    // Initialize WebSockets for real-time pushing
    if (typeof NetworkIDSWebSocket !== 'undefined') {
        try {
            const socketClient = new NetworkIDSWebSocket();
            socketClient.on('connected', () => {
                socketClient.joinRoom('dashboard');
                console.log("Joined WebSocket dashboard room.");
            });
            socketClient.on('new_alert', (alert) => {
                console.log("Socket alert received:", alert);
                loadRecentAlerts();
                showToast(`[ALERT] ${alert.message}`, alert.severity === 'High' ? 'danger' : 'warning');
                if ('Notification' in window && Notification.permission === 'granted') {
                    new Notification('NetSentry Security Alert', { body: alert.message });
                }
            });
            socketClient.on('new_ids_event', (event) => {
                console.log("Socket IDS event received:", event);
                loadDashboardStats();
                if (typeof fetchIdsEvents === 'function') {
                    fetchIdsEvents();
                }
            });
        } catch (e) {
            console.warn("Failed to hook socket client in main.js:", e);
        }
    }
    
    // Request notification permission
    if ('Notification' in window && Notification.permission === 'default') {
        Notification.requestPermission();
    }
});

async function initChart() {
    const ctx = document.getElementById('networkChart');
    if (!ctx) return;
    networkChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: [],
            datasets: [{
                label: 'Latency (ms)',
                data: [],
                borderColor: '#2563EB',
                backgroundColor: 'rgba(37, 99, 235, 0.15)',
                borderWidth: 2, fill: true, tension: 0.3, pointRadius: 3, pointBackgroundColor: '#2563EB'
            }, {
                label: 'Packet Loss (%)',
                data: [],
                borderColor: '#EF4444',
                backgroundColor: 'rgba(239, 68, 68, 0.1)',
                borderWidth: 2, fill: true, tension: 0.3, pointRadius: 3, pointBackgroundColor: '#EF4444'
            }]
        },
        options: {
            responsive: true,
            plugins: { legend: { labels: { color: '#94A3B8', boxWidth: 12, font: { size: 11 } } } },
            scales: {
                y: { beginAtZero: true, ticks: { color: '#94A3B8' }, grid: { color: 'rgba(45,58,82,0.5)' } },
                x: { ticks: { color: '#94A3B8' }, grid: { color: 'rgba(45,58,82,0.5)' } }
            }
        }
    });
    updateChart();
}

async function initServerStatsChart() {
    const ctx = document.getElementById('serverStatsChart');
    if (!ctx) return;
    serverStatsChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: Array(maxDataPoints).fill(''),
            datasets: [{
                label: 'CPU Usage (%)',
                data: Array(maxDataPoints).fill(0),
                borderColor: '#22C55E',
                backgroundColor: 'rgba(34, 197, 94, 0.1)',
                borderWidth: 2, fill: true, tension: 0.4, pointRadius: 0
            }, {
                label: 'RAM Usage (%)',
                data: Array(maxDataPoints).fill(0),
                borderColor: '#F59E0B',
                backgroundColor: 'rgba(245, 158, 11, 0.1)',
                borderWidth: 2, fill: true, tension: 0.4, pointRadius: 0
            }]
        },
        options: {
            responsive: true,
            animation: false,
            plugins: { legend: { labels: { color: '#94A3B8', boxWidth: 12, font: { size: 11 } } } },
            scales: {
                y: { beginAtZero: true, max: 100, ticks: { color: '#94A3B8' }, grid: { color: 'rgba(45,58,82,0.5)' } },
                x: { display: false }
            }
        }
    });
    setInterval(fetchServerStats, 2000);
}

async function fetchServerStats() {
    if (!serverStatsChart) return;
    try {
        const response = await fetch('/api/server_stats');
        const data = await response.json();
        
        // Shift data
        serverStatsChart.data.datasets[0].data.shift();
        serverStatsChart.data.datasets[0].data.push(data.cpu);
        
        serverStatsChart.data.datasets[1].data.shift();
        serverStatsChart.data.datasets[1].data.push(data.ram);
        
        serverStatsChart.update();
    } catch (e) {
        console.error("Live stats error:", e);
    }
}

async function updateChart() {
    try {
        const response = await fetch('/api/chart_data');
        const data = await response.json();
        
        if (networkChart) {
            networkChart.data.labels = data.labels;
            networkChart.data.datasets[0].data = data.latency;
            networkChart.data.datasets[1].data = data.packet_loss;
            networkChart.update();
        }
    } catch (error) {
        console.error("Could not fetch chart data", error);
    }
}

async function runDiagnostics() {
    const targetIp = document.getElementById('targetIp').value;
    const btn = document.getElementById('btnMonitor');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Analyzing...';
    document.getElementById('loadingIndicator').classList.remove('d-none');
    try {
        const response = await fetch('/monitor_api', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ target_ip: targetIp })
        });
        const data = await response.json();

        // Update KPI cards safely
        const valLatencyEl = document.getElementById('valLatency');
        if (valLatencyEl) {
            valLatencyEl.innerText = data.latency === 'Timeout' ? 'Timeout' : `${data.latency} ms`;
        }
        const fLatencyEl = document.getElementById('f-latency');
        if (fLatencyEl) {
            fLatencyEl.innerText = data.latency === 'Timeout' ? 'Timeout' : `${data.latency} ms`;
        }

        // Update Rule Diagnostics Panel
        const issueEl = document.getElementById('aiIssue');
        const recEl   = document.getElementById('aiRecommendation');
        const riskEl  = document.getElementById('aiRiskLevel');
        const ringEl  = document.getElementById('threatRing');
        const confEl  = document.getElementById('aiConfidence');
        const barEl   = document.getElementById('confidenceBar');

        if (issueEl) issueEl.innerText = data.ai_issue;
        if (recEl)   recEl.innerText   = data.ai_recommendation;

        // Determine risk level
        let risk = 'Low', riskColor = '#22C55E', ringClass = 'ring-low', threatPct = '18%';
        if (data.ai_issue === 'Device Offline / Unreachable') {
            risk = 'High'; riskColor = '#EF4444'; ringClass = 'ring-high'; threatPct = '85%';
        } else if (data.ai_issue !== 'Normal') {
            risk = 'Medium'; riskColor = '#F59E0B'; ringClass = 'ring-medium'; threatPct = '42%';
        }
        if (riskEl)  riskEl.innerHTML = `<span style="color:${riskColor}">● ${risk}</span>`;
        if (ringEl)  { ringEl.className = `threat-level-ring ${ringClass}`; ringEl.innerText = threatPct; }
        if (confEl)  confEl.innerText = '100%';
        if (barEl)   barEl.style.width = '100%';
        if (document.getElementById('kpi-threat')) document.getElementById('kpi-threat').innerText = threatPct;

        // Update alerts feed
        const newAlert = {
            msg: data.ai_issue === 'Normal' ? 'Diagnostics OK — No Issues' : data.ai_issue,
            time: new Date().toLocaleTimeString(),
            sev: data.ai_issue === 'Device Offline / Unreachable' ? 'sev-high' : (data.ai_issue === 'Normal' ? 'sev-low' : 'sev-medium'),
            sevLabel: data.ai_issue === 'Device Offline / Unreachable' ? 'HIGH' : (data.ai_issue === 'Normal' ? 'OK' : 'MED')
        };
        const feed = document.getElementById('alertsFeed');
        if(feed) {
            feed.insertAdjacentHTML('afterbegin', `
                <div class="alert-item">
                    <span class="alert-severity ${newAlert.sev}">${newAlert.sevLabel}</span>
                    <div>
                        <div class="alert-msg">${newAlert.msg}</div>
                        <div class="alert-time"><i class="bi bi-clock me-1"></i>${newAlert.time}</div>
                    </div>
                </div>`);
        }

        updateChart();
        showToast('Diagnostics complete for ' + targetIp, 'success');
    } catch (error) {
        showToast('Error: Could not connect to server.', 'danger');
    } finally {
        document.getElementById('loadingIndicator').classList.add('d-none');
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-play-fill"></i> Run Diagnostics';
    }
}



async function scanNetwork() {
    const btn = document.getElementById('btnScanNetwork');
    const loading = document.getElementById('discoverLoading');
    const table = document.getElementById('discoveryTable');
    const tbody = document.getElementById('discoveryResults');
    
    btn.disabled = true;
    table.classList.add('d-none');
    loading.classList.remove('d-none');
    tbody.innerHTML = '';
    
    try {
        const response = await fetch('/api/scan_network');
        const data = await response.json();
        
        if (data.length === 0) {
            tbody.innerHTML = '<tr><td colspan="3" class="text-center text-muted">No devices found.</td></tr>';
        } else {
            data.forEach(device => {
                tbody.innerHTML += `
                    <tr>
                        <td><strong>${device.ip}</strong></td>
                        <td><span class="badge bg-secondary">${device.mac}</span></td>
                        <td>
                            <button class="btn btn-sm btn-outline-primary" onclick="selectDeviceForTroubleshoot('${device.ip}')">
                                Troubleshoot
                            </button>
                        </td>
                    </tr>
                `;
            });
        }
        table.classList.remove('d-none');
    } catch (error) {
        alert('Failed to scan network');
    } finally {
        btn.disabled = false;
        loading.classList.add('d-none');
    }
}

function selectDeviceForTroubleshoot(ip) {
    document.getElementById('targetIp').value = ip;
    const modal = bootstrap.Modal.getInstance(document.getElementById('networkToolsModal'));
    if (modal) {
        modal.hide();
    }
}

async function runPortScan() {
    const targetIp = document.getElementById('portScanIp').value;
    if (!targetIp) return;
    
    const btn = document.getElementById('btnPortScan');
    const loading = document.getElementById('portScanLoading');
    const resultsPanel = document.getElementById('portScanResults');
    
    btn.disabled = true;
    resultsPanel.classList.add('d-none');
    loading.classList.remove('d-none');
    resultsPanel.innerHTML = '';
    
    try {
        const response = await fetch('/api/port_scan', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ target_ip: targetIp })
        });
        const data = await response.json();
        
        for (const [port, status] of Object.entries(data)) {
            const badgeClass = status === 'Open' ? 'bg-success' : 'bg-danger';
            resultsPanel.innerHTML += `
                <div class="col-md-4 mb-3">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body text-center">
                            <h5 class="card-title mb-2">Port ${port}</h5>
                            <span class="badge ${badgeClass} p-2 px-3">${status}</span>
                        </div>
                    </div>
                </div>
            `;
        }
        resultsPanel.classList.remove('d-none');
    } catch (error) {
        alert('Failed to scan ports');
    } finally {
        btn.disabled = false;
        loading.classList.add('d-none');
    }
}

// --- Ultimate Features ---
async function runSpeedTest() {
    const btn = document.getElementById('btnSpeedTest');
    const resultsDiv = document.getElementById('speedTestResults');
    
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Testing Bandwidth...';
    
    try {
        const res = await fetch('/api/speedtest');
        const data = await res.json();
        
        if(data.status === 'success') {
            document.getElementById('stPing').innerText = data.ping;
            document.getElementById('stDownload').innerText = data.download;
            document.getElementById('stUpload').innerText = data.upload;
            resultsDiv.style.display = 'block';
            showToast('Speed test completed successfully!', 'success');
        } else {
            showToast('Speed test failed: ' + data.message, 'danger');
        }
    } catch (e) {
        showToast('Speed test error.', 'danger');
    }
    
    btn.disabled = false;
    btn.innerHTML = '<i class="bi bi-play-circle"></i> Run Speed Test';
}

async function loadWatchlist() {
    try {
        const res = await fetch('/api/watchlist');
        const data = await res.json();
        
        const list = document.getElementById('watchlistList');
        if(!list) return;
        
        list.innerHTML = '';
        data.forEach(item => {
            const li = document.createElement('li');
            li.className = 'list-group-item d-flex justify-content-between align-items-center';
            li.innerHTML = `
                <span><i class="bi bi-hdd-network"></i> ${item.ip}</span>
                <button class="btn btn-sm btn-outline-danger" onclick="removeFromWatchlist('${item.ip}')">
                    <i class="bi bi-x"></i>
                </button>
            `;
            list.appendChild(li);
        });
    } catch(e) {}
}

async function addToWatchlist() {
    const ip = document.getElementById('watchlistIp').value;
    if(!ip) return;
    
    try {
        await fetch('/api/watchlist', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ ip: ip })
        });
        document.getElementById('watchlistIp').value = '';
        showToast('IP added to watchlist.', 'success');
        loadWatchlist();
    } catch(e) {
        showToast('Failed to add IP.', 'danger');
    }
}

async function removeFromWatchlist(ip) {
    try {
        await fetch('/api/watchlist', {
            method: 'DELETE',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ ip: ip })
        });
        showToast('IP removed from watchlist.', 'success');
        loadWatchlist();
    } catch(e) {
        showToast('Failed to remove IP.', 'danger');
    }
}

// --- Devices API Integration ---
async function loadDevicesData() {
    try {
        const res = await fetch('/api/devices');
        const data = await res.json();
        
        // Update KPI Cards
        const total = data.devices.length;
        const online = data.devices.filter(d => d.status.toLowerCase() !== 'offline').length;
        const offline = total - online;
        
        if (document.getElementById('kpi-total')) document.getElementById('kpi-total').innerText = total;
        if (document.getElementById('kpi-online')) document.getElementById('kpi-online').innerText = online;
        if (document.getElementById('kpi-offline')) document.getElementById('kpi-offline').innerText = offline;
        
        // Update Footer and Badges
        if (document.getElementById('f-devices')) document.getElementById('f-devices').innerText = total;
        if (document.getElementById('totalDeviceBadge')) document.getElementById('totalDeviceBadge').innerText = total + ' devices';
        
        // Update Connected Devices Table
        const tbody = document.getElementById('devicesTableBody');
        if (tbody) {
            tbody.innerHTML = '';
            data.devices.forEach(device => {
                const tr = document.createElement('tr');
                const badgeStatusClass = device.status.toLowerCase() === 'offline' ? 'status-offline' : (device.status.toLowerCase() === 'high latency' ? 'status-warning' : 'status-online');
                const badgeTypeClass = device.color_class === 'danger' ? 'status-offline' : (device.color_class === 'warning' ? 'status-warning' : 'status-online');
                
                tr.innerHTML = `
                    <td><i class="bi ${device.icon} me-2 text-${device.color_class}"></i>${device.hostname}</td>
                    <td class="mono">${device.ip}</td>
                    <td class="mono">${device.mac}</td>
                    <td><span class="status-badge ${badgeTypeClass}"><span class="status-dot"></span> ${device.type}</span></td>
                    <td><span class="status-badge ${badgeStatusClass}"><span class="status-dot"></span> ${device.status}</span></td>
                    <td><button class="btn-noc-outline" style="padding:3px 10px; font-size:0.75rem;" onclick="setTarget('${device.ip}')">Diagnose</button></td>
                `;
                tbody.appendChild(tr);
            });
        }
        
        // Update Device Type Chart if it exists
        if (window.deviceTypeChart) {
            window.deviceTypeChart.data.datasets[0].data = [
                data.metrics.workstations,
                data.metrics.mobile,
                data.metrics.printers,
                data.metrics.unclassified
            ];
            // Update labels to match new categories returned by metrics
            window.deviceTypeChart.data.labels = ['Workstation', 'Mobile', 'Printer/IoT', 'Unknown'];
            window.deviceTypeChart.update();
        }
        
        // Update Network Topology Map
        if (typeof window.refreshTopology === 'function') {
            window.refreshTopology(data.devices);
        }
        
    } catch(e) {
        console.error("Failed to load devices data:", e);
    }
}

// --- Alerts API Integration ---
async function loadRecentAlerts() {
    try {
        const res = await fetch('/api/alerts/recent');
        const data = await res.json();
        
        // Update KPI
        if (document.getElementById('kpi-alerts')) {
            document.getElementById('kpi-alerts').innerText = data.unresolved_count;
        }
        
        // Update header badge
        if (document.getElementById('alertCount')) {
            document.getElementById('alertCount').innerText = `${data.unresolved_count} active`;
            if (data.unresolved_count > 0) {
                document.getElementById('alertCount').className = 'status-badge status-warning';
            } else {
                document.getElementById('alertCount').className = 'status-badge status-online';
            }
        }
        
        // Populate feed
        const feed = document.getElementById('alertsFeed');
        if (feed) {
            feed.innerHTML = '';
            if (data.alerts.length === 0) {
                feed.innerHTML = '<div class="text-center py-3 text-muted" style="font-size: 0.85rem;">No recent alerts.</div>';
            } else {
                data.alerts.forEach(alert => {
                    let sevClass = 'sev-low';
                    let sevLabel = 'LOW';
                    
                    if (alert.severity === 'High') {
                        sevClass = 'sev-high';
                        sevLabel = 'HIGH';
                    } else if (alert.severity === 'Medium') {
                        sevClass = 'sev-medium';
                        sevLabel = 'MED';
                    }
                    
                    feed.innerHTML += `
                        <div class="alert-item">
                            <span class="alert-severity ${sevClass}">${sevLabel}</span>
                            <div>
                                <div class="alert-msg">${alert.message}</div>
                                <div class="alert-time"><i class="bi bi-clock me-1"></i>${alert.time}</div>
                            </div>
                        </div>
                    `;
                });
            }
        }
        
    } catch (e) {
        console.error("Failed to load alerts:", e);
        const feed = document.getElementById('alertsFeed');
        if (feed) feed.innerHTML = '<div class="text-center py-3 text-danger" style="font-size: 0.85rem;">Error loading alerts.</div>';
    }
}

// --- Dashboard Stats API Integration ---
async function loadDashboardStats() {
    try {
        const res = await fetch('/api/dashboard_stats');
        const data = await res.json();
        
        // Update Protocol Chart
        if (window.protocolChart) {
            window.protocolChart.data.labels = data.protocols.labels;
            window.protocolChart.data.datasets[0].data = data.protocols.data;
            window.protocolChart.update();
        }
        
        // Update Footer and Uptime KPI
        if (document.getElementById('f-packets')) {
            document.getElementById('f-packets').innerText = data.packets_today.toLocaleString();
        }
        if (document.getElementById('f-threats')) {
            document.getElementById('f-threats').innerText = data.threats_blocked.toLocaleString();
        }
        if (document.getElementById('kpi-uptime')) {
            document.getElementById('kpi-uptime').innerText = `${data.uptime}%`;
        }
        
        // Update Latency, Bandwidth, Threats, Alerts dynamically
        if (document.getElementById('valLatency')) {
            document.getElementById('valLatency').innerText = data.latency > 0 ? `${data.latency} ms` : '-- ms';
        }
        if (document.getElementById('kpi-bandwidth')) {
            document.getElementById('kpi-bandwidth').innerText = `${data.bandwidth} Mbps`;
        }
        if (document.getElementById('kpi-threat')) {
            document.getElementById('kpi-threat').innerText = `${data.threat_score}%`;
        }
        if (document.getElementById('kpi-alerts')) {
            document.getElementById('kpi-alerts').innerText = data.active_alerts;
        }
        
        // Update Ring and Risk level indicators
        const threatRing = document.getElementById('threatRing');
        if (threatRing) {
            threatRing.innerText = `${data.threat_score}%`;
            threatRing.className = `threat-level-ring ring-${data.threat_score >= 50 ? 'high' : (data.threat_score >= 15 ? 'medium' : 'low')}`;
        }
        if (document.getElementById('aiRiskLevel')) {
            document.getElementById('aiRiskLevel').innerText = data.risk_level;
        }
    } catch (e) {
        console.error("Failed to load dashboard stats:", e);
    }
}
