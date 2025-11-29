#!/bin/bash

# ═══════════════════════════════════════════════════════════════════════════════════════════
# Deployment Script - Deploy to production
# ═══════════════════════════════════════════════════════════════════════════════════════════

set -e  # Exit on any error

echo "════════════════════════════════════════════════════════════════════════════════════"
echo "  Pizza Data System - Production Deployment"
echo "════════════════════════════════════════════════════════════════════════════════════"
echo ""

# Colors
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m'

# Configuration
DEPLOY_ENV="${DEPLOY_ENV:-production}"
APP_DIR="${APP_DIR:-/var/www/pizza-data}"

echo -e "${YELLOW}Deploying to: $DEPLOY_ENV${NC}"
echo -e "${YELLOW}Application directory: $APP_DIR${NC}"
echo ""

# Ask for confirmation
read -p "Continue with deployment? (y/n) " -n 1 -r
echo
if [[ ! \$REPLY =~ ^[Yy]$ ]]; then
    echo "Deployment cancelled"
    exit 1
fi

# Step 1: Enable maintenance mode
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "[Step 1/10] Enabling maintenance mode"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
cd "$APP_DIR"
php artisan down --render="errors::503" --retry=60
echo -e "${GREEN}✓ Maintenance mode enabled${NC}"
echo ""

# Step 2: Pull latest code
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "[Step 2/10] Pulling latest code from repository"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
git pull origin main
echo -e "${GREEN}✓ Code updated${NC}"
echo ""

# Step 3: Install dependencies
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "[Step 3/10] Installing Composer dependencies"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
composer install --no-dev --optimize-autoloader
echo -e "${GREEN}✓ Dependencies installed${NC}"
echo ""

# Step 4: Clear caches
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "[Step 4/10] Clearing caches"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear
echo -e "${GREEN}✓ Caches cleared${NC}"
echo ""

# Step 5: Run migrations
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "[Step 5/10] Running database migrations"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
php artisan migrate --database=operational --path=database/migrations/operational --force
php artisan migrate --database=analytics --path=database/migrations/analytics --force
echo -e "${GREEN}✓ Migrations complete${NC}"
echo ""

# Step 6: Optimize
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "[Step 6/10] Optimizing application"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
php artisan config:cache
php artisan route:cache
php artisan view:cache
echo -e "${GREEN}✓ Optimization complete${NC}"
echo ""

# Step 7: Set permissions
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "[Step 7/10] Setting permissions"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
chmod -R 775 storage bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache
echo -e "${GREEN}✓ Permissions set${NC}"
echo ""

# Step 8: Restart queue workers
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "[Step 8/10] Restarting queue workers"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
php artisan queue:restart
echo -e "${GREEN}✓ Queue workers restarted${NC}"
echo ""

# Step 9: Restart PHP-FPM
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "[Step 9/10] Restarting PHP-FPM"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
sudo systemctl restart php8.2-fpm
echo -e "${GREEN}✓ PHP-FPM restarted${NC}"
echo ""

# Step 10: Disable maintenance mode
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "[Step 10/10] Disabling maintenance mode"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
php artisan up
echo -e "${GREEN}✓ Application is live${NC}"
echo ""

# Health check
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "Running health check..."
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
curl -s http://localhost/api/health || echo -e "${RED}Health check failed${NC}"
echo ""

echo ""
echo -e "${GREEN}════════════════════════════════════════════════════════════════════════════════════${NC}"
echo -e "${GREEN}  Deployment complete!${NC}"
echo -e "${GREEN}════════════════════════════════════════════════════════════════════════════════════${NC}"
echo ""
echo "Next steps:"
echo "  1. Check application logs: tail -f storage/logs/laravel.log"
echo "  2. Verify scheduled jobs: php artisan schedule:list"
echo "  3. Monitor imports: tail -f storage/logs/import-daily-data.log"
