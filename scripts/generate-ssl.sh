#!/bin/bash
# Generate self-signed SSL certificate for development/testing
# For production, use Let's Encrypt or a commercial CA

# Create ssl directory if it doesn't exist
mkdir -p ssl

# Generate private key (2048-bit RSA)
openssl genrsa -out ssl/key.pem 2048

# Generate certificate signing request
openssl req -new -key ssl/key.pem -out ssl/cert.csr \
  -subj "/C=US/ST=State/L=City/O=Organization/CN=localhost"

# Generate self-signed certificate (valid for 365 days)
openssl x509 -req -days 365 -in ssl/cert.csr \
  -signkey ssl/key.pem -out ssl/cert.pem

# Set appropriate permissions
chmod 600 ssl/key.pem
chmod 644 ssl/cert.pem

echo "SSL certificates generated successfully"
echo "Certificate: ssl/cert.pem"
echo "Private Key: ssl/key.pem"
echo ""
echo "For production, use Let's Encrypt:"
echo "  certbot certonly --standalone -d yourdomain.com"
