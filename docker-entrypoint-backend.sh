#!/bin/bash
set -e

echo "🚀 Starting Laravel Backend Container..."

# Required dirs
mkdir -p \
  /var/www/html/storage/framework/sessions \
  /var/www/html/storage/framework/views \
  /var/www/html/storage/framework/cache/data \
  /var/www/html/storage/logs \
  /var/www/html/bootstrap/cache

# Ensure log file exists (prevents Monolog crash)
touch /var/www/html/storage/logs/laravel.log

# Make writable paths owned by www-data
# (limit to storage + bootstrap/cache only)
chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache || true

# Directory perms (rwx for owner+group)
find /var/www/html/storage /var/www/html/bootstrap/cache -type d -exec chmod 775 {} \; || true
# File perms (rw for owner+group)
find /var/www/html/storage /var/www/html/bootstrap/cache -type f -exec chmod 664 {} \; || true

# Optional cache clear (safe)
php artisan config:clear || true
php artisan view:clear || true
php artisan cache:clear || true
php artisan route:clear || true

# storage symlink
if [ ! -L /var/www/html/public/storage ]; then
  php artisan storage:link || true
fi

echo "✅ Backend container ready!"
exec apache2-foreground
