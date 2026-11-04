#!/bin/sh
set -e

# ==============================================================================
# ApexApprove - Automated Container Startup & Initialization
# Tailored for Hostinger 1-Click VPS Deployments
# ==============================================================================

echo "🚀 [ApexApprove] Initializing application container..."

# 1. Environment Configuration Check
if [ ! -f /var/www/html/.env ]; then
    echo "📋 [ApexApprove] Generating .env from .env.example..."
    cp /var/www/html/.env.example /var/www/html/.env
fi

# 2. Generate random APP_KEY if empty
if ! grep -q "^APP_KEY=base64:" /var/www/html/.env; then
    echo "🔑 [ApexApprove] Generating new APP_KEY..."
    RANDOM_KEY=$(head -c 32 /dev/urandom | base64)
    sed -i "s|^APP_KEY=.*|APP_KEY=base64:$RANDOM_KEY|" /var/www/html/.env
fi

# 3. Create persistent storage directories with correct permissions
mkdir -p /var/www/html/storage/app/public \
         /var/www/html/storage/app/quarantine \
         /var/www/html/storage/framework/cache \
         /var/www/html/storage/framework/sessions \
         /var/www/html/storage/framework/views \
         /var/www/html/storage/logs

chmod -R 775 /var/www/html/storage

# 4. Wait for PostgreSQL to be ready
DB_HOST="${DB_HOST:-postgres}"
DB_PORT="${DB_PORT:-5432}"

echo "⏳ [ApexApprove] Waiting for PostgreSQL at $DB_HOST:$DB_PORT..."
MAX_TRIES=30
TRIES=0
while ! nc -z "$DB_HOST" "$DB_PORT" 2>/dev/null; do
    TRIES=$((TRIES + 1))
    if [ "$TRIES" -ge "$MAX_TRIES" ]; then
        echo "❌ [ApexApprove] Timed out waiting for PostgreSQL."
        exit 1
    fi
    sleep 2
done
echo "✅ [ApexApprove] PostgreSQL is reachable."

# 5. Database Schema Auto-Initialization
echo "🔄 [ApexApprove] Checking and applying database migrations..."
export PGPASSWORD="${DB_PASSWORD:-apex_secret}"
DB_USER="${DB_USERNAME:-apex_user}"
DB_NAME="${DB_DATABASE:-apex_db}"

for migration_file in /var/www/html/database/migrations/*.sql; do
    if [ -f "$migration_file" ]; then
        echo "   -> Executing $(basename "$migration_file")..."
        psql -h "$DB_HOST" -p "$DB_PORT" -U "$DB_USER" -d "$DB_NAME" -f "$migration_file" 2>&1 | grep -v "already exists" || true
    fi
done

echo "✅ [ApexApprove] System ready for traffic."

# Execute user command (e.g. php-fpm or queue:work)
exec "$@"
