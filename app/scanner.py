import random
import socket

def get_hybrid_devices():
    """
    Returns a mix of real network details and simulated dynamic devices
    to ensure the dashboard always has active data for demonstrations.
    """
    # 1. Get real local IP address
    try:
        s = socket.socket(socket.AF_INET, socket.SOCK_DGRAM)
        s.connect(("8.8.8.8", 80))
        local_ip = s.getsockname()[0]
        s.close()
    except Exception:
        local_ip = "192.168.1.100"

    base_ip = ".".join(local_ip.split(".")[:3])
    
    # 2. Base list of devices (mixed real and fake)
    devices = [
        {
            "hostname": socket.gethostname() or "DESKTOP-DEV1",
            "ip": local_ip,
            "mac": "A4:C3:F0:11:22:33",
            "type": "Workstation",
            "status": "Online",
            "last_seen": "Just now",
            "icon": "bi-pc-display",
            "color_class": "primary"
        },
        {
            "hostname": "iPhone-14",
            "ip": f"{base_ip}.105",
            "mac": "F0:DE:F1:77:88:99",
            "type": "Phone",
            "status": "Online",
            "last_seen": "Just now",
            "icon": "bi-phone",
            "color_class": "info"
        },
        {
            "hostname": "Office-Printer",
            "ip": f"{base_ip}.200",
            "mac": "B8:27:EB:44:55:66",
            "type": "Printer",
            "status": "Online",
            "last_seen": "2 mins ago",
            "icon": "bi-printer",
            "color_class": "warning"
        },
        {
            "hostname": "Smart-TV",
            "ip": f"{base_ip}.110",
            "mac": "A1:B2:C3:D4:E5:F6",
            "type": "IoT",
            "status": "Online",
            "last_seen": "5 mins ago",
            "icon": "bi-tv",
            "color_class": "purple"
        }
    ]

    # 3. Simulate random network events
    event = random.choice([0, 1, 2, 3, 4])
    
    if event == 1:
        devices[1]["status"] = "Offline"
        devices[1]["last_seen"] = "10 mins ago"
    elif event == 2:
        devices[2]["status"] = "High Latency"
    elif event == 3 or event == 4:
        # Simulate a suspicious device connecting
        devices.append({
            "hostname": "Unknown-Device",
            "ip": f"{base_ip}.55",
            "mac": "DE:AD:BE:EF:00:01",
            "type": "Unknown",
            "status": "High Latency",
            "last_seen": "Just now",
            "icon": "bi-question-circle",
            "color_class": "danger"
        })

    return devices
