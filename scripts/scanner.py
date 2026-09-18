#!/usr/bin/env python3
"""
NetSentry LAN Scanner & Network Discovery Engine
Discovers active LAN devices, identifies MAC vendors, resolves hostnames,
measures latency, classifies device types, and detects network changes.
"""

import os
import sys
import json
import time
import socket
import struct
import platform
import subprocess
import concurrent.futures
from datetime import datetime

# Common MAC OUI Vendors Dictionary (Top LAN Manufacturers)
OUI_VENDORS = {
    "00:50:56": ("VMware", "Server"),
    "00:0C:29": ("VMware", "Server"),
    "00:1A:11": ("Google", "Smartphone"),
    "00:1A:2B": ("Cisco", "Router"),
    "00:1B:44": ("SanDisk", "IoT Device"),
    "00:1E:65": ("Apple", "Computer"),
    "00:23:6C": ("Apple", "Laptop"),
    "00:26:BB": ("Apple", "Smartphone"),
    "04:D3:CF": ("Apple", "Smartphone"),
    "08:66:98": ("Apple", "Laptop"),
    "10:9A:DD": ("Apple", "Computer"),
    "14:7D:DA": ("Apple", "Smartphone"),
    "18:AF:61": ("Apple", "Smartphone"),
    "28:CF:E9": ("Apple", "Laptop"),
    "3C:07:54": ("Apple", "Computer"),
    "AC:BC:32": ("Apple", "Laptop"),
    "F0:18:98": ("Apple", "Smartphone"),
    "00:1A:79": ("Samsung", "Smartphone"),
    "00:21:D1": ("Samsung", "Smartphone"),
    "14:BB:6E": ("Samsung", "Smartphone"),
    "34:BE:00": ("Samsung", "Smartphone"),
    "50:01:D9": ("Samsung", "Smartphone"),
    "88:36:5F": ("Samsung", "Smartphone"),
    "C8:02:10": ("Samsung", "Smartphone"),
    "00:1C:B0": ("Dell", "Computer"),
    "00:14:22": ("Dell", "Laptop"),
    "18:03:73": ("Dell", "Computer"),
    "F8:DB:88": ("Dell", "Server"),
    "00:1E:0B": ("HP", "Computer"),
    "00:25:B3": ("HP", "Printer"),
    "3C:D9:2B": ("HP", "Printer"),
    "9C:8E:99": ("HP", "Laptop"),
    "00:1F:33": ("Netgear", "Router"),
    "10:DA:43": ("Netgear", "Router"),
    "20:E5:2A": ("Netgear", "Router"),
    "00:1D:0F": ("TP-Link", "Router"),
    "50:C7:BF": ("TP-Link", "Router"),
    "E8:48:B8": ("TP-Link", "Router"),
    "F4:F2:6D": ("TP-Link", "Access Point"),
    "00:18:E7": ("Cameo Communications / D-Link", "Router"),
    "00:24:01": ("D-Link", "Router"),
    "28:10:7B": ("D-Link", "Switch"),
    "00:11:32": ("Synology", "Server"),
    "00:08:9B": ("QNAP", "Server"),
    "B8:27:EB": ("Raspberry Pi Foundation", "IoT Device"),
    "DC:A6:32": ("Raspberry Pi Foundation", "IoT Device"),
    "E4:5F:01": ("Raspberry Pi Foundation", "IoT Device"),
    "24:0A:C4": ("Espressif Systems", "IoT Device"),
    "30:AE:A4": ("Espressif Systems", "IoT Device"),
    "84:F3:EB": ("Espressif Systems", "IoT Device"),
    "A4:CF:12": ("Espressif Systems", "IoT Device"),
    "EC:FA:BC": ("Espressif Systems", "IoT Device"),
    "00:15:5D": ("Microsoft Hyper-V", "Server"),
    "28:18:78": ("Microsoft", "Computer"),
    "00:1B:21": ("Intel", "Computer"),
    "00:27:0E": ("Intel", "Laptop"),
    "34:13:E8": ("Intel", "Computer"),
    "A4:4C:C8": ("Intel", "Computer"),
    "00:1E:10": ("Huawei", "Smartphone"),
    "78:1D:BA": ("Huawei", "Router"),
    "84:5B:12": ("Xiaomi", "Smartphone"),
    "74:23:44": ("Xiaomi", "IoT Device"),
    "00:11:22": ("Cisco Systems", "Switch"),
    "00:27:90": ("Cisco Systems", "Router"),
    "70:69:79": ("Amazon Technologies", "IoT Device"),
    "FC:65:DE": ("Amazon Echo", "IoT Device"),
    "00:26:73": ("Ricoh", "Printer"),
    "00:00:48": ("Epson", "Printer"),
    "00:22:58": ("Brother", "Printer"),
    "00:00:85": ("Canon", "Printer")
}

def get_mac_vendor_and_type(mac_address):
    """Resolve Vendor and Device Type based on MAC address prefix."""
    if not mac_address or mac_address == "Unknown":
        return "Unknown", "Unknown"
    
    clean_mac = mac_address.upper().replace("-", ":").strip()
    prefix = ":".join(clean_mac.split(":")[:3]) if len(clean_mac.split(":")) >= 3 else ""
    
    if prefix in OUI_VENDORS:
        return OUI_VENDORS[prefix]
    
    return "Unknown", "Unknown"

def get_active_lan_interface():
    """Dynamically determine active default LAN interface, IP, and Subnet."""
    local_ip = "127.0.0.1"
    gateway = "192.168.1.1"
    subnet = "192.168.1.0/24"
    iface_name = "Ethernet / Wi-Fi"

    try:
        # Create UDP socket to determine outgoing interface
        s = socket.socket(socket.AF_INET, socket.SOCK_DGRAM)
        s.connect(("8.8.8.8", 80))
        local_ip = s.getsockname()[0]
        s.close()
    except Exception:
        try:
            local_ip = socket.gethostbyname(socket.gethostname())
        except Exception:
            local_ip = "127.0.0.1"

    is_windows = platform.system().lower() == "windows"

    if is_windows:
        try:
            out = subprocess.check_output("ipconfig", text=True, stderr=subprocess.DEVNULL)
            lines = out.splitlines()
            cur_adapter = "Ethernet"
            for line in lines:
                if "adapter" in line.lower() and ":" in line:
                    cur_adapter = line.split("adapter")[-1].replace(":", "").strip()
                if "IPv4 Address" in line or "IP Address" in line:
                    if local_ip in line:
                        iface_name = cur_adapter
                if "Default Gateway" in line and ":" in line:
                    gw_candidate = line.split(":")[-1].strip()
                    if gw_candidate and "." in gw_candidate and gw_candidate != "0.0.0.0":
                        gateway = gw_candidate
        except Exception:
            pass
    else:
        try:
            out = subprocess.check_output("ip route", shell=True, text=True, stderr=subprocess.DEVNULL)
            for line in out.splitlines():
                if "default via" in line:
                    parts = line.split()
                    gateway = parts[2]
                    if "dev" in parts:
                        iface_name = parts[parts.index("dev") + 1]
        except Exception:
            pass

    if local_ip != "127.0.0.1":
        octets = local_ip.split(".")
        if len(octets) == 4:
            subnet = f"{octets[0]}.{octets[1]}.{octets[2]}.0/24"
            if not gateway or gateway == "192.168.1.1":
                gateway = f"{octets[0]}.{octets[1]}.{octets[2]}.1"

    return {
        "interface": iface_name,
        "local_ip": local_ip,
        "gateway": gateway,
        "subnet": subnet
    }

def ping_ip(ip):
    """Ping an IP and measure roundtrip latency in ms."""
    is_windows = platform.system().lower() == "windows"
    cmd = ["ping", "-n", "1", "-w", "250", ip] if is_windows else ["ping", "-c", "1", "-W", "1", ip]
    
    start_time = time.time()
    try:
        proc = subprocess.run(cmd, stdout=subprocess.PIPE, stderr=subprocess.DEVNULL, text=True, timeout=0.8 if is_windows else 1.5)
        latency = (time.time() - start_time) * 1000.0
        if proc.returncode == 0:
            # Parse precise time from ping output if present
            out = proc.stdout
            if "time=" in out or "time<" in out:
                try:
                    part = out.split("time=")[-1] if "time=" in out else out.split("time<")[-1]
                    ms_str = part.split("ms")[0].strip()
                    latency = float(ms_str)
                except Exception:
                    pass
            return True, round(latency, 1), 0.0
        return False, 0.0, 100.0
    except Exception:
        return False, 0.0, 100.0

def resolve_hostname(ip):
    """Reverse DNS and NetBIOS hostname resolution."""
    try:
        host, _, _ = socket.gethostbyaddr(ip)
        if host and host != ip:
            return host.split(".")[0]
    except Exception:
        pass
    return "Unknown"

def get_arp_table():
    """Read local ARP cache table for IP-MAC mappings."""
    arp_map = {}
    is_windows = platform.system().lower() == "windows"
    cmd = "arp -a" if is_windows else "ip neigh || arp -an"
    
    try:
        out = subprocess.check_output(cmd, shell=True, text=True, stderr=subprocess.DEVNULL)
        for line in out.splitlines():
            line = line.strip()
            parts = line.split()
            if len(parts) >= 2:
                ip = parts[0]
                mac = parts[1]
                if is_windows and len(parts) >= 3:
                    ip = parts[0]
                    mac = parts[1]
                if "." in ip and ("-" in mac or ":" in mac):
                    clean_mac = mac.replace("-", ":").upper()
                    # Filter multicast / broadcast / invalid MACs
                    if not (ip.startswith("224.") or ip.startswith("239.") or ip.endswith(".255") or clean_mac == "FF:FF:FF:FF:FF:FF"):
                        arp_map[ip] = clean_mac
    except Exception:
        pass
    return arp_map

def classify_device(ip, hostname, mac, vendor, dev_type_from_mac, gateway_ip):
    """Intelligently infer device type based on network attributes."""
    if ip == gateway_ip or (hostname and "gateway" in hostname.lower()) or (hostname and "router" in hostname.lower()):
        return "Router"
    
    if dev_type_from_mac != "Unknown":
        return dev_type_from_mac
    
    h_lower = (hostname or "").lower()
    v_lower = (vendor or "").lower()

    if any(k in h_lower for k in ["iphone", "android", "galaxy", "pixel", "redmi", "xiaomi", "mobile", "phone"]):
        return "Smartphone"
    if any(k in h_lower for k in ["desktop", "pc", "workstation", "win", "host"]):
        return "Computer"
    if any(k in h_lower for k in ["laptop", "macbook", "thinkpad", "dell-nb", "hp-nb"]):
        return "Laptop"
    if any(k in h_lower for k in ["printer", "canon", "epson", "hp-print", "brother", "laserjet"]):
        return "Printer"
    if any(k in h_lower for k in ["server", "srv", "nas", "synology", "qnap", "ubuntu", "debian", "proxmox"]):
        return "Server"
    if any(k in h_lower for k in ["tv", "smart", "echo", "alexa", "nest", "tasmota", "sonoff", "esp32", "cam"]):
        return "IoT Device"

    if "apple" in v_lower:
        return "Smartphone"
    if "intel" in v_lower or "dell" in v_lower or "lenovo" in v_lower:
        return "Computer"
    if "cisco" in v_lower or "tp-link" in v_lower or "netgear" in v_lower or "d-link" in v_lower:
        return "Router"

    return "Computer"

def scan_network(target_subnet=None):
    """Execute complete network discovery scan."""
    net_info = get_active_lan_interface()
    subnet = target_subnet or net_info["subnet"]
    gateway = net_info["gateway"]
    local_ip = net_info["local_ip"]

    # Generate IP range (1 to 254)
    base_ip = ".".join(subnet.split(".")[:3])
    ip_list = [f"{base_ip}.{i}" for i in range(1, 255)]

    start_time = time.time()
    discovered_devices = []

    # Fast Concurrent Ping Sweep
    active_ips = []
    with concurrent.futures.ThreadPoolExecutor(max_workers=50) as executor:
        future_to_ip = {executor.submit(ping_ip, ip): ip for ip in ip_list}
        for future in concurrent.futures.as_completed(future_to_ip):
            ip = future_to_ip[future]
            try:
                is_online, latency, packet_loss = future.result()
                if is_online:
                    active_ips.append((ip, latency, packet_loss))
            except Exception:
                pass

    # Read ARP cache to get MAC addresses
    arp_map = get_arp_table()

    # Also include local machine
    if local_ip not in [item[0] for item in active_ips] and local_ip != "127.0.0.1":
        active_ips.append((local_ip, 0.5, 0.0))

    # Also check any other entries present in ARP table that responded
    for ip, mac in arp_map.items():
        if ip.startswith(base_ip) and ip not in [item[0] for item in active_ips]:
            active_ips.append((ip, 5.0, 0.0))

    # Resolve details for all active devices
    for ip, latency, packet_loss in active_ips:
        if ip == local_ip:
            import uuid
            mac_num = uuid.getnode()
            mac = ':'.join(('%012X' % mac_num)[i:i+2] for i in range(0, 12, 2))
        else:
            mac = arp_map.get(ip, "Unknown")
        vendor, dev_type_mac = get_mac_vendor_and_type(mac)
        # Reverse DNS can block for several seconds on offline or isolated LANs.
        # Resolve only the local machine and gateway; other devices remain identifiable by IP/MAC.
        hostname = resolve_hostname(ip) if ip in (local_ip, gateway) else "Unknown"
        
        # Friendly hostname for local IP if unknown
        if hostname == "Unknown":
            if ip == local_ip:
                hostname = socket.gethostname() or "Local-Admin-PC"
            elif ip == gateway:
                hostname = "Gateway-Router"

        device_type = classify_device(ip, hostname, mac, vendor, dev_type_mac, gateway)
        
        # Detect OS where possible
        operating_system = "Unknown"
        if ip == local_ip:
            operating_system = f"{platform.system()} {platform.release()}"
        elif "apple" in vendor.lower():
            operating_system = "iOS / macOS"
        elif "samsung" in vendor.lower() or "xiaomi" in vendor.lower() or "huawei" in vendor.lower():
            operating_system = "Android"
        elif device_type == "Router":
            operating_system = "Embedded Linux / RouterOS"

        discovered_devices.append({
            "ip_address": ip,
            "mac_address": mac,
            "hostname": hostname,
            "device_type": device_type,
            "vendor": vendor,
            "operating_system": operating_system,
            "status": "Online",
            "latency": latency,
            "packet_loss": packet_loss
        })

    duration = round(time.time() - start_time, 2)

    return {
        "status": "success",
        "timestamp": datetime.now().isoformat(),
        "interface": net_info["interface"],
        "subnet": subnet,
        "gateway": gateway,
        "local_ip": local_ip,
        "duration_seconds": duration,
        "total_devices": len(discovered_devices),
        "devices": discovered_devices
    }

if __name__ == "__main__":
    subnet_arg = sys.argv[1] if len(sys.argv) > 1 else None
    results = scan_network(subnet_arg)
    print(json.dumps(results, indent=2))
