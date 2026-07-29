import os

class Config:
    SECRET_KEY = os.environ.get('SECRET_KEY') or 'super-secret-key-123'
    
    # MySQL Database Connection String
    # Format: mysql+pymysql://username:password@hostname/database_name
    # Default Ampps MySQL credentials: root / mysql
    SQLALCHEMY_DATABASE_URI = os.environ.get('DATABASE_URL') or \
        'mysql+pymysql://root:mysql@localhost/network_troubleshooting'
        
    SQLALCHEMY_TRACK_MODIFICATIONS = False
