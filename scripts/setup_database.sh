#!/bin/bash

# ═══════════════════════════════════════════════════════════════════════════════════════════
# Database Setup Script - Create databases and tables
# ═══════════════════════════════════════════════════════════════════════════════════════════

set -e  # Exit on any error

echo "════════════════════════════════════════════════════════════════════════════════════"
echo "  Pizza Data System - Database Setup"
echo "════════════════════════════════════════════════════════════════════════════════════"
echo ""

# Configuration
DB_HOST="${DB_HOST:-localhost}"
DB_PORT="${DB_PORT:-3306}"
DB_USER="${DB_USER:-root}"
DB_PASSWORD="${DB_PASSWORD}"

OPERATIONAL_DB="pizza_operational"
ANALYTICS_DB="pizza_analytics"

# Colors for output
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m' # No Color

# Check if MySQL is running
echo "Checking MySQL connection..."
if ! mysql -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USER" -p"$DB_PASSWORD" -e "SELECT 1;" > /dev/null 2>&1; then
    echo -e "${RED}✗ Cannot connect to MySQL${NC}"
    echo "Please check your MySQL credentials in .env file"
    exit 1
fi
echo -e "${GREEN}✓ MySQL connection successful${NC}"
echo ""

# Create databases
echo "Creating databases..."
mysql -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USER" -p"$DB_PASSWORD" <<EOF
CREATE DATABASE IF NOT EXISTS $OPERATIONAL_DB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE IF NOT EXISTS $ANALYTICS_DB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
EOF

echo -e "${GREEN}✓ Databases created${NC}"
echo "  • $OPERATIONAL_DB"
echo "  • $ANALYTICS_DB"
echo ""

# Run Laravel migrations
echo "Running Laravel migrations..."
php artisan migrate --database=operational --path=database/migrations/operational --force
php artisan migrate --database=analytics --path=database/migrations/analytics --force

echo -e "${GREEN}✓ Migrations complete${NC}"
echo ""

# Create indexes
echo "Creating indexes for optimal performance..."
mysql -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USER" -p"$DB_PASSWORD" "$OPERATIONAL_DB" <<EOF
-- detail_orders_hot indexes
CREATE INDEX idx_store_date ON detail_orders_hot(franchise_store, business_date);
CREATE INDEX idx_date ON detail_orders_hot(business_date);
CREATE INDEX idx_order_id ON detail_orders_hot(order_id);

-- order_line_hot indexes
CREATE INDEX idx_store_date ON order_line_hot(franchise_store, business_date);
CREATE INDEX idx_item ON order_line_hot(item_id);

-- summary_sales_hot indexes
CREATE INDEX idx_store_date ON summary_sales_hot(franchise_store, business_date);
EOF

mysql -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USER" -p"$DB_PASSWORD" "$ANALYTICS_DB" <<EOF
-- daily_store_summary indexes
CREATE INDEX idx_store_date ON daily_store_summary(franchise_store, business_date);
CREATE INDEX idx_date ON daily_store_summary(business_date);

-- daily_item_summary indexes
CREATE INDEX idx_store_date ON daily_item_summary(franchise_store, business_date);
CREATE INDEX idx_item ON daily_item_summary(item_id);
EOF

echo -e "${GREEN}✓ Indexes created${NC}"
echo ""

# Show database sizes
echo "Database sizes:"
mysql -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USER" -p"$DB_PASSWORD" -e "
SELECT 
    table_schema AS 'Database',
    ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS 'Size (MB)'
FROM information_schema.tables
WHERE table_schema IN ('$OPERATIONAL_DB', '$ANALYTICS_DB')
GROUP BY table_schema;
"

echo ""
echo -e "${GREEN}════════════════════════════════════════════════════════════════════════════════════${NC}"
echo -e "${GREEN}  Database setup complete!${NC}"
echo -e "${GREEN}════════════════════════════════════════════════════════════════════════════════════${NC}"
