#!/bin/bash

# ═══════════════════════════════════════════════════════════════════════════════════════════
# Restore Script - Restore databases from backup
# ═══════════════════════════════════════════════════════════════════════════════════════════

set -e

echo "════════════════════════════════════════════════════════════════════════════════════"
echo "  Pizza Data System - Restore from Backup"
echo "════════════════════════════════════════════════════════════════════════════════════"
echo ""

# Configuration
BACKUP_DIR="${BACKUP_DIR:-/var/backups/pizza-data}"
DB_HOST="${DB_HOST:-localhost}"
DB_PORT="${DB_PORT:-3306}"
DB_USER="${DB_USER:-root}"
DB_PASSWORD="${DB_PASSWORD}"

OPERATIONAL_DB="pizza_operational"
ANALYTICS_DB="pizza_analytics"

# Colors
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m'

# Check if backup name provided
if [ -z "$1" ]; then
    echo -e "${RED}Error: No backup name provided${NC}"
    echo ""
    echo "Usage: ./restore.sh BACKUP_NAME"
    echo ""
    echo "Available backups:"
    ls -lh "$BACKUP_DIR" | grep pizza_backup_ | grep manifest
    exit 1
fi

BACKUP_NAME=$1

# Check if backup exists
if [ ! -f "$BACKUP_DIR/${BACKUP_NAME}_operational.sql.gz" ]; then
    echo -e "${RED}Error: Backup not found: $BACKUP_DIR/${BACKUP_NAME}_operational.sql.gz${NC}"
    exit 1
fi

echo -e "${YELLOW}⚠️  WARNING: This will REPLACE all data in:${NC}"
echo "  • $OPERATIONAL_DB"
echo "  • $ANALYTICS_DB"
echo ""
read -p "Are you absolutely sure? Type 'yes' to continue: " -r
echo
if [[ ! \$REPLY == "yes" ]]; then
    echo "Restore cancelled"
    exit 1
fi

# Enable maintenance mode
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "Enabling maintenance mode..."
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
php artisan down
echo -e "${GREEN}✓ Maintenance mode enabled${NC}"

# Restore operational database
echo ""
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "Restoring operational database..."
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

gunzip -c "$BACKUP_DIR/${BACKUP_NAME}_operational.sql.gz" | \
    mysql -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USER" -p"$DB_PASSWORD" "$OPERATIONAL_DB"

echo -e "${GREEN}✓ Operational database restored${NC}"

# Restore analytics database
echo ""
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "Restoring analytics database..."
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

gunzip -c "$BACKUP_DIR/${BACKUP_NAME}_analytics.sql.gz" | \
    mysql -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USER" -p"$DB_PASSWORD" "$ANALYTICS_DB"

echo -e "${GREEN}✓ Analytics database restored${NC}"

# Clear caches
echo ""
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "Clearing caches..."
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
php artisan cache:clear
php artisan config:clear
echo -e "${GREEN}✓ Caches cleared${NC}"

# Disable maintenance mode
echo ""
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "Disabling maintenance mode..."
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
php artisan up
echo -e "${GREEN}✓ Application is live${NC}"

echo ""
echo -e "${GREEN}════════════════════════════════════════════════════════════════════════════════════${NC}"
echo -e "${GREEN}  Restore complete!${NC}"
echo -e "${GREEN}════════════════════════════════════════════════════════════════════════════════════${NC}"
