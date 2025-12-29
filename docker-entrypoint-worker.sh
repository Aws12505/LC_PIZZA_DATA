#!/bin/bash
# docker-entrypoint-worker.sh
set -e

echo "🚀 Starting Laravel Worker Container..."

# Ensure required directories exist
mkdir -p /var/www/html/storage/framework/sessions \
         /var/www/html/storage/framework/views \
         /var/www/html/storage/framework/cache \
         /var/www/html/storage/framework/cache/data \
         /var/www/html/storage/logs \
         /var/www/html/storage/app/public \
         /var/www/html/storage/app/uploads \
         /var/www/html/storage/app/temp \
         /var/www/html/bootstrap/cache

# Fix ownership for www-data
echo "🔧 Fixing permissions for worker..."
chown -R www-data:www-data /var/www/html/storage
chown -R www-data:www-data /var/www/html/bootstrap/cache

# Fix permissions
chmod -R 775 /var/www/html/storage
chmod -R 775 /var/www/html/bootstrap/cache

# Clear caches
echo "🧹 Clearing worker caches..."
php artisan config:clear || true
php artisan cache:clear || true

# Ensure standard runtime directory exists for supervisor socket/pid
# (Best practice in containers: put sockets/pids in /run)
mkdir -p /run/supervisor
chmod 755 /run /run/supervisor

echo "✅ Worker container ready!"

# Start supervisor using the standard config path
exec /usr/bin/supervisord -c /etc/supervisor/supervisord.conf
