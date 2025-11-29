#!/bin/bash

# ═══════════════════════════════════════════════════════════════════════════════════════════
# Crontab Setup Script - Install cron jobs for Laravel scheduler
# ═══════════════════════════════════════════════════════════════════════════════════════════

set -e

echo "════════════════════════════════════════════════════════════════════════════════════"
echo "  Pizza Data System - Crontab Setup"
echo "════════════════════════════════════════════════════════════════════════════════════"
echo ""

# Configuration
APP_DIR="${PWD}"
USER="${USER:-www-data}"

# Colors
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

echo -e "${YELLOW}Application directory: $APP_DIR${NC}"
echo -e "${YELLOW}User: $USER${NC}"
echo ""

# Create cron entry
CRON_ENTRY="* * * * * cd $APP_DIR && php artisan schedule:run >> /dev/null 2>&1"

# Check if entry already exists
if crontab -l -u "$USER" 2>/dev/null | grep -q "schedule:run"; then
    echo -e "${YELLOW}⚠️  Cron job already exists${NC}"
    echo ""
    echo "Current crontab for $USER:"
    crontab -l -u "$USER" | grep schedule:run
    echo ""
    read -p "Replace existing entry? (y/n) " -n 1 -r
    echo
    if [[ ! \$REPLY =~ ^[Yy]$ ]]; then
        echo "Setup cancelled"
        exit 0
    fi

    # Remove old entry
    crontab -l -u "$USER" | grep -v "schedule:run" | crontab -u "$USER" -
fi

# Add cron entry
(crontab -l -u "$USER" 2>/dev/null; echo "$CRON_ENTRY") | crontab -u "$USER" -

echo -e "${GREEN}✓ Cron job installed${NC}"
echo ""
echo "Installed cron entry:"
echo "  $CRON_ENTRY"
echo ""
echo "This will run the Laravel scheduler every minute, which executes:"
echo "  • 02:00 AM - Archive old data"
echo "  • 09:20 AM - Primary daily import"
echo "  • 10:20 AM - Secondary daily import"
echo "  • 10:30 AM - Update daily aggregations"
echo "  • 11:00 AM - Validate data"
echo ""
echo "To view scheduled tasks:"
echo "  php artisan schedule:list"
echo ""
echo "To view crontab:"
echo "  crontab -l -u $USER"
echo ""
echo -e "${GREEN}════════════════════════════════════════════════════════════════════════════════════${NC}"
echo -e "${GREEN}  Crontab setup complete!${NC}"
echo -e "${GREEN}════════════════════════════════════════════════════════════════════════════════════${NC}"
