#!/bin/bash
set -e

echo "🚀 Starting Laravel Worker Container..."

# Ensure required directories exist
mkdir -p \
  /var/www/html/storage/framework/sessions \
  /var/www/html/storage/framework/views \
  /var/www/html/storage/framework/cache \
  /var/www/html/storage/framework/cache/data \
  /var/www/html/storage/logs \
  /var/www/html/storage/app/public \
  /var/www/html/storage/app/uploads \
  /var/www/html/storage/app/temp \
  /var/www/html/bootstrap/cache

# Ensure laravel.log exists (THIS FIXES YOUR PERMISSION CRASH PERMANENTLY)
touch /var/www/html/storage/logs/laravel.log

# Correct ownership + permissions
chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache || true
chmod -R ug+rwX /var/www/html/storage /var/www/html/bootstrap/cache || true
chmod 664 /var/www/html/storage/logs/laravel.log || true

# Clear caches (optional)
php artisan config:clear || true
php artisan cache:clear || true

# Supervisor runtime dir
mkdir -p /run/supervisor
chmod 755 /run /run/supervisor

echo "✅ Worker container ready!"
exec /usr/bin/supervisord -c /etc/supervisor/supervisord.conf
