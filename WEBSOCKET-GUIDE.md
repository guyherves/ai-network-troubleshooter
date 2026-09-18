# WebSocket Real-time Updates Guide

## Overview

WebSocket real-time updates provide live, bidirectional communication between the server and connected clients. This eliminates the need for polling and provides instant updates for dashboards, alerts, and IDS events.

**Benefits:**
- 🚀 Real-time updates without page refresh
- 📊 Reduced server load (no polling)
- 💬 Bidirectional communication
- 🔄 Automatic reconnection on disconnect
- 📱 Works on all modern browsers

---

## Architecture

### Server-side (Python/Flask)

The WebSocket server is implemented using `Flask-SocketIO` and `python-socketio`:

```python
# app/websocket.py
from flask_socketio import SocketIO, emit, join_room

socketio = SocketIO(cors_allowed_origins="*")

@socketio.on('connect')
def handle_connect():
    emit('response', {'data': 'Connected'})

@socketio.on('join_dashboard')
def handle_join_dashboard(data):
    join_room(data['room'])
    emit('status', {'msg': 'Joined room'}, room=data['room'])
```

### Client-side (JavaScript)

The WebSocket client is implemented as a JavaScript class:

```javascript
// app/static/js/websocket-client.js
class NetworkIDSWebSocket {
    constructor(url = null) {
        this.socket = io(this.getWebSocketURL());
        this.listeners = {};
        this.attachEventListeners();
    }
    
    joinRoom(room) {
        this.socket.emit('join_dashboard', { room: room });
    }
    
    on(event, callback) {
        if (!this.listeners[event]) {
            this.listeners[event] = [];
        }
        this.listeners[event].push(callback);
    }
}
```

---

## Usage

### 1. Include Libraries in HTML Template

```html
<!-- Include Socket.io library -->
<script src="/socket.io/socket.io.js"></script>

<!-- Include WebSocket client library -->
<script src="/static/js/websocket-client.js"></script>

<!-- Include dashboard real-time updates -->
<script src="/static/js/dashboard-realtime.js"></script>
```

### 2. Initialize WebSocket in Dashboard

```javascript
// Initialize WebSocket client
let ws = new NetworkIDSWebSocket();

// Listen for connection
ws.on('connected', () => {
    console.log('Connected to server');
    ws.joinRoom('dashboard');
});

// Listen for real-time updates
ws.on('new_alert', (alert) => {
    console.log('New alert:', alert);
    updateUI(alert);
});

// Listen for IDS events
ws.on('new_ids_event', (event) => {
    console.log('New IDS event:', event);
    updateUI(event);
});
```

### 3. Request Data from Server

```javascript
// Request dashboard update
ws.requestDashboardUpdate();

// Request alerts
ws.requestAlerts();

// Request IDS events
ws.requestIDSEvents();
```

---

## API Reference

### Client Methods

#### `constructor(url = null)`
Initialize WebSocket client.

```javascript
const ws = new NetworkIDSWebSocket();
// or with custom URL
const ws = new NetworkIDSWebSocket('wss://example.com');
```

#### `joinRoom(room)`
Join a room for updates.

```javascript
ws.joinRoom('dashboard');     // Join dashboard room
ws.joinRoom('monitoring');    // Join monitoring room
ws.joinRoom('analytics');     // Join analytics room
```

#### `leaveRoom()`
Leave current room.

```javascript
ws.leaveRoom();
```

#### `on(event, callback)`
Register event listener.

```javascript
ws.on('new_alert', (data) => {
    console.log('Alert:', data);
});

ws.on('connected', () => {
    console.log('Connected');
});

ws.on('disconnected', () => {
    console.log('Disconnected');
});
```

#### `requestDashboardUpdate()`
Request dashboard update from server.

```javascript
ws.requestDashboardUpdate();
```

#### `requestAlerts()`
Request alerts from server.

```javascript
ws.requestAlerts();
```

#### `requestIDSEvents()`
Request IDS events from server.

```javascript
ws.requestIDSEvents();
```

#### `isConnected()`
Check if WebSocket is connected.

```javascript
if (ws.isConnected()) {
    ws.requestDashboardUpdate();
}
```

#### `close()`
Close WebSocket connection.

```javascript
ws.close();
```

---

## Server Events

### Emitted Events

#### `connect`
Client connects to server.

```python
@socketio.on('connect')
def handle_connect():
    emit('response', {'data': 'Connected'})
```

#### `disconnect`
Client disconnects from server.

```python
@socketio.on('disconnect')
def handle_disconnect():
    logger.info(f"Client disconnected: {request.sid}")
```

#### `join_dashboard`
Client joins a room.

```python
@socketio.on('join_dashboard')
def handle_join_dashboard(data):
    room = data.get('room')
    join_room(room)
    emit('status', {'msg': f'Joined {room}'}, room=room)
```

### Received Events

#### `new_alert`
New alert created.

```python
def emit_alert(alert):
    socketio.emit('new_alert', {
        'id': alert.id,
        'message': alert.message,
        'severity': alert.severity
    }, room='dashboard')
```

#### `new_ids_event`
New IDS event detected.

```python
def emit_ids_event(ids_event):
    socketio.emit('new_ids_event', {
        'src_ip': ids_event.src_ip,
        'event': ids_event.event_type,
        'severity': ids_event.severity_level
    }, room='dashboard')
```

#### `network_update`
Network status update.

```python
def emit_network_update(target_ip, stats):
    socketio.emit('network_update', {
        'target_ip': target_ip,
        'status': stats['status'],
        'latency': stats['latency']
    }, room='monitoring')
```

#### `dashboard_stats`
Dashboard statistics update.

```python
def emit_dashboard_stats(stats):
    socketio.emit('dashboard_stats', stats, room='dashboard')
```

---

## Rooms

Rooms allow grouping of clients for targeted broadcasts.

### Available Rooms

- **`dashboard`** - Dashboard real-time updates
- **`monitoring`** - Network monitoring updates
- **`alerts`** - Alert notifications
- **`analytics`** - Analytics data updates

### Usage

```python
# Emit to specific room
socketio.emit('new_alert', alert_data, room='dashboard')

# Emit to all clients
socketio.emit('broadcast_message', {'msg': 'Hello everyone'})

# Emit to sender only
emit('response', {'msg': 'Hello sender'})
```

---

## Integration Examples

### Example 1: Real-time Alerts

```javascript
let ws = new NetworkIDSWebSocket();

ws.on('connected', () => {
    ws.joinRoom('dashboard');
});

ws.on('new_alert', (alert) => {
    // Add alert to UI
    const alertEl = document.createElement('div');
    alertEl.className = `alert alert-${alert.severity}`;
    alertEl.textContent = alert.message;
    document.getElementById('alerts').appendChild(alertEl);
    
    // Show notification
    if (Notification.permission === 'granted') {
        new Notification('Alert', { body: alert.message });
    }
});
```

### Example 2: Real-time Network Monitoring

```javascript
let ws = new NetworkIDSWebSocket();

ws.on('connected', () => {
    ws.joinRoom('monitoring');
});

ws.on('network_update', (data) => {
    // Update status indicator
    document.getElementById(`status-${data.target_ip}`).textContent = data.status;
    
    // Update latency chart
    updateLatencyChart(data.target_ip, data.latency);
});
```

### Example 3: Real-time IDS Events

```javascript
let ws = new NetworkIDSWebSocket();

ws.on('connected', () => {
    ws.joinRoom('dashboard');
});

ws.on('new_ids_event', (event) => {
    // Add to table
    const row = document.createElement('tr');
    row.innerHTML = `
        <td>${event.src_ip}</td>
        <td>${event.event}</td>
        <td><span class="badge ${event.severity}">${event.severity}</span></td>
    `;
    document.getElementById('events-table').insertBefore(row, document.querySelector('tbody').firstChild);
    
    // Keep only last 10
    while (document.querySelectorAll('tbody tr').length > 10) {
        document.querySelector('tbody tr:last-child').remove();
    }
});
```

---

## Configuration

### Environment Variables

```bash
# Enable WebSocket
SOCKETIO_MESSAGE_QUEUE=redis://localhost:6379  # Optional, for distributed deployments

# Flask settings
FLASK_ENV=production
DEBUG=False
```

### Flask Configuration

```python
# config.py
SOCKETIO_ASYNC_MODE = 'threading'  # or 'eventlet', 'gevent'
SOCKETIO_PING_TIMEOUT = 60
SOCKETIO_PING_INTERVAL = 25
SOCKETIO_CORS_ALLOWED_ORIGINS = "*"
```

---

## Performance Optimization

### 1. Use Rooms for Targeted Broadcasts

Instead of broadcasting to all clients:

```python
# ❌ Bad - broadcasts to everyone
socketio.emit('alert', alert_data)

# ✅ Good - broadcasts only to dashboard viewers
socketio.emit('alert', alert_data, room='dashboard')
```

### 2. Batch Updates

```python
# ❌ Bad - sends many small messages
for alert in recent_alerts:
    emit_alert(alert)

# ✅ Good - sends batched data
socketio.emit('batch_alerts', {'alerts': recent_alerts}, room='dashboard')
```

### 3. Use Connection Pooling for Redis

For production deployments with multiple workers:

```python
# config.py
SOCKETIO_MESSAGE_QUEUE = 'redis://localhost:6379/0'
SOCKETIO_CHANNEL = 'network-ids'
```

### 4. Optimize Event Payload

```python
# ❌ Bad - sends all data
emit_alert(alert.__dict__)

# ✅ Good - sends only necessary data
emit_alert({
    'id': alert.id,
    'message': alert.message,
    'severity': alert.severity
})
```

---

## Troubleshooting

### WebSocket Not Connecting

```javascript
// Check if Socket.io library is loaded
if (typeof io === 'undefined') {
    console.error('Socket.io library not found');
}

// Check browser console for errors
// Check server logs: docker-compose logs -f app
```

### Connection Timeouts

```python
# Increase timeout in config.py
SOCKETIO_PING_TIMEOUT = 120
SOCKETIO_PING_INTERVAL = 30
```

### Events Not Received

```javascript
// Verify you're in the correct room
ws.on('connected', () => {
    console.log('Connected');
    ws.joinRoom('dashboard');
});

// Check browser console for errors
// Check server logs
```

### High Latency

- Enable Redis message queue for distributed deployments
- Reduce event payload size
- Use rooms instead of broadcasting to all clients
- Monitor network bandwidth

---

## Production Deployment

### Docker Compose

WebSocket is already configured in `docker-compose.yml`:

```yaml
app:
  environment:
    SOCKETIO_MESSAGE_QUEUE: redis://redis:6379
```

### Nginx Configuration

The included `nginx.conf` supports WebSocket:

```nginx
location / {
    proxy_pass http://flask_app;
    proxy_http_version 1.1;
    proxy_set_header Upgrade $http_upgrade;
    proxy_set_header Connection "upgrade";
}
```

### Horizontal Scaling

For multiple workers, use Redis as message queue:

```python
# config.py
SOCKETIO_MESSAGE_QUEUE = 'redis://redis-host:6379/0'
SOCKETIO_ASYNC_MODE = 'threading'
```

---

## Testing

### Unit Tests

```python
def test_websocket_connect(socketio_client):
    """Test WebSocket connection"""
    assert socketio_client.is_connected()

def test_join_room(socketio_client):
    """Test joining a room"""
    socketio_client.emit('join_dashboard', {'room': 'dashboard'})
    # Verify join was successful
```

### Integration Tests

```python
def test_alert_emission(socketio_client):
    """Test alert real-time emission"""
    # Create alert
    alert = Alert(message='Test', severity='High')
    db.session.add(alert)
    db.session.commit()
    
    # Emit alert
    from app.websocket import emit_alert
    emit_alert(alert)
    
    # Verify client received it
    data = socketio_client.get_received()
    assert data[0]['args'][0]['message'] == 'Test'
```

---

## References

- Socket.IO Documentation: https://socket.io/docs/
- Flask-SocketIO: https://flask-socketio.readthedocs.io/
- WebSocket Protocol: https://tools.ietf.org/html/rfc6455
- Real-time Web Technologies Guide: https://www.html5rocks.com/en/tutorials/websockets/basics/

---

## Support

For issues or questions:
1. Check browser console for JavaScript errors
2. Check server logs: `docker-compose logs -f app`
3. Verify WebSocket URL in `NetworkIDSWebSocket.getWebSocketURL()`
4. Check Socket.io library is loaded
5. Verify you're joining the correct room

---

**Last Updated:** 2024-01-16  
**Version:** 1.0.0
