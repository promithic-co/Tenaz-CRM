#!/bin/bash
# Aria PostgreSQL Backup Script
# Add to crontab: 0 3 * * * /opt/aria/backup.sh >> /var/log/aria-backup.log 2>&1

set -e

BACKUP_DIR="/opt/aria/backups"
TIMESTAMP=$(date +%Y%m%d_%H%M%S)
RETENTION_DAYS=30
ENV_FILE="/opt/aria/.env"

# Read DB credentials from .env
DB_HOST=$(grep -E '^DB_HOST=' "$ENV_FILE" 2>/dev/null | cut -d= -f2-)
DB_PORT=$(grep -E '^DB_PORT=' "$ENV_FILE" 2>/dev/null | cut -d= -f2-)
DB_DATABASE=$(grep -E '^DB_DATABASE=' "$ENV_FILE" 2>/dev/null | cut -d= -f2-)
DB_USERNAME=$(grep -E '^DB_USERNAME=' "$ENV_FILE" 2>/dev/null | cut -d= -f2-)
DB_PASSWORD=$(grep -E '^DB_PASSWORD=' "$ENV_FILE" 2>/dev/null | cut -d= -f2-)

# Defaults
DB_HOST="${DB_HOST:-postgres}"
DB_PORT="${DB_PORT:-5432}"
DB_DATABASE="${DB_DATABASE:-laravel_agent}"
DB_USERNAME="${DB_USERNAME:-postgres}"

mkdir -p "$BACKUP_DIR"

echo "[$(date -Iseconds)] Starting backup of ${DB_DATABASE}..."

# Find the postgres container
PG_CONTAINER=$(docker ps -q -f name=postgres 2>/dev/null | head -1)

if [ -n "$PG_CONTAINER" ]; then
    # Dump via docker exec
    PGPASSWORD="$DB_PASSWORD" docker exec -e PGPASSWORD="$DB_PASSWORD" "$PG_CONTAINER" \
        pg_dump -U "$DB_USERNAME" -h localhost "$DB_DATABASE" \
        | gzip > "$BACKUP_DIR/db_${TIMESTAMP}.sql.gz"
else
    # Dump directly (if pg_dump is available on host)
    PGPASSWORD="$DB_PASSWORD" pg_dump \
        -h "$DB_HOST" -p "$DB_PORT" -U "$DB_USERNAME" "$DB_DATABASE" \
        | gzip > "$BACKUP_DIR/db_${TIMESTAMP}.sql.gz"
fi

BACKUP_SIZE=$(du -h "$BACKUP_DIR/db_${TIMESTAMP}.sql.gz" | cut -f1)
echo "[$(date -Iseconds)] Backup complete: db_${TIMESTAMP}.sql.gz (${BACKUP_SIZE})"

# Retention: remove backups older than N days
DELETED=$(find "$BACKUP_DIR" -name "db_*.sql.gz" -mtime +${RETENTION_DAYS} -delete -print | wc -l)
if [ "$DELETED" -gt 0 ]; then
    echo "[$(date -Iseconds)] Cleaned up ${DELETED} old backup(s) (>${RETENTION_DAYS} days)"
fi

# Optional: upload to S3/Wasabi (uncomment and configure)
# aws s3 cp "$BACKUP_DIR/db_${TIMESTAMP}.sql.gz" "s3://aria-backups/db_${TIMESTAMP}.sql.gz"

echo "[$(date -Iseconds)] Backup script finished."
