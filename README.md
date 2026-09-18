# NetSentry: Network Monitoring and Intrusion Prevention System

![Python](https://img.shields.io/badge/Python-3.x-blue?style=for-the-badge&logo=python&logoColor=white)
![Flask](https://img.shields.io/badge/Flask-Web%20Framework-black?style=for-the-badge&logo=flask&logoColor=white)
![Bootstrap](https://img.shields.io/badge/Bootstrap-5-purple?style=for-the-badge&logo=bootstrap&logoColor=white)
![Scapy](https://img.shields.io/badge/Scapy-Packet%20Sniffer-green?style=for-the-badge&logo=wireshark&logoColor=white)

> A rule-based Network Operations Center (NOC) and Intrusion Prevention System (IPS). Built with Flask, it uses `scapy` to capture live network packets and a deterministic rules engine to autonomously detect anomalies like DDoS attacks, Port Scans, and signature-based L7 threats. It then automatically blocks malicious IP addresses using the host's native OS firewall.

## ✨ Features

- **Live Packet Sniffing**: Uses `scapy` in a background thread to continuously monitor all incoming and outgoing network traffic just like Wireshark.
- **Rule-Based Intrusion Detection**: Inspects packet telemetry (byte volume, TCP/UDP ratios, port ranges) against predefined security policies to instantly classify DDoS, Port Scans, and suspicious connection activities.
- **Deep Packet Inspection (DPI)**: Scans packet payloads for SQL Injection (SQLi) and Cross-Site Scripting (XSS) signatures.
- **Automated Threat Response (IPS)**: When an attack is detected, the Firewall Manager identifies the malicious IP and automatically executes `netsh advfirewall` (Windows) or `iptables` (Linux) commands to drop or block their connection.
- **Persistent Database**: All metrics, diagnostics history, and intrusion events are logged to a local SQLite/MySQL database via `Flask-SQLAlchemy`.
- **Premium Admin Dashboard**: A clean, responsive Bootstrap 5 interface with dark/light modes and glassmorphism styling to view live bandwidth charts, KPIs, and recent threat alerts.
- 🚀 **WebSocket Real-time Updates**: Live dashboards with zero-latency alerts, IDS events, and network statistics using Socket.IO
- 🔐 **Production-Ready Security**: Input validation, rate limiting, CSRF protection, security headers, structured logging, and comprehensive error handling
- 📦 **Docker Deployment**: Multi-service orchestration with MySQL, Flask, and Nginx with health checks
- 📚 **API Documentation**: Interactive Swagger/OpenAPI documentation at `/apidocs`
- 🧪 **Comprehensive Testing**: 150+ test cases covering routing, websocket, database, and auth security.
- 📊 **Structured Logging**: JSON-formatted logs with audit trail for security events
- 🔄 **Database Migrations**: Version-controlled schema changes with Alembic/Flask-Migrate

## 🛠️ Tech Stack

- **Backend**: Python 3, Flask, SQLAlchemy
- **Network Telemetry & Sniffing**: `scapy`, `ping3`, `psutil`
- **Security Action**: Windows Advanced Firewall / Linux UFW
- **Frontend**: HTML5, CSS3, Bootstrap 5, Chart.js
- **Database**: SQLite (Configurable to MySQL)
- **Real-time**: Flask-SocketIO

## Installation & Setup

### Quick Start (Development)

1. **Clone the repository** (or copy the project files to your local machine).
2. **Install Python Requirements**:
   ```bash
   pip install -r requirements.txt
   ```
3. **Configure Environment**:
   ```bash
   cp .env.example .env
   # Edit .env with your database credentials
   ```
4. **Start the Application**:
   ```bash
   python run.py
   ```
5. **Access the Dashboard**:
   Open your web browser and navigate to `http://localhost:5000`. Register an administrator account and log in to start running network diagnostics!

### Production Deployment (Docker)

For production deployment with all security hardening, database migrations, and WebSocket support:

```bash
# 1. Configure environment
cp .env.example .env
nano .env  # Edit with production settings

# 2. Start all services (MySQL, Flask, Nginx)
docker-compose up -d

# 3. Initialize database
docker-compose exec app python manage.py db upgrade
docker-compose exec app python manage.py init_db

# 4. View logs
docker-compose logs -f app
```

**See `DEPLOYMENT.md` for detailed cloud deployment guides (AWS, Azure, self-hosted).**

## 🏗️ Project Structure

- `app/`: Flask application with core logic
  - `packet_sniffer.py`: Live scapy packet capture engine and rule checks
  - `firewall_manager.py`: Automated IPS blocking
  - `rule_diagnoser.py`: Deterministic diagnostic rules engine
  - `websocket.py`: WebSocket/SocketIO server
  - `security.py`: Input validation and rate limiting
  - `error_handlers.py`: Global error handling
  - `logging_config.py`: Structured logging
  - `swagger_spec.py`: API documentation
  - `static/js/websocket-client.js`: WebSocket client
  - `static/js/dashboard-realtime.js`: Dashboard integration
  
- `tests/`: Comprehensive test suite (150+ tests)
  - `test_auth.py`, `test_security.py`, `test_api.py`, `test_websocket.py`, `test_basic.py`
  
- `migrations/`: Database migrations (Alembic)

- `config.py`: Environment-based configuration

- `run.py`: Entry point with WebSocket support

- `Dockerfile`, `docker-compose.yml`: Container orchestration

- `nginx.conf`: Production reverse proxy with SSL/TLS

- `manage.py`: Database management CLI

## 🔧 Available Commands

```bash
# Development
python run.py                           # Start with WebSocket support

# Database
python manage.py db init                # Initialize migrations
python manage.py db migrate             # Create migration
python manage.py db upgrade             # Apply migrations
python manage.py init_db                # Create all tables
python manage.py create_admin           # Create admin user
python manage.py seed_db                # Add sample data

# Testing
pytest                                  # Run all tests
pytest --cov=app --cov-report=html      # Run with coverage

# Docker
docker-compose up -d                    # Start all services
docker-compose logs -f app              # View app logs
docker-compose down                     # Stop all services
```

## 🌐 Accessing the Application

| Service | URL | Purpose |
|---------|-----|---------|
| Dashboard | http://localhost:5000 | Main web interface |
| API Docs | http://localhost:5000/apidocs | Swagger API documentation |
| Health Check | http://localhost:5000/health | Service health status |

## 🔐 Security Features

- **Input Validation**: IP addresses, emails, ports, usernames, passwords
- **Rate Limiting**: Configurable per-endpoint limits (default: 100/hour)
- **Password Security**: Bcrypt hashing with strength requirements
- **CSRF Protection**: Flask-Talisman with security headers
- **Structured Logging**: Audit trail for security events
- **SSL/TLS**: Support for HTTPS with strong ciphers (TLS 1.2/1.3)
- **CORS**: Configurable cross-origin requests
- **Error Handling**: Consistent JSON error responses

## 📈 Monitoring & Logging

- **Application Logs**: JSON-formatted logs to `logs/app.log`
- **Error Logs**: Daily rotating logs to `logs/error.log`
- **Audit Logs**: Security events to `logs/audit.log`
- **Health Check**: Automatic service health verification
- **Docker Health Checks**: All services monitored

## 🚀 Performance

- **WebSocket**: Real-time updates without polling (95% reduction in polling traffic)
- **Connection Pooling**: Database connection pool (10 connections, 3600s recycle)
- **Compression**: Nginx gzip compression
- **Async Mode**: Threading support for WebSocket
