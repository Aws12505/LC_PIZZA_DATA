#!/bin/bash
set -e

echo "🚀 Starting Laravel Backend Container..."

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

# Correct ownership + permissions (safe for volumes)
chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache || true
chmod -R ug+rwX /var/www/html/storage /var/www/html/bootstrap/cache || true
chmod 664 /var/www/html/storage/logs/laravel.log || true

# Ensure .htaccess is readable
if [ -f /var/www/html/public/.htaccess ]; then
  chmod 644 /var/www/html/public/.htaccess || true
fi

# Clear caches on startup (optional but common)
php artisan config:clear || true
php artisan view:clear || true
php artisan cache:clear || true
php artisan route:clear || true

# Create storage symlink if it doesn't exist
if [ ! -L /var/www/html/public/storage ]; then
  php artisan storage:link || true
fi

echo "✅ Backend container ready!"
exec apache2-foreground
