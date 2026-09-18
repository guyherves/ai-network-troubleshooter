# HTTPS/SSL-TLS Configuration Guide

## Overview

This guide explains how to configure HTTPS/SSL-TLS for the Network IDS application to ensure secure communication between clients and servers.

## Table of Contents
1. [Development Setup](#development-setup)
2. [Production Setup with Let's Encrypt](#production-setup-with-lets-encrypt)
3. [Nginx Configuration](#nginx-configuration)
4. [Environment Variables](#environment-variables)
5. [Certificate Management](#certificate-management)
6. [Troubleshooting](#troubleshooting)

---

## Development Setup

### Quick Self-Signed Certificate

For development and testing purposes, use a self-signed certificate:

```bash
# Generate self-signed certificate
bash generate-ssl.sh

# This creates:
# - ssl/cert.pem (public certificate)
# - ssl/key.pem (private key)
```

### Manual Certificate Generation

```bash
# Create directory
mkdir -p ssl
cd ssl

# Generate private key
openssl genrsa -out key.pem 2048

# Generate certificate signing request
openssl req -new -key key.pem -out cert.csr \
  -subj "/C=US/ST=State/L=City/O=Org/CN=localhost"

# Generate self-signed certificate (365 days)
openssl x509 -req -days 365 -in cert.csr \
  -signkey key.pem -out cert.pem

# Verify certificate
openssl x509 -in cert.pem -text -noout
```

---

## Production Setup with Let's Encrypt

### Prerequisites
- Domain name pointing to your server
- Server with port 80 and 443 accessible from internet
- Certbot installed

### Installation

```bash
# Install certbot
sudo apt-get install certbot python3-certbot-nginx

# For other systems:
# macOS: brew install certbot
# CentOS: sudo yum install certbot
```

### Certificate Generation

```bash
# Standalone (stop web server during renewal)
sudo certbot certonly --standalone -d yourdomain.com -d www.yourdomain.com

# Nginx plugin (automatic renewal)
sudo certbot certonly --nginx -d yourdomain.com -d www.yourdomain.com

# Webroot (existing web server)
sudo certbot certonly --webroot -w /var/www/html \
  -d yourdomain.com -d www.yourdomain.com
```

### Certificate Locations

Certificates are typically located at:
- `/etc/letsencrypt/live/yourdomain.com/fullchain.pem`
- `/etc/letsencrypt/live/yourdomain.com/privkey.pem`

### Auto-Renewal

Let's Encrypt certificates expire after 90 days. Set up automatic renewal:

```bash
# Check renewal status
sudo certbot renew --dry-run

# Add to crontab for automatic renewal
sudo crontab -e

# Add this line:
0 3 * * * /usr/bin/certbot renew --quiet --renew-hook "systemctl reload nginx"
```

---

## Nginx Configuration

### HTTPS Redirect

The included `nginx.conf` already includes:

```nginx
# HTTP to HTTPS redirect
server {
    listen 80;
    server_name _;
    
    location / {
        return 301 https://$host$request_uri;
    }
}

# HTTPS server
server {
    listen 443 ssl http2;
    server_name _;
    
    ssl_certificate /etc/nginx/ssl/cert.pem;
    ssl_certificate_key /etc/nginx/ssl/key.pem;
    
    # ... rest of configuration
}
```

### Update Certificate Paths

For production with Let's Encrypt:

```nginx
ssl_certificate /etc/letsencrypt/live/yourdomain.com/fullchain.pem;
ssl_certificate_key /etc/letsencrypt/live/yourdomain.com/privkey.pem;
```

### Security Headers

The Nginx config includes important security headers:

```nginx
# HSTS - Force HTTPS for 1 year
add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;

# Prevent MIME type sniffing
add_header X-Content-Type-Options "nosniff" always;

# Clickjacking protection
add_header X-Frame-Options "SAMEORIGIN" always;

# XSS protection
add_header X-XSS-Protection "1; mode=block" always;

# Referrer policy
add_header Referrer-Policy "strict-origin-when-cross-origin" always;

# Permissions policy
add_header Permissions-Policy "geolocation=(), microphone=(), camera=()" always;
```

### SSL Protocol Configuration

```nginx
# Use only secure protocols
ssl_protocols TLSv1.2 TLSv1.3;

# Strong cipher suites
ssl_ciphers HIGH:!aNULL:!MD5;

# Prefer server's cipher order
ssl_prefer_server_ciphers on;

# Session caching
ssl_session_cache shared:SSL:10m;
ssl_session_timeout 10m;
```

---

## Environment Variables

### Flask Configuration

Update `.env` for production:

```bash
# Enable HTTPS/TLS
SESSION_COOKIE_SECURE=True
SESSION_COOKIE_HTTPONLY=True
SESSION_COOKIE_SAMESITE=Strict

# Security headers
FLASK_ENV=production
DEBUG=False
```

### Flask-Talisman Configuration

The app automatically configures HTTPS security using Flask-Talisman:

```python
from flask_talisman import Talisman

Talisman(app,
    force_https=True,
    strict_transport_security=True,
    strict_transport_security_max_age=31536000,
    content_security_policy={
        'default-src': "'self'",
        'script-src': "'self' 'unsafe-inline'",
        'style-src': "'self' 'unsafe-inline'"
    }
)
```

---

## Certificate Management

### Check Certificate Validity

```bash
# View certificate details
openssl x509 -in /etc/nginx/ssl/cert.pem -text -noout

# Check expiration date
openssl x509 -in /etc/nginx/ssl/cert.pem -noout -dates

# Verify certificate chain
openssl verify -CAfile /etc/nginx/ssl/chain.pem /etc/nginx/ssl/cert.pem
```

### Certificate Renewal

```bash
# Manual renewal
sudo certbot renew --force-renewal -d yourdomain.com

# Renewal with specific challenge
sudo certbot renew -d yourdomain.com --dns-cloudflare
```

### Monitor Expiration

```bash
# Check all certificates
sudo certbot certificates

# Get expiration alerts
sudo certbot renew --dry-run --verbose
```

---

## Docker Configuration

### Using Let's Encrypt with Docker

```bash
# Install Certbot plugins
pip install certbot-dns-cloudflare  # For DNS challenges

# Generate certificate
docker run -it --rm -v /etc/letsencrypt:/etc/letsencrypt \
  -v /var/lib/letsencrypt:/var/lib/letsencrypt \
  certbot/certbot certonly --dns-cloudflare \
  -d yourdomain.com -d www.yourdomain.com
```

### Mount Certificates in Docker

```yaml
services:
  nginx:
    volumes:
      # Mount Let's Encrypt certificates
      - /etc/letsencrypt/live/yourdomain.com/fullchain.pem:/etc/nginx/ssl/cert.pem:ro
      - /etc/letsencrypt/live/yourdomain.com/privkey.pem:/etc/nginx/ssl/key.pem:ro
      # Or use development self-signed certs
      - ./ssl/cert.pem:/etc/nginx/ssl/cert.pem:ro
      - ./ssl/key.pem:/etc/nginx/ssl/key.pem:ro
```

---

## Troubleshooting

### Mixed Content Warning

Browser shows "Mixed Content" error when loading HTTP resources on HTTPS page:

```python
# Fix in Flask app
from flask_talisman import Talisman

Talisman(app, force_https=True)
```

### Certificate Not Trusted

```bash
# Verify certificate chain
openssl s_client -connect yourdomain.com:443 -showcerts

# Install intermediate certificates if needed
cat intermediate.pem certificate.pem > fullchain.pem
```

### HTTPS Connection Refused

```bash
# Check if Nginx is running
sudo systemctl status nginx

# Check Nginx error log
sudo tail -f /var/log/nginx/error.log

# Verify certificate paths in Nginx config
grep ssl_certificate /etc/nginx/nginx.conf
```

### High SSL Overhead

```nginx
# Enable SSL session caching
ssl_session_cache shared:SSL:50m;
ssl_session_timeout 1d;

# Use HTTP/2
listen 443 ssl http2;
```

### Certificate Renewal Failed

```bash
# Debug renewal process
sudo certbot renew --verbose --dry-run

# Force renewal
sudo certbot renew --force-renewal

# Check certbot logs
sudo tail -f /var/log/letsencrypt/letsencrypt.log
```

---

## Testing HTTPS Configuration

### Test with OpenSSL

```bash
# Test SSL connection
openssl s_client -connect yourdomain.com:443

# Test TLS version
openssl s_client -connect yourdomain.com:443 -tls1_2

# Test specific cipher
openssl s_client -cipher 'HIGH' -connect yourdomain.com:443
```

### Test with curl

```bash
# Test HTTPS endpoint
curl -v https://yourdomain.com

# Ignore self-signed certificate
curl -k https://localhost:443

# Check response headers
curl -I https://yourdomain.com

# Verify security headers
curl -I https://yourdomain.com | grep -i "strict-transport"
```

### Test with Online Tools

- SSL Labs: https://www.ssllabs.com/ssltest/
- SSL Checker: https://www.sslshopper.com/ssl-checker.html
- Security Headers: https://securityheaders.com/

---

## Best Practices

1. **Always use HTTPS in production**
   - Non-HTTPS connections allow man-in-the-middle attacks
   - Modern browsers mark HTTP as insecure

2. **Use strong certificates**
   - Minimum 2048-bit RSA (preferably 4096-bit)
   - Wildcard certificates for subdomains (*.yourdomain.com)

3. **Automate renewal**
   - Don't rely on manual renewal
   - Use cron jobs or systemd timers

4. **Monitor certificate expiration**
   - Set up alerts for expiration
   - Aim to renew well before expiration

5. **Use HSTS**
   - Prevents downgrade attacks
   - Train browsers to always use HTTPS

6. **Certificate pinning**
   - For high-security applications
   - Pin public key or certificate

---

## References

- Let's Encrypt Documentation: https://letsencrypt.org/docs/
- OWASP TLS Cheat Sheet: https://cheatsheetseries.owasp.org/cheatsheets/Transport_Layer_Protection_Cheat_Sheet.html
- Mozilla SSL Configuration: https://ssl-config.mozilla.org/
- NIST Guidelines: https://nvlpubs.nist.gov/nistpubs/SpecialPublications/NIST.SP.800-52r2.pdf
