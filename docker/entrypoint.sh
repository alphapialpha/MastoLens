#!/bin/sh
set -e

# Ensure required writable paths exist on bind-mounted storage.
mkdir -p /var/www/html/storage/framework/cache
mkdir -p /var/www/html/storage/framework/sessions
mkdir -p /var/www/html/storage/framework/views
mkdir -p /var/www/html/storage/framework/testing
mkdir -p /var/www/html/storage/logs
mkdir -p /var/www/html/bootstrap/cache

# Safety check: refuse to run all migrations on a DB that already had data
# This prevents silent data loss if the volume is accidentally wiped
MIGRATION_COUNT=$(php artisan migrate:status --no-ansi 2>/dev/null | grep -c "Ran" || echo "0")
PENDING_COUNT=$(php artisan migrate:status --no-ansi 2>/dev/null | grep -c "Pending" || echo "0")

if [ "$MIGRATION_COUNT" = "0" ] && [ "$PENDING_COUNT" -gt 5 ]; then
    echo "========================================="
    echo "WARNING: Database appears empty but has $PENDING_COUNT pending migrations."
    echo "This may indicate the data volume was wiped."
    echo "If this is a fresh install, set ALLOW_FRESH_MIGRATE=true"
    echo "========================================="
    if [ "$ALLOW_FRESH_MIGRATE" != "true" ]; then
        echo "Aborting. Set ALLOW_FRESH_MIGRATE=true to proceed."
        exit 1
    fi
fi

echo "Running migrations..."
php artisan migrate --force

echo "Caching configuration..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo "Starting PHP-FPM..."
exec php-fpm
