#!/bin/bash

# ═══════════════════════════════════════════════════════════════════════════════════════════
# Development Setup Script - Quick setup for local development
# ═══════════════════════════════════════════════════════════════════════════════════════════

set -e

echo "════════════════════════════════════════════════════════════════════════════════════"
echo "  Pizza Data System - Development Setup"
echo "════════════════════════════════════════════════════════════════════════════════════"
echo ""

# Colors
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

# Check PHP version
echo "Checking PHP version..."
PHP_VERSION=$(php -r "echo PHP_VERSION;")
echo "PHP Version: $PHP_VERSION"
if [ "$(printf '%s\n' "8.2" "$PHP_VERSION" | sort -V | head -n1)" != "8.2" ]; then
    echo "❌ PHP 8.2 or higher required"
    exit 1
fi
echo -e "${GREEN}✓ PHP version OK${NC}"
echo ""

# Install Composer dependencies
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "Installing Composer dependencies..."
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
composer install
echo -e "${GREEN}✓ Dependencies installed${NC}"
echo ""

# Copy .env file
if [ ! -f .env ]; then
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
    echo "Creating .env file..."
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
    cp .env.example .env
    php artisan key:generate
    echo -e "${GREEN}✓ .env file created${NC}"
    echo -e "${YELLOW}⚠️  Please configure your database credentials in .env${NC}"
    echo ""
fi

# Setup databases
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "Setting up databases..."
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
./scripts/setup_database.sh
echo -e "${GREEN}✓ Databases setup complete${NC}"
echo ""

# Set permissions
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "Setting permissions..."
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
chmod -R 775 storage bootstrap/cache
echo -e "${GREEN}✓ Permissions set${NC}"
echo ""

# Create storage directories
mkdir -p storage/app/temp
mkdir -p storage/logs

# Run tests
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "Running tests..."
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
php artisan test
echo ""

echo -e "${GREEN}════════════════════════════════════════════════════════════════════════════════════${NC}"
echo -e "${GREEN}  Development environment ready!${NC}"
echo -e "${GREEN}════════════════════════════════════════════════════════════════════════════════════${NC}"
echo ""
echo "Next steps:"
echo "  1. Configure .env with your API credentials"
echo "  2. Start development server: php artisan serve"
echo "  3. Import test data: php artisan import:daily-data --date=2025-11-29"
echo "  4. View API docs: http://localhost:8000/api/docs"
