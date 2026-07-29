from app import create_app, db
from app.models import Watchlist, NetworkLog, DiagnosisHistory, Alert
from app.monitor import get_network_stats
from app.ai_predictor import predict_network_state
from apscheduler.schedulers.background import BackgroundScheduler

app = create_app()

def background_monitoring():
    with app.app_context():
        items = Watchlist.query.all()
        for item in items:
            ip = item.ip_address
            # Ping
            import ping3
            ping_result = ping3.ping(ip, timeout=1)
            
            latency = 9999
            packet_loss = 100
            status = 'Offline'
            
            if ping_result is not None and ping_result is not False:
                latency = round(ping_result * 1000, 2)
                packet_loss = 0
                status = 'Online'
                
            # Dummy system stats since we can't easily remote psutil without agent
            bw = 10
            cpu = 5
            ram = 5
            
            issue, rec = predict_network_state(latency, packet_loss, bw, cpu, ram)
            
            # Log
            log = NetworkLog(target_ip=ip, latency=latency, packet_loss=packet_loss, status=status)
            db.session.add(log)
            
            if issue != 'Normal':
                diag = DiagnosisHistory(target_ip=ip, issue_detected=issue, recommendation=rec)
                db.session.add(diag)
                
                severity = 'High' if 'DDoS' in issue or 'Exfiltration' in issue else ('Medium' if 'Congestion' in issue else 'Low')
                category = 'Security' if 'DDoS' in issue or 'Exfiltration' in issue else 'Performance'
                msg = f"{issue} detected on {ip}"
                alert = Alert(severity=severity, category=category, message=msg)
                db.session.add(alert)
                
            db.session.commit()

# Start scheduler
scheduler = BackgroundScheduler(daemon=True)
scheduler.add_job(background_monitoring, 'interval', minutes=5)
scheduler.start()

if __name__ == '__main__':
    with app.app_context():
        # Create database tables if they don't exist
        db.create_all()
    app.run(debug=True, host='0.0.0.0', port=5000)
