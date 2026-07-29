from datetime import datetime
from app import db, login_manager
from flask_login import UserMixin

@login_manager.user_loader
def load_user(user_id):
    return User.query.get(int(user_id))

class User(db.Model, UserMixin):
    id = db.Column(db.Integer, primary_key=True)
    username = db.Column(db.String(20), unique=True, nullable=False)
    email = db.Column(db.String(120), unique=True, nullable=False)
    image_file = db.Column(db.String(20), nullable=False, default='default.jpg')
    password = db.Column(db.String(60), nullable=False)
    role = db.Column(db.String(20), nullable=False, default='admin')
    
    def __repr__(self):
        return f"User('{self.username}', '{self.email}', '{self.role}')"

class NetworkLog(db.Model):
    id = db.Column(db.Integer, primary_key=True)
    timestamp = db.Column(db.DateTime, nullable=False, default=datetime.utcnow)
    target_ip = db.Column(db.String(50), nullable=False)
    latency = db.Column(db.Float, nullable=True) # ms
    packet_loss = db.Column(db.Float, nullable=True) # percentage
    status = db.Column(db.String(20), nullable=False) # Online, Offline
    
    def __repr__(self):
        return f"NetworkLog('{self.target_ip}', '{self.timestamp}', '{self.status}')"

class DiagnosisHistory(db.Model):
    id = db.Column(db.Integer, primary_key=True)
    timestamp = db.Column(db.DateTime, nullable=False, default=datetime.utcnow)
    target_ip = db.Column(db.String(50), nullable=False)
    issue_detected = db.Column(db.String(100), nullable=False)
    recommendation = db.Column(db.Text, nullable=False)
    
    def __repr__(self):
        return f"DiagnosisHistory('{self.target_ip}', '{self.issue_detected}', '{self.timestamp}')"

class Watchlist(db.Model):
    id = db.Column(db.Integer, primary_key=True)
    ip_address = db.Column(db.String(50), nullable=False, unique=True)
    added_on = db.Column(db.DateTime, nullable=False, default=datetime.utcnow)
    
    def __repr__(self):
        return f"Watchlist('{self.ip_address}')"

class Alert(db.Model):
    id = db.Column(db.Integer, primary_key=True)
    timestamp = db.Column(db.DateTime, nullable=False, default=datetime.utcnow)
    severity = db.Column(db.String(20), nullable=False) # High, Medium, Low
    category = db.Column(db.String(50), nullable=False) # Security, Performance, System
    message = db.Column(db.Text, nullable=False)
    status = db.Column(db.String(20), nullable=False, default='Unresolved') # Unresolved, Resolved
    
    def __repr__(self):
        return f"Alert('{self.severity}', '{self.category}', '{self.timestamp}')"
