# NetSentry: Network Monitoring and Intrusion Prevention System

![PHP](https://img.shields.io/badge/PHP-8.x-777BB4?style=for-the-badge&logo=php&logoColor=white)
![Python](https://img.shields.io/badge/Python-3.x-blue?style=for-the-badge&logo=python&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL-Database-4479A1?style=for-the-badge&logo=mysql&logoColor=white)
![Bootstrap](https://img.shields.io/badge/Bootstrap-5-purple?style=for-the-badge&logo=bootstrap&logoColor=white)
![Scapy](https://img.shields.io/badge/Scapy-Packet%20Sniffer-green?style=for-the-badge&logo=wireshark&logoColor=white)

> A lightweight, real-time Network Operations Center (NOC) and Intrusion Prevention System (IPS). Built with a blazing fast PHP 8 backend and a Python `scapy` packet engine, it autonomously scans your local network, tracks device states, monitors latency, and logs critical network events.

## ✨ Features

- **Live Network Discovery**: Uses Python `scapy` to perform real-time ARP and ICMP scans across your `/24` subnet, logging discovered devices and resolving their hardware MAC addresses.
- **Real-Time Dashboard**: A clean, responsive Bootstrap 5 interface with dark mode and glassmorphism styling to view live bandwidth charts, KPIs, and network events without refreshing.
- **Latency Monitoring**: Tracks and charts network ping responses (latency) over time to help diagnose network congestion.
- **Automated Event Logging**: Detects device state transitions (New Device, Device Offline, Device Reconnected) and maintains an audit log of network activity in MySQL.
- **Diagnostic Tools**: Built-in AJAX tools for live ping testing and monitoring firewall rules.
- **Responsive Architecture**: Clean separation of frontend UI, backend PHP logic, and background Python daemons.

## 🛠️ Tech Stack

- **Backend Logic**: PHP 8.x
- **Network Telemetry & Sniffing**: Python 3 (`scapy`, `uuid`, `socket`)
- **Database**: MySQL / MariaDB (via PDO)
- **Frontend**: HTML5, CSS3, Vanilla JS, Bootstrap 5, Chart.js
- **Server**: Apache / Nginx (via AMPPS/XAMPP)

## 🏗️ Project Structure

- `index.php`: Main Dashboard view
- `assets/`: Contains all static CSS and JavaScript files (e.g., Chart.js initializers, dashboard live updates).
- `includes/`: Core PHP backend logic (`db.php` for PDO connections, `functions.php` for helper routines, plus modular `header.php`/`footer.php`).
- `scripts/`: Python daemons (like `scanner.py` for network sweeps).
- `api/`: AJAX endpoints used by the frontend for real-time pinging, stat updates, and live charts.

## Installation & Setup

### Requirements
- A local web server stack like **AMPPS** or **XAMPP** (Apache/Nginx + MySQL + PHP).
- Python 3.x installed and added to your system PATH.
- `scapy` Python library (`pip install scapy`).
- Note: Python scripts require administrative/root privileges to craft ARP packets.

### Quick Start

1. **Clone the repository** into your web server's public directory (e.g., `C:\Program Files\Ampps\www\fyp`).
2. **Start your Web Server and MySQL Database**.
3. **Database Configuration**:
   The system is designed to auto-provision its database schema. Simply open `includes/db.php` and verify the `DB_USER` and `DB_PASS` match your local MySQL configuration (AMPPS default is `root` / `mysql`).
4. **Access the Dashboard**:
   Open your web browser and navigate to `http://localhost/fyp`. The database tables will be created automatically on your first visit!
5. **Run a Network Scan**:
   Click the yellow "Scan Network" button on the dashboard to trigger the Python script and discover devices on your local LAN.
