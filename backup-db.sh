#!/usr/bin/env bash
# Database backup script.
#
# Usage:
#   DB_CONNECTION=pgsql DB_HOST=... DB_DATABASE=... DB_USERNAME=... DB_PASSWORD=... ./backup-db.sh
#
# Optional:
#   BACKUP_DIR       destination directory (default: ./backups)
#   RETENTION_DAYS   delete backups older than this many days (default: 7)
#   DB_PORT          database port (default: 5432 for pgsql, 3306 for mysql)
#   DB_SSLMODE       PostgreSQL sslmode (default: prefer)
#
# Prefer a password file (.pgpass / ~/.my.cnf) over DB_PASSWORD when the host
# supports it; an environment variable is still safer than command arguments.

set -euo pipefail
umask 077

BACKUP_DIR="${BACKUP_DIR:-./backups}"
RETENTION_DAYS="${RETENTION_DAYS:-7}"
TIMESTAMP="$(date +"%Y%m%d_%H%M%S")"

: "${DB_CONNECTION:?DB_CONNECTION must be set (pgsql or mysql)}"
: "${DB_HOST:?DB_HOST must be set}"
: "${DB_DATABASE:?DB_DATABASE must be set}"
: "${DB_USERNAME:?DB_USERNAME must be set}"
: "${DB_PASSWORD:?DB_PASSWORD must be set}"

mkdir -p "$BACKUP_DIR"
chmod 700 "$BACKUP_DIR"

ARCHIVE="$BACKUP_DIR/db_backup_${TIMESTAMP}.sql.gz"
TEMP_FILE="$(mktemp "$BACKUP_DIR/.db_backup_${TIMESTAMP}_XXXXXX.sql.gz")"

cleanup() {
    if [ -f "$TEMP_FILE" ]; then
        rm -f -- "$TEMP_FILE"
    fi
}
trap cleanup EXIT

case "$DB_CONNECTION" in
    pgsql)
        DB_PORT="${DB_PORT:-5432}"
        # PGPASSWORD is passed through the environment, not the process arguments.
        PGSSLMODE="${DB_SSLMODE:-prefer}" PGPASSWORD="$DB_PASSWORD" pg_dump \
            --host="$DB_HOST" \
            --port="$DB_PORT" \
            --username="$DB_USERNAME" \
            --dbname="$DB_DATABASE" \
            --format=plain \
            --no-owner \
            --no-privileges \
            | gzip -9 > "$TEMP_FILE"
        ;;
    mysql)
        DB_PORT="${DB_PORT:-3306}"
        # MYSQL_PWD is passed through the environment, not the process arguments.
        MYSQL_PWD="$DB_PASSWORD" mysqldump \
            --host="$DB_HOST" \
            --port="$DB_PORT" \
            --user="$DB_USERNAME" \
            --single-transaction \
            --quick \
            --no-tablespaces \
            "$DB_DATABASE" \
            | gzip -9 > "$TEMP_FILE"
        ;;
    *)
        echo "Unsupported DB_CONNECTION: $DB_CONNECTION (expected pgsql or mysql)" >&2
        exit 1
        ;;
esac

# Fail if the archive is missing or truncated.
gzip -t "$TEMP_FILE"
mv -- "$TEMP_FILE" "$ARCHIVE"
trap - EXIT

(
    cd "$BACKUP_DIR"
    sha256sum "$(basename "$ARCHIVE")" > "$(basename "$ARCHIVE").sha256"
)

echo "Backup created: $ARCHIVE ($(du -h "$ARCHIVE" | cut -f1))"
echo "Checksum:       $ARCHIVE.sha256"

# Remove archives and checksums older than RETENTION_DAYS.
find "$BACKUP_DIR" -maxdepth 1 -name "db_backup_*.sql.gz" -type f -mtime "+$RETENTION_DAYS" -delete
find "$BACKUP_DIR" -maxdepth 1 -name "db_backup_*.sql.gz.sha256" -type f -mtime "+$RETENTION_DAYS" -delete
echo "Removed backups older than ${RETENTION_DAYS} days."
