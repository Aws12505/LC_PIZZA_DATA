#!/bin/bash
set -e

echo "🚀 Starting Laravel Worker Container..."

mkdir -p \
  /var/www/html/storage/framework/sessions \
  /var/www/html/storage/framework/views \
  /var/www/html/storage/framework/cache/data \
  /var/www/html/storage/logs \
  /var/www/html/bootstrap/cache

touch /var/www/html/storage/logs/laravel.log

chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache || true
find /var/www/html/storage /var/www/html/bootstrap/cache -type d -exec chmod 775 {} \; || true
find /var/www/html/storage /var/www/html/bootstrap/cache -type f -exec chmod 664 {} \; || true

php artisan config:clear || true
php artisan cache:clear || true

mkdir -p /run/supervisor
chmod 755 /run /run/supervisor || true

echo "✅ Worker container ready!"
exec /usr/bin/supervisord -c /etc/supervisor/supervisord.conf
