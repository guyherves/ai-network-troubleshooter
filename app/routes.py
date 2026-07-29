from flask import Blueprint, render_template, request, jsonify, Response, redirect, url_for
import csv
import io
import subprocess
import socket
from reportlab.lib.pagesizes import letter
from reportlab.pdfgen import canvas
from flask_login import login_required, current_user
from functools import wraps
from app import db
from app.models import User, NetworkLog, DiagnosisHistory, Watchlist, Alert
from app.monitor import get_network_stats
from app.ai_predictor import predict_network_state
from app.scanner import get_hybrid_devices

main = Blueprint('main', __name__)

def admin_required(f):
    @wraps(f)
    def decorated_function(*args, **kwargs):
        if not current_user.is_authenticated or current_user.role != 'admin':
            return "Unauthorized", 403
        return f(*args, **kwargs)
    return decorated_function

def analyst_required(f):
    @wraps(f)
    def decorated_function(*args, **kwargs):
        if not current_user.is_authenticated or current_user.role not in ['admin', 'analyst']:
            return "Unauthorized", 403
        return f(*args, **kwargs)
    return decorated_function

def save_picture(form_picture):
    import os
    import secrets
    from werkzeug.utils import secure_filename
    random_hex = secrets.token_hex(8)
    _, f_ext = os.path.splitext(form_picture.filename)
    picture_fn = random_hex + f_ext
    picture_path = os.path.join('c:\\Program Files\\Ampps\\www\\fyp\\app\\static\\profile_pics', picture_fn)
    form_picture.save(picture_path)
    return picture_fn

@main.route("/")
@main.route("/dashboard")
@login_required
def dashboard():
    return render_template('dashboard.html', title='Dashboard')

@main.route("/history")
@login_required
def history():
    records = DiagnosisHistory.query.order_by(DiagnosisHistory.timestamp.desc()).limit(50).all()
    return render_template('history.html', title='Diagnosis History', records=records)

@main.route("/network_devices")
@login_required
def network_devices():
    return render_template('network_devices.html', title='Network Devices')

@main.route("/live_traffic")
@login_required
def live_traffic():
    return render_template('live_traffic.html', title='Live Traffic')

@main.route("/intrusion_detection")
@login_required
def intrusion_detection():
    return render_template('intrusion_detection.html', title='Intrusion Detection')

@main.route("/ai_predictions")
@login_required
def ai_predictions():
    return render_template('ai_predictions.html', title='AI Predictions')

@main.route("/alerts")
@login_required
def alerts():
    all_alerts = Alert.query.order_by(Alert.timestamp.desc()).limit(100).all()
    stats = {
        'high': sum(1 for a in all_alerts if a.severity == 'High' and a.status == 'Unresolved'),
        'medium': sum(1 for a in all_alerts if a.severity == 'Medium' and a.status == 'Unresolved'),
        'low': sum(1 for a in all_alerts if a.severity == 'Low' and a.status == 'Unresolved')
    }
    return render_template('alerts.html', title='System Alerts', alerts=all_alerts, stats=stats)

@main.route("/api/alerts/acknowledge/<int:alert_id>", methods=['POST'])
@login_required
def acknowledge_alert(alert_id):
    alert = Alert.query.get_or_404(alert_id)
    alert.status = 'Resolved'
    db.session.commit()
    return jsonify({'status': 'success'})

@main.route("/api/alerts/mark_all_read", methods=['POST'])
@login_required
def mark_all_alerts_read():
    Alert.query.filter_by(status='Unresolved').update({'status': 'Resolved'})
    db.session.commit()
    return jsonify({'status': 'success'})

@main.route("/api/alerts/clear", methods=['POST'])
@login_required
def clear_alerts():
    Alert.query.delete()
    db.session.commit()
    return jsonify({'status': 'success'})

@main.route("/analytics")
@login_required
def analytics():
    return render_template('analytics.html', title='Analytics')

@main.route("/monitor_api", methods=['POST'])
@login_required
@analyst_required
def monitor_api():
    target_ip = request.json.get('target_ip', '8.8.8.8')
    
    # 1. Gather stats
    latency, packet_loss, bw, cpu, ram, status = get_network_stats(target_ip)
    
    # 2. Log network status
    net_log = NetworkLog(
        target_ip=target_ip, 
        latency=latency if latency != 9999 else None, 
        packet_loss=packet_loss,
        status=status
    )
    db.session.add(net_log)
    
    # 3. AI Prediction
    issue, rec = predict_network_state(latency, packet_loss, bw, cpu, ram)
    
    # 4. Save Diagnosis and Alert if there is an issue
    if issue != 'Normal':
        diag = DiagnosisHistory(target_ip=target_ip, issue_detected=issue, recommendation=rec)
        db.session.add(diag)
        
        severity = 'High' if 'DDoS' in issue or 'Exfiltration' in issue else ('Medium' if 'Congestion' in issue else 'Low')
        category = 'Security' if 'DDoS' in issue or 'Exfiltration' in issue else 'Performance'
        msg = f"{issue} detected on {target_ip}"
        alert = Alert(severity=severity, category=category, message=msg)
        db.session.add(alert)
        
    db.session.commit()
    # Mock Email Alert Dispatch for Critical Issues
    if issue == "Device Offline / Unreachable" or issue == "Severe Packet Loss":
        print(f"[ALERT] Sending critical email alert to admin: {target_ip} is experiencing {issue}!")
    
    return jsonify({
        'target_ip': target_ip,
        'status': status,
        'latency': latency if latency != 9999 else 'Timeout',
        'packet_loss': f"{packet_loss:.1f}%",
        'bandwidth': f"{bw:.1f}%",
        'cpu': f"{cpu:.1f}%",
        'ram': f"{ram:.1f}%",
        'ai_issue': issue,
        'ai_recommendation': rec
    })

@main.route("/api/devices")
@login_required
def api_devices():
    devices = get_hybrid_devices()
    
    # Calculate some metrics for the dashboard
    workstations = sum(1 for d in devices if d['type'] == 'Workstation')
    mobile = sum(1 for d in devices if d['type'] == 'Phone')
    printers = sum(1 for d in devices if d['type'] in ('Printer', 'IoT'))
    unclassified = sum(1 for d in devices if d['type'] == 'Unknown')
    
    metrics = {
        "workstations": workstations,
        "mobile": mobile,
        "printers": printers,
        "unclassified": unclassified
    }
    
    return jsonify({
        "metrics": metrics,
        "devices": devices
    })

@main.route("/api/scan_network")
@login_required
@analyst_required
def scan_network():
    # Fast network discovery using arp -a (Windows)
    devices = []
    try:
        output = subprocess.check_output("arp -a", shell=True).decode('utf-8')
        for line in output.split('\n'):
            if 'dynamic' in line.lower() or 'static' in line.lower():
                parts = line.split()
                if len(parts) >= 2:
                    ip = parts[0]
                    mac = parts[1]
                    devices.append({'ip': ip, 'mac': mac})
    except Exception as e:
        print("ARP scan failed", e)
        
    return jsonify(devices)

@main.route("/api/port_scan", methods=['POST'])
@login_required
def port_scan():
    data = request.get_json()
    target_ip = data.get('target_ip', '127.0.0.1')
    ports_to_check = [21, 22, 80, 443, 3389, 8080]
    results = {}
    
    for port in ports_to_check:
        sock = socket.socket(socket.AF_INET, socket.SOCK_STREAM)
        sock.settimeout(0.5)
        result = sock.connect_ex((target_ip, port))
        results[str(port)] = "Open" if result == 0 else "Closed"
        sock.close()
        
    return jsonify(results)

@main.route("/api/server_stats")
@login_required
def server_stats():
    # Return real-time CPU and RAM of the server hosting the app
    import psutil
    return jsonify({
        'cpu': psutil.cpu_percent(interval=None),
        'ram': psutil.virtual_memory().percent
    })

@main.route("/api/speedtest")
@login_required
def run_speedtest():
    import speedtest
    try:
        st = speedtest.Speedtest()
        st.get_best_server()
        download = st.download() / 1_000_000 # Convert to Mbps
        upload = st.upload() / 1_000_000 # Convert to Mbps
        ping = st.results.ping
        return jsonify({
            'status': 'success',
            'download': round(download, 2),
            'upload': round(upload, 2),
            'ping': round(ping, 2)
        })
    except Exception as e:
        return jsonify({'status': 'error', 'message': str(e)})

@main.route("/api/watchlist", methods=['GET', 'POST', 'DELETE'])
@login_required
def manage_watchlist():
    if request.method == 'GET':
        items = Watchlist.query.all()
        return jsonify([{'id': w.id, 'ip': w.ip_address} for w in items])
        
    elif request.method == 'POST':
        data = request.get_json()
        ip = data.get('ip')
        if not Watchlist.query.filter_by(ip_address=ip).first():
            w = Watchlist(ip_address=ip)
            db.session.add(w)
            db.session.commit()
            return jsonify({'status': 'success'})
        return jsonify({'status': 'exists'})
        
    elif request.method == 'DELETE':
        data = request.get_json()
        w = Watchlist.query.filter_by(ip_address=data.get('ip')).first()
        if w:
            db.session.delete(w)
            db.session.commit()
        return jsonify({'status': 'success'})

@main.route("/export/pdf")
@login_required
def export_pdf():
    records = DiagnosisHistory.query.order_by(DiagnosisHistory.timestamp.desc()).all()
    
    buffer = io.BytesIO()
    p = canvas.Canvas(buffer, pagesize=letter)
    p.setFont("Helvetica-Bold", 16)
    p.drawString(50, 750, "Network Troubleshooting History Report")
    
    p.setFont("Helvetica", 10)
    y_position = 710
    
    p.drawString(50, y_position, "Timestamp")
    p.drawString(180, y_position, "Target IP")
    p.drawString(280, y_position, "Issue Detected")
    
    y_position -= 10
    p.line(50, y_position, 550, y_position)
    y_position -= 20
    
    for r in records:
        if y_position < 50:
            p.showPage()
            y_position = 750
            
        p.drawString(50, y_position, r.timestamp.strftime('%Y-%m-%d %H:%M'))
        p.drawString(180, y_position, r.target_ip)
        
        # Truncate long issues
        issue = r.issue_detected[:40] + '...' if len(r.issue_detected) > 40 else r.issue_detected
        p.drawString(280, y_position, issue)
        y_position -= 20
        
    p.showPage()
    p.save()
    
    buffer.seek(0)
    return Response(
        buffer,
        mimetype="application/pdf",
        headers={"Content-Disposition": "attachment;filename=troubleshooting_history.pdf"}
    )

@main.route("/admin")
@login_required
@admin_required
def admin_panel():
    users = User.query.all()
    return render_template('admin.html', users=users)

@main.route("/admin/delete_user/<int:id>", methods=['POST'])
@login_required
@admin_required
def delete_user(id):
    if id == current_user.id:
        return jsonify({'status': 'error', 'message': 'Cannot delete yourself'})
    
    user = User.query.get_or_404(id)
    db.session.delete(user)
    db.session.commit()
    return jsonify({'status': 'success'})

@main.route("/admin/change_role/<int:id>", methods=['POST'])
@login_required
@admin_required
def change_role(id):
    if id == current_user.id:
        return jsonify({'status': 'error', 'message': 'Cannot change your own role'})
    
    user = User.query.get_or_404(id)
    data = request.get_json()
    new_role = data.get('role')
    
    if new_role in ['admin', 'user']:
        user.role = new_role
        db.session.commit()
        return jsonify({'status': 'success'})
    return jsonify({'status': 'error', 'message': 'Invalid role'})

@main.route("/profile", methods=['GET', 'POST'])
@login_required
def profile():
    if request.method == 'POST':
        # Handle form submission
        if 'picture' in request.files:
            picture_file = request.files['picture']
            if picture_file.filename != '':
                picture_fn = save_picture(picture_file)
                current_user.image_file = picture_fn
        
        current_user.username = request.form.get('username', current_user.username)
        current_user.email = request.form.get('email', current_user.email)
        db.session.commit()
        return redirect(url_for('main.profile'))
    
    return render_template('profile.html')

@main.route("/settings", methods=['GET', 'POST'])
@login_required
@admin_required
def settings_panel():
    import json
    import os
    settings_file = os.path.join('c:\\Program Files\\Ampps\\www\\fyp', 'settings.json')
    
    if request.method == 'POST':
        data = {
            'system_name': request.form.get('system_name'),
            'ping_interval': request.form.get('ping_interval'),
            'alert_email': request.form.get('alert_email')
        }
        with open(settings_file, 'w') as f:
            json.dump(data, f)
        return redirect(url_for('main.settings_panel'))
        
    # Read settings
    settings = {}
    if os.path.exists(settings_file):
        with open(settings_file, 'r') as f:
            settings = json.load(f)
            
    return render_template('settings.html', settings=settings)

@main.route("/api/chart_data")
@login_required
def chart_data():
    logs = NetworkLog.query.order_by(NetworkLog.timestamp.desc()).limit(15).all()
    logs.reverse() # chronological order
    
    labels = [log.timestamp.strftime('%H:%M:%S') for log in logs]
    latency_data = [log.latency if log.latency else 0 for log in logs]
    packet_loss_data = [log.packet_loss for log in logs]
    
    return jsonify({
        'labels': labels,
        'latency': latency_data,
        'packet_loss': packet_loss_data
    })

@main.route("/export/csv")
@login_required
def export_csv():
    records = DiagnosisHistory.query.order_by(DiagnosisHistory.timestamp.desc()).all()
    
    output = io.StringIO()
    writer = csv.writer(output)
    
    # Write header
    writer.writerow(['ID', 'Timestamp', 'Target IP', 'Issue Detected', 'Recommendation'])
    
    for r in records:
        writer.writerow([r.id, r.timestamp.strftime('%Y-%m-%d %H:%M:%S'), r.target_ip, r.issue_detected, r.recommendation])
        
    response = Response(output.getvalue(), mimetype="text/csv")
    response.headers["Content-Disposition"] = "attachment; filename=troubleshooting_history.csv"
    return response
