#!/bin/bash
# Docker entrypoint script for Flask application

set -e

echo "Waiting for MySQL to be ready..."
while ! mysqladmin ping -h "${DB_HOST:-mysql}" -u "${DB_USER:-root}" -p"${DB_PASSWORD:-mysql}" --silent; do
    echo "MySQL is unavailable - sleeping"
    sleep 1
done

echo "MySQL is up - executing database initialization"

# Create database if not exists
mysql -h "${DB_HOST:-mysql}" -u "${DB_USER:-root}" -p"${DB_PASSWORD:-mysql}" <<-EOSQL
    CREATE DATABASE IF NOT EXISTS \`${DB_NAME:-network_troubleshooting}\`;
    USE \`${DB_NAME:-network_troubleshooting}\`;
    GRANT ALL PRIVILEGES ON \`${DB_NAME:-network_troubleshooting}\`.* TO '${DB_USER:-root}'@'%';
    FLUSH PRIVILEGES;
EOSQL

echo "Database initialization completed"

# Run Flask migrations if Flask-Migrate is being used
# flask db upgrade

# Start the application
exec "$@"
