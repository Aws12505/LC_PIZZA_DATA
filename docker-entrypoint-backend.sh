#!/bin/bash
set -e

echo "🚀 Starting Laravel Backend Container..."

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

# Fix ownership - www-data (33:33) needs to own these
echo "🔧 Fixing permissions..."
chown -R www-data:www-data /var/www/html/storage
chown -R www-data:www-data /var/www/html/bootstrap/cache

# Fix permissions - 775 allows www-data AND host user to write
chmod -R 775 /var/www/html/storage
chmod -R 775 /var/www/html/bootstrap/cache

# Ensure .htaccess is readable
if [ -f /var/www/html/public/.htaccess ]; then
    chmod 644 /var/www/html/public/.htaccess
fi

# Clear Laravel caches on startup (prevents stale cache issues)
echo "🧹 Clearing Laravel caches..."
php artisan config:clear || true
php artisan view:clear || true
php artisan cache:clear || true
php artisan route:clear || true

# Create storage symlink if it doesn't exist
if [ ! -L /var/www/html/public/storage ]; then
    php artisan storage:link || true
fi

echo "✅ Backend container ready!"

# Start Apache in foreground
exec apache2-foreground
