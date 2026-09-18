# Network IDS Deployment Guide

## Table of Contents
1. [Local Development Setup](#local-development-setup)
2. [Docker Deployment](#docker-deployment)
3. [Production Deployment](#production-deployment)
4. [AWS Deployment](#aws-deployment)
5. [Azure Deployment](#azure-deployment)
6. [Monitoring & Maintenance](#monitoring--maintenance)

---

## Local Development Setup

### Prerequisites
- Python 3.11+
- MySQL 8.0+
- Redis (optional, for caching)
- Git

### Installation Steps

1. **Clone the repository**
```bash
git clone <repository-url>
cd fyp
```

2. **Create virtual environment**
```bash
python -m venv venv
source venv/bin/activate  # On Windows: venv\Scripts\activate
```

3. **Install dependencies**
```bash
pip install -r requirements.txt
```

4. **Setup environment variables**
```bash
cp .env.example .env
# Edit .env with your configuration
```

5. **Initialize database**
```bash
python manage.py init_db
python manage.py seed_db  # Optional: add sample data
```

6. **Run the application**
```bash
python run.py
```

The application will be available at `http://localhost:5000`

---

## Docker Deployment

### Prerequisites
- Docker 20.10+
- Docker Compose 2.0+

### Quick Start

1. **Create .env file**
```bash
cp .env.example .env
# Edit .env with your configuration
```

2. **Build and run**
```bash
docker-compose up -d
```

3. **Initialize database**
```bash
docker-compose exec app python manage.py init_db
```

4. **Access the application**
- Web UI: `http://localhost:80`
- API: `http://localhost:5000`
- API Documentation: `http://localhost:5000/apidocs`

### Docker Commands

```bash
# View logs
docker-compose logs -f app

# Stop containers
docker-compose down

# Restart services
docker-compose restart

# Rebuild images
docker-compose build --no-cache
```

---

## Production Deployment

### Pre-deployment Checklist

- [ ] Update `SECRET_KEY` in `.env` to a strong random value
- [ ] Set `FLASK_ENV=production` in `.env`
- [ ] Set `DEBUG=False` in `.env`
- [ ] Configure SSL/TLS certificates
- [ ] Set up proper database backups
- [ ] Configure monitoring and logging
- [ ] Set up health checks
- [ ] Review security settings

### Environment Configuration

```bash
# Production .env example
FLASK_ENV=production
SECRET_KEY=<generate-strong-secret-key>
DATABASE_URL=mysql+pymysql://user:password@db.example.com/network_troubleshooting
MAIL_SERVER=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=your-email@gmail.com
MAIL_PASSWORD=<app-specific-password>
ADMIN_EMAIL=admin@example.com
SESSION_COOKIE_SECURE=True
RATELIMIT_ENABLED=True
```

### Database Setup

```bash
# Create database
mysql -h db.example.com -u root -p
> CREATE DATABASE network_troubleshooting;
> CREATE USER 'netids'@'%' IDENTIFIED BY 'strong_password';
> GRANT ALL PRIVILEGES ON network_troubleshooting.* TO 'netids'@'%';
> FLUSH PRIVILEGES;

# Run migrations
python manage.py db upgrade
```

### Starting the Application

```bash
# Using Gunicorn
gunicorn --bind 0.0.0.0:5000 --workers 4 --worker-class sync run:app

# Using systemd (recommended)
sudo systemctl start network-ids
sudo systemctl enable network-ids  # Auto-start on boot
```

### Nginx Reverse Proxy

The included `nginx.conf` provides:
- SSL/TLS termination
- Rate limiting
- Static file caching
- Security headers
- Gzip compression

Start nginx:
```bash
docker-compose up -d nginx
```

---

## AWS Deployment

### Using Elastic Beanstalk

1. **Install EB CLI**
```bash
pip install awsebcli
```

2. **Initialize Elastic Beanstalk**
```bash
eb init -p python-3.11 network-ids
eb create network-ids-env
```

3. **Deploy application**
```bash
eb deploy
```

4. **Configure environment variables**
```bash
eb setenv FLASK_ENV=production SECRET_KEY=<your-secret-key>
```

### Using ECS/Fargate

1. **Create ECR repository**
```bash
aws ecr create-repository --repository-name network-ids
```

2. **Push Docker image**
```bash
docker build -t network-ids .
docker tag network-ids:latest <account>.dkr.ecr.<region>.amazonaws.com/network-ids:latest
docker push <account>.dkr.ecr.<region>.amazonaws.com/network-ids:latest
```

3. **Create ECS cluster and task definition** (via AWS Console or CLI)

### Using RDS for Database

```bash
# Create RDS instance via AWS Console
# Configuration:
# - Engine: MySQL 8.0
# - Instance class: db.t3.micro (for dev)
# - Storage: 20 GB SSD
# - Multi-AZ: Yes (for production)
# - Backup retention: 30 days

# Update DATABASE_URL in .env
DATABASE_URL=mysql+pymysql://user:password@rds-endpoint.amazonaws.com/network_troubleshooting
```

---

## Azure Deployment

### Using Azure App Service

1. **Create App Service**
```bash
az appservice plan create --name network-ids-plan --resource-group mygroup --sku B2 --is-linux
az webapp create --resource-group mygroup --plan network-ids-plan --name network-ids --runtime "python|3.11"
```

2. **Deploy application**
```bash
az webapp deployment source config-zip --resource-group mygroup --name network-ids --src package.zip
```

3. **Configure environment variables**
```bash
az webapp config appsettings set --resource-group mygroup --name network-ids \
  --settings FLASK_ENV=production SECRET_KEY=<your-secret-key>
```

### Using Azure Container Instances

```bash
az container create \
  --resource-group mygroup \
  --name network-ids \
  --image <registry>/network-ids:latest \
  --environment-variables FLASK_ENV=production SECRET_KEY=<key>
```

### Using Azure Database for MySQL

```bash
# Create database via Portal
# Connection string:
DATABASE_URL=mysql+pymysql://user@server:password@server.mysql.database.azure.com/network_troubleshooting
```

---

## Monitoring & Maintenance

### Health Checks

```bash
# Check application health
curl http://localhost:5000/health

# Expected response:
# {"status": "healthy", "service": "network_ids_app", "version": "1.0.0"}
```

### Logging

Logs are stored in:
- `logs/app.log` - Application logs
- `logs/error.log` - Error logs  
- `logs/audit.log` - Security audit logs

View logs:
```bash
# Real-time logs
tail -f logs/app.log

# Docker logs
docker-compose logs -f app

# Search logs
grep "ERROR" logs/error.log
```

### Database Backups

```bash
# Manual backup
mysqldump -h db.example.com -u user -p network_troubleshooting > backup.sql

# Automated backup (cron job)
0 2 * * * mysqldump -h db.example.com -u user -p network_troubleshooting > /backups/$(date +\%Y\%m\%d).sql
```

### Performance Monitoring

```bash
# Check application metrics
curl http://localhost:5000/api/dashboard_stats

# Monitor database connections
mysql -h db.example.com -u root -p
> SHOW PROCESSLIST;
> SHOW STATUS WHERE variable_name LIKE 'Threads%';
```

### Updates and Patches

```bash
# Update dependencies
pip install --upgrade -r requirements.txt

# Pull latest changes
git pull origin main
git checkout <tag>  # For specific version

# Restart application
systemctl restart network-ids
# or
docker-compose restart app
```

### Troubleshooting

#### Application won't start
```bash
# Check logs
tail -f logs/error.log

# Verify database connection
python -c "from app import create_app, db; app = create_app(); print('DB OK')"

# Check port availability
lsof -i :5000
```

#### Database connection issues
```bash
# Test connection
mysql -h db.host -u user -p -e "SELECT 1"

# Check connection pool
python manage.py db status
```

#### High CPU usage
```bash
# Check running processes
top

# Monitor worker threads
ps aux | grep gunicorn

# Increase worker count in production
gunicorn --workers 8 run:app
```

---

## Security Best Practices

1. **Secrets Management**
   - Use environment variables, never commit secrets
   - Rotate secrets regularly
   - Use a secrets vault (HashiCorp Vault, AWS Secrets Manager)

2. **SSL/TLS**
   - Use strong certificates (Let's Encrypt for free)
   - Enable HSTS headers
   - Use minimum TLS 1.2

3. **Database Security**
   - Use strong passwords (20+ characters)
   - Enable database encryption at rest
   - Use private subnets for databases
   - Regular backups with encryption

4. **Application Security**
   - Keep dependencies updated
   - Run security scans regularly
   - Implement Web Application Firewall (WAF)
   - Use rate limiting and DDoS protection

5. **Access Control**
   - Use IAM roles for cloud deployments
   - Implement MFA for admin access
   - Use VPN for internal access
   - Regular access audits

---

## Rollback Procedure

```bash
# Database rollback
python manage.py db downgrade

# Application rollback
git checkout previous-version
docker-compose build
docker-compose up -d

# DNS/Load balancer failover
# Switch traffic to previous version via infrastructure console
```

---

For more help:
- API Documentation: `http://localhost:5000/apidocs`
- GitHub Issues: `<repository-issues-url>`
- Contact Support: `support@networkids.com`
