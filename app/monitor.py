import ping3
import psutil
import time

def get_network_stats(target_ip):
    """
    Pings the target IP and collects local system network stats.
    Returns: (latency_ms, packet_loss, bandwidth, cpu, ram, status)
    """
    # 1. Ping the target
    # ping3 returns latency in seconds, or None if timeout, or False if error
    delay = ping3.ping(target_ip, timeout=2)
    
    # 2. Simulate packet loss (ping3 doesn't easily do multiple packets without a loop)
    # We will send 4 pings to calculate a rough packet loss
    success = 0
    total = 4
    for _ in range(total):
        res = ping3.ping(target_ip, timeout=1)
        if res is not None and res is not False:
            success += 1
            
    packet_loss = ((total - success) / total) * 100
    
    if delay is None or delay is False:
        latency_ms = 9999
        status = 'Offline'
    else:
        latency_ms = round(delay * 1000, 2)
        status = 'Online'
        
    # 3. Get local system stats (CPU, RAM)
    cpu = psutil.cpu_percent(interval=0.1)
    ram = psutil.virtual_memory().percent
    
    # 4. Get rough bandwidth usage (simulation based on bytes sent/recv difference)
    net1 = psutil.net_io_counters()
    time.sleep(0.5)
    net2 = psutil.net_io_counters()
    
    # Calculate bytes per second (very rough)
    bytes_sent = (net2.bytes_sent - net1.bytes_sent) * 2
    bytes_recv = (net2.bytes_recv - net1.bytes_recv) * 2
    total_bytes = bytes_sent + bytes_recv
    
    # Let's map it to a pseudo percentage (assuming 1Gbps link max ~ 125MB/s)
    max_bytes = 125 * 1024 * 1024
    bandwidth_usage = min(100.0, (total_bytes / max_bytes) * 100 * 100) # Exaggerate a bit for testing
    
    return latency_ms, packet_loss, bandwidth_usage, cpu, ram, status
