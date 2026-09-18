/**
 * Network IDS Real-time WebSocket Client
 * Provides real-time updates for dashboard, alerts, and IDS events
 */

class NetworkIDSWebSocket {
    constructor(url = null) {
        this.url = url || this.getWebSocketURL();
        this.socket = null;
        this.connected = false;
        this.currentRoom = null;
        this.reconnectAttempts = 0;
        this.maxReconnectAttempts = 5;
        this.reconnectDelay = 1000;
        this.listeners = {};
        
        this.init();
    }
    
    /**
     * Get WebSocket URL based on current location
     */
    getWebSocketURL() {
        const protocol = window.location.protocol === 'https:' ? 'wss:' : 'ws:';
        return `${protocol}//${window.location.host}`;
    }
    
    /**
     * Initialize WebSocket connection
     */
    init() {
        try {
            // Import socket.io library (must be included in HTML)
            if (typeof io === 'undefined') {
                console.error('Socket.io library not loaded. Make sure to include: <script src="/socket.io/socket.io.js"></script>');
                return;
            }
            
            this.socket = io(this.url, {
                reconnection: true,
                reconnectionDelay: this.reconnectDelay,
                reconnectionDelayMax: 5000,
                reconnectionAttempts: this.maxReconnectAttempts
            });
            
            this.attachEventListeners();
        } catch (error) {
            console.error('Failed to initialize WebSocket:', error);
        }
    }
    
    /**
     * Attach event listeners
     */
    attachEventListeners() {
        if (!this.socket) return;
        
        // Connection events
        this.socket.on('connect', () => this.handleConnect());
        this.socket.on('disconnect', () => this.handleDisconnect());
        this.socket.on('error', (error) => this.handleError(error));
        
        // Custom events
        this.socket.on('response', (data) => this.emit('response', data));
        this.socket.on('status', (data) => this.emit('status', data));
        this.socket.on('dashboard_update', (data) => this.emit('dashboard_update', data));
        this.socket.on('dashboard_stats', (data) => this.emit('dashboard_stats', data));
        this.socket.on('alerts_data', (data) => this.emit('alerts_data', data));
        this.socket.on('new_alert', (data) => this.emit('new_alert', data));
        this.socket.on('ids_events', (data) => this.emit('ids_events', data));
        this.socket.on('new_ids_event', (data) => this.emit('new_ids_event', data));
        this.socket.on('network_update', (data) => this.emit('network_update', data));
        this.socket.on('error', (error) => this.emit('error', error));
    }
    
    /**
     * Handle connection
     */
    handleConnect() {
        this.connected = true;
        this.reconnectAttempts = 0;
        console.log('WebSocket connected');
        this.emit('connected');
    }
    
    /**
     * Handle disconnection
     */
    handleDisconnect() {
        this.connected = false;
        console.log('WebSocket disconnected');
        this.emit('disconnected');
    }
    
    /**
     * Handle errors
     */
    handleError(error) {
        console.error('WebSocket error:', error);
        this.emit('ws_error', error);
    }
    
    /**
     * Join a room for updates
     */
    joinRoom(room) {
        if (!this.socket || !this.connected) {
            console.error('Socket not connected');
            return;
        }
        
        this.currentRoom = room;
        this.socket.emit('join_dashboard', { room: room });
        console.log(`Joined room: ${room}`);
    }
    
    /**
     * Leave current room
     */
    leaveRoom() {
        if (!this.socket || !this.currentRoom) return;
        
        const room = this.currentRoom;
        this.socket.emit('leave_dashboard', { room: room });
        this.currentRoom = null;
        console.log(`Left room: ${room}`);
    }
    
    /**
     * Request dashboard update
     */
    requestDashboardUpdate() {
        if (!this.socket || !this.connected) return;
        this.socket.emit('request_dashboard_update');
    }
    
    /**
     * Request alerts
     */
    requestAlerts() {
        if (!this.socket || !this.connected) return;
        this.socket.emit('request_alerts');
    }
    
    /**
     * Request IDS events
     */
    requestIDSEvents() {
        if (!this.socket || !this.connected) return;
        this.socket.emit('request_ids_events');
    }
    
    /**
     * Register event listener
     */
    on(event, callback) {
        if (!this.listeners[event]) {
            this.listeners[event] = [];
        }
        this.listeners[event].push(callback);
    }
    
    /**
     * Emit event to listeners
     */
    emit(event, data) {
        if (!this.listeners[event]) return;
        this.listeners[event].forEach(callback => {
            try {
                callback(data);
            } catch (error) {
                console.error(`Error in listener for ${event}:`, error);
            }
        });
    }
    
    /**
     * Close connection
     */
    close() {
        if (this.socket) {
            this.socket.disconnect();
            this.socket = null;
        }
    }
    
    /**
     * Check if connected
     */
    isConnected() {
        return this.connected && this.socket !== null;
    }
}

// Export for use in modules
if (typeof module !== 'undefined' && module.exports) {
    module.exports = NetworkIDSWebSocket;
}
