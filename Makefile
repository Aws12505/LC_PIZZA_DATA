# ═══════════════════════════════════════════════════════════════════════════════════════════
# Makefile for Pizza Data System
# ═══════════════════════════════════════════════════════════════════════════════════════════

.PHONY: help install setup test deploy backup restore clean

# Default target
.DEFAULT_GOAL := help

help: ## Show this help message
	@echo 'Usage: make [target]'
	@echo ''
	@echo 'Available targets:'
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}; {printf "  \033[36m%-15s\033[0m %s\n", $$1, $$2}'

install: ## Install Composer dependencies
	@echo "Installing dependencies..."
	composer install
	@echo "✓ Dependencies installed"

setup: ## Setup development environment
	@echo "Setting up development environment..."
	@./scripts/dev_setup.sh
	@echo "✓ Development environment ready"

setup-db: ## Setup databases
	@echo "Setting up databases..."
	@./scripts/setup_database.sh
	@echo "✓ Databases setup complete"

setup-cron: ## Setup cron jobs
	@echo "Setting up cron jobs..."
	@./scripts/setup_cron.sh
	@echo "✓ Cron jobs configured"

test: ## Run tests
	@echo "Running tests..."
	php artisan test
	@echo "✓ Tests complete"

test-coverage: ## Run tests with coverage
	@echo "Running tests with coverage..."
	php artisan test --coverage
	@echo "✓ Coverage report generated"

deploy: ## Deploy to production
	@echo "Deploying to production..."
	@./scripts/deploy.sh
	@echo "✓ Deployment complete"

backup: ## Backup databases
	@echo "Creating backup..."
	@./scripts/backup.sh
	@echo "✓ Backup complete"

restore: ## Restore from backup (requires BACKUP_NAME)
	@if [ -z "$(BACKUP_NAME)" ]; then \
		echo "Error: BACKUP_NAME not specified"; \
		echo "Usage: make restore BACKUP_NAME=pizza_backup_20251129_120000"; \
		exit 1; \
	fi
	@echo "Restoring from backup: $(BACKUP_NAME)..."
	@BACKUP_NAME=$(BACKUP_NAME) ./scripts/restore.sh $(BACKUP_NAME)
	@echo "✓ Restore complete"

import: ## Import data for specific date (requires DATE)
	@if [ -z "$(DATE)" ]; then \
		echo "Error: DATE not specified"; \
		echo "Usage: make import DATE=2025-11-29"; \
		exit 1; \
	fi
	@echo "Importing data for $(DATE)..."
	php artisan import:daily-data --date=$(DATE)
	@echo "✓ Import complete"

validate: ## Validate data integrity
	@echo "Validating data..."
	php artisan validation:check-data
	@echo "✓ Validation complete"

optimize: ## Optimize database tables
	@echo "Optimizing database tables..."
	php artisan partition:optimize
	@echo "✓ Optimization complete"

stats: ## Show partition statistics
	php artisan partition:stats

clean: ## Clean caches and temp files
	@echo "Cleaning caches..."
	php artisan cache:clear
	php artisan config:clear
	php artisan route:clear
	php artisan view:clear
	@echo "✓ Caches cleared"

serve: ## Start development server
	@echo "Starting development server..."
	@echo "Access at: http://localhost:8000"
	php artisan serve

# Production commands
prod-cache: ## Optimize for production
	php artisan config:cache
	php artisan route:cache
	php artisan view:cache

prod-permissions: ## Set production permissions
	chmod -R 775 storage bootstrap/cache
	chown -R www-data:www-data storage bootstrap/cache

# Maintenance
down: ## Enable maintenance mode
	php artisan down --render="errors::503" --retry=60

up: ## Disable maintenance mode
	php artisan up

# Logs
logs: ## Tail Laravel logs
	tail -f storage/logs/laravel.log

logs-import: ## Tail import logs
	tail -f storage/logs/import-daily-data.log

logs-aggregation: ## Tail aggregation logs
	tail -f storage/logs/aggregation-daily.log
