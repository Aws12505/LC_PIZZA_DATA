#!/bin/bash

# ═══════════════════════════════════════════════════════════════════════════════════════════
# Backup Script - Backup databases and files
# ═══════════════════════════════════════════════════════════════════════════════════════════

set -e

echo "════════════════════════════════════════════════════════════════════════════════════"
echo "  Pizza Data System - Backup"
echo "════════════════════════════════════════════════════════════════════════════════════"
echo ""

# Configuration
BACKUP_DIR="${BACKUP_DIR:-/var/backups/pizza-data}"
RETENTION_DAYS="${RETENTION_DAYS:-30}"
DB_HOST="${DB_HOST:-localhost}"
DB_PORT="${DB_PORT:-3306}"
DB_USER="${DB_USER:-root}"
DB_PASSWORD="${DB_PASSWORD}"

OPERATIONAL_DB="pizza_operational"
ANALYTICS_DB="pizza_analytics"

TIMESTAMP=$(date +%Y%m%d_%H%M%S)
BACKUP_NAME="pizza_backup_${TIMESTAMP}"

# Colors
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

# Create backup directory
mkdir -p "$BACKUP_DIR"

echo -e "${YELLOW}Backup directory: $BACKUP_DIR${NC}"
echo -e "${YELLOW}Retention: $RETENTION_DAYS days${NC}"
echo ""

# Backup operational database
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "Backing up operational database..."
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

mysqldump -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USER" -p"$DB_PASSWORD" \
    --single-transaction \
    --quick \
    --lock-tables=false \
    "$OPERATIONAL_DB" | gzip > "$BACKUP_DIR/${BACKUP_NAME}_operational.sql.gz"

OPERATIONAL_SIZE=$(du -h "$BACKUP_DIR/${BACKUP_NAME}_operational.sql.gz" | cut -f1)
echo -e "${GREEN}✓ Operational database backed up ($OPERATIONAL_SIZE)${NC}"

# Backup analytics database
echo ""
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "Backing up analytics database..."
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

mysqldump -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USER" -p"$DB_PASSWORD" \
    --single-transaction \
    --quick \
    --lock-tables=false \
    "$ANALYTICS_DB" | gzip > "$BACKUP_DIR/${BACKUP_NAME}_analytics.sql.gz"

ANALYTICS_SIZE=$(du -h "$BACKUP_DIR/${BACKUP_NAME}_analytics.sql.gz" | cut -f1)
echo -e "${GREEN}✓ Analytics database backed up ($ANALYTICS_SIZE)${NC}"

# Backup .env file
echo ""
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "Backing up configuration..."
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

if [ -f .env ]; then
    cp .env "$BACKUP_DIR/${BACKUP_NAME}_env"
    echo -e "${GREEN}✓ Configuration backed up${NC}"
fi

# Create backup manifest
echo ""
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "Creating backup manifest..."
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

cat > "$BACKUP_DIR/${BACKUP_NAME}_manifest.txt" <<EOF
Pizza Data System Backup
========================
Timestamp: $TIMESTAMP
Date: $(date)

Files:
- ${BACKUP_NAME}_operational.sql.gz ($OPERATIONAL_SIZE)
- ${BACKUP_NAME}_analytics.sql.gz ($ANALYTICS_SIZE)
- ${BACKUP_NAME}_env

To restore:
1. gunzip ${BACKUP_NAME}_operational.sql.gz
2. mysql -u root -p pizza_operational < ${BACKUP_NAME}_operational.sql
3. gunzip ${BACKUP_NAME}_analytics.sql.gz
4. mysql -u root -p pizza_analytics < ${BACKUP_NAME}_analytics.sql
5. cp ${BACKUP_NAME}_env .env
EOF

echo -e "${GREEN}✓ Manifest created${NC}"

# Clean up old backups
echo ""
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "Cleaning up old backups (older than $RETENTION_DAYS days)..."
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

DELETED_COUNT=$(find "$BACKUP_DIR" -name "pizza_backup_*" -type f -mtime +$RETENTION_DAYS -delete -print | wc -l)
echo -e "${GREEN}✓ Deleted $DELETED_COUNT old backup files${NC}"

echo ""
echo -e "${GREEN}════════════════════════════════════════════════════════════════════════════════════${NC}"
echo -e "${GREEN}  Backup complete!${NC}"
echo -e "${GREEN}════════════════════════════════════════════════════════════════════════════════════${NC}"
echo ""
echo "Backup location: $BACKUP_DIR/$BACKUP_NAME*"
echo ""

# Optional: Upload to S3
if [ ! -z "$AWS_S3_BUCKET" ]; then
    echo "Uploading to S3..."
    aws s3 sync "$BACKUP_DIR" "s3://$AWS_S3_BUCKET/backups/pizza-data/" \
        --exclude "*" \
        --include "${BACKUP_NAME}*"
    echo -e "${GREEN}✓ Uploaded to S3${NC}"
fi
