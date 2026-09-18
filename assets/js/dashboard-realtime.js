/**
 * Dashboard Real-time Updates
 * Example integration of WebSocket for live dashboard updates
 */

let webSocketClient;

/**
 * Initialize WebSocket connection for dashboard
 */
function initDashboardWebSocket() {
    // Create WebSocket client
    webSocketClient = new NetworkIDSWebSocket();
    
    // Listen for connection
    webSocketClient.on('connected', () => {
        console.log('Connected to server');
        // Join dashboard room for live updates
        webSocketClient.joinRoom('dashboard');
        // Request initial data
        webSocketClient.requestDashboardUpdate();
    });
    
    // Listen for disconnection
    webSocketClient.on('disconnected', () => {
        console.log('Disconnected from server');
        updateConnectionStatus(false);
    });
    
    // Listen for dashboard updates
    webSocketClient.on('dashboard_update', (data) => {
        console.log('Dashboard update received:', data);
        updateDashboardStats(data);
    });
    
    // Listen for dashboard stats
    webSocketClient.on('dashboard_stats', (data) => {
        console.log('Dashboard stats received:', data);
        updateCharts(data);
    });
    
    // Listen for new alerts
    webSocketClient.on('new_alert', (alert) => {
        console.log('New alert:', alert);
        addAlertToUI(alert);
        showNotification(alert);
    });
    
    // Listen for IDS events
    webSocketClient.on('new_ids_event', (event) => {
        console.log('New IDS event:', event);
        addIDSEventToUI(event);
    });
    
    // Listen for network updates
    webSocketClient.on('network_update', (data) => {
        console.log('Network update:', data);
        updateNetworkStatus(data);
    });
    
    // Listen for errors
    webSocketClient.on('ws_error', (error) => {
        console.error('WebSocket error:', error);
        updateConnectionStatus(false);
    });
}

/**
 * Update connection status indicator
 */
function updateConnectionStatus(connected) {
    const statusEl = document.getElementById('connection-status');
    if (statusEl) {
        if (connected) {
            statusEl.classList.remove('disconnected');
            statusEl.classList.add('connected');
            statusEl.textContent = 'Live';
        } else {
            statusEl.classList.remove('connected');
            statusEl.classList.add('disconnected');
            statusEl.textContent = 'Offline';
        }
    }
}

/**
 * Update dashboard statistics
 */
function updateDashboardStats(data) {
    // Update uptime
    const uptimeEl = document.getElementById('uptime-stat');
    if (uptimeEl && data.uptime !== undefined) {
        uptimeEl.textContent = data.uptime + '%';
    }
    
    // Update threat count
    const threatsEl = document.getElementById('threats-stat');
    if (threatsEl && data.threats !== undefined) {
        threatsEl.textContent = data.threats;
    }
    
    // Update timestamp
    const timestampEl = document.getElementById('last-update');
    if (timestampEl) {
        const time = new Date(data.timestamp).toLocaleTimeString();
        timestampEl.textContent = 'Last updated: ' + time;
    }
}

/**
 * Add alert to UI
 */
function addAlertToUI(alert) {
    const alertsContainer = document.getElementById('alerts-container');
    if (!alertsContainer) return;
    
    const alertEl = document.createElement('div');
    alertEl.className = `alert alert-${alert.severity.toLowerCase()}`;
    alertEl.innerHTML = `
        <strong>${alert.severity}:</strong> ${alert.message}
        <small>${new Date(alert.timestamp).toLocaleTimeString()}</small>
    `;
    
    alertsContainer.insertBefore(alertEl, alertsContainer.firstChild);
    
    // Keep only last 10 alerts
    while (alertsContainer.children.length > 10) {
        alertsContainer.removeChild(alertsContainer.lastChild);
    }
}

/**
 * Add IDS event to UI
 */
function addIDSEventToUI(event) {
    const eventsContainer = document.getElementById('ids-events-container');
    if (!eventsContainer) return;
    
    const eventEl = document.createElement('div');
    eventEl.className = `event event-${event.severity.toLowerCase()}`;
    eventEl.innerHTML = `
        <strong>${event.event}:</strong> ${event.src_ip}:${event.port} (${event.action})
        <small>${new Date(event.timestamp).toLocaleTimeString()}</small>
    `;
    
    eventsContainer.insertBefore(eventEl, eventsContainer.firstChild);
    
    // Keep only last 10 events
    while (eventsContainer.children.length > 10) {
        eventsContainer.removeChild(eventsContainer.lastChild);
    }
}

/**
 * Update network status
 */
function updateNetworkStatus(data) {
    const statusEl = document.getElementById(`status-${data.target_ip}`);
    if (statusEl) {
        statusEl.textContent = data.status;
        statusEl.className = `status status-${data.status.toLowerCase()}`;
    }
    
    const latencyEl = document.getElementById(`latency-${data.target_ip}`);
    if (latencyEl) {
        latencyEl.textContent = data.latency + 'ms';
    }
    
    const lossEl = document.getElementById(`loss-${data.target_ip}`);
    if (lossEl) {
        lossEl.textContent = data.packet_loss + '%';
    }
}

/**
 * Update charts with new data
 */
function updateCharts(data) {
    // This would integrate with your charting library (Chart.js, etc)
    console.log('Updating charts with:', data);
    // Example: updateProtocolChart(data.protocols);
}

/**
 * Show browser notification
 */
function showNotification(alert) {
    if ('Notification' in window && Notification.permission === 'granted') {
        new Notification('Network IDS Alert', {
            body: alert.message,
            icon: '/static/img/icon.png',
            tag: 'network-ids-alert'
        });
    }
}

/**
 * Request notification permission
 */
function requestNotificationPermission() {
    if ('Notification' in window && Notification.permission === 'default') {
        Notification.requestPermission();
    }
}

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    initDashboardWebSocket();
    requestNotificationPermission();
});

// Cleanup on page unload
window.addEventListener('beforeunload', function() {
    if (webSocketClient) {
        webSocketClient.close();
    }
});
