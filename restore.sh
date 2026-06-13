#!/bin/bash
# Aria PostgreSQL Restore Script
# Usage: bash restore.sh [backup_file.sql.gz]
# Example: bash restore.sh /opt/aria/backups/db_20260320_030000.sql.gz

set -e

BACKUP_DIR="/opt/aria/backups"
ENV_FILE="/opt/aria/.env"

# Accept backup file as argument or show latest available
BACKUP_FILE="${1:-}"

if [ -z "$BACKUP_FILE" ]; then
    echo "Available backups:"
    ls -lh "$BACKUP_DIR"/db_*.sql.gz 2>/dev/null | tail -10
    echo ""
    echo "Usage: bash restore.sh <backup_file>"
    echo "Example: bash restore.sh $BACKUP_DIR/db_20260320_030000.sql.gz"
    exit 1
fi

if [ ! -f "$BACKUP_FILE" ]; then
    echo "ERROR: Backup file not found: $BACKUP_FILE"
    exit 1
fi

# Read DB credentials
DB_DATABASE=$(grep -E '^DB_DATABASE=' "$ENV_FILE" 2>/dev/null | cut -d= -f2-)
DB_USERNAME=$(grep -E '^DB_USERNAME=' "$ENV_FILE" 2>/dev/null | cut -d= -f2-)
DB_PASSWORD=$(grep -E '^DB_PASSWORD=' "$ENV_FILE" 2>/dev/null | cut -d= -f2-)

DB_DATABASE="${DB_DATABASE:-laravel_agent}"
DB_USERNAME="${DB_USERNAME:-postgres}"

echo "========================================"
echo " RESTORE: $BACKUP_FILE"
echo " Database: $DB_DATABASE"
echo "========================================"
echo ""
echo "WARNING: This will DROP and recreate the database!"
read -p "Are you sure? (yes/no): " CONFIRM

if [ "$CONFIRM" != "yes" ]; then
    echo "Aborted."
    exit 0
fi

PG_CONTAINER=$(docker ps -q -f name=postgres 2>/dev/null | head -1)

if [ -z "$PG_CONTAINER" ]; then
    echo "ERROR: PostgreSQL container not found. Is it running?"
    exit 1
fi

echo "[1/3] Stopping application workers..."
docker exec "$(docker ps -q -f name=aria_aria | head -1)" php artisan down 2>/dev/null || true

echo "[2/3] Restoring database..."
PGPASSWORD="$DB_PASSWORD" docker exec -e PGPASSWORD="$DB_PASSWORD" "$PG_CONTAINER" \
    psql -U "$DB_USERNAME" -c "DROP DATABASE IF EXISTS \"${DB_DATABASE}\";" postgres
PGPASSWORD="$DB_PASSWORD" docker exec -e PGPASSWORD="$DB_PASSWORD" "$PG_CONTAINER" \
    psql -U "$DB_USERNAME" -c "CREATE DATABASE \"${DB_DATABASE}\";" postgres

gunzip -c "$BACKUP_FILE" | PGPASSWORD="$DB_PASSWORD" docker exec -i -e PGPASSWORD="$DB_PASSWORD" "$PG_CONTAINER" \
    psql -U "$DB_USERNAME" "$DB_DATABASE"

echo "[3/3] Bringing application back up..."
docker exec "$(docker ps -q -f name=aria_aria | head -1)" php artisan up 2>/dev/null || true

echo ""
echo "Restore complete! Database has been restored from: $BACKUP_FILE"
echo "Consider running: docker exec \$(docker ps -q -f name=aria_aria) php artisan migrate"
