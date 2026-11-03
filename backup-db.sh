#!/bin/bash
# Database backup script
# Usage: ./backup-db.sh

BACKUP_DIR="./backups"
TIMESTAMP=$(date +"%Y%m%d_%H%M%S")
mkdir -p $BACKUP_DIR

# For PostgreSQL (Supabase)
if [ -n "$DB_CONNECTION" ] && [ "$DB_CONNECTION" = "pgsql" ]; then
    PGPASSWORD=$DB_PASSWORD pg_dump -h $DB_HOST -p $DB_PORT -U $DB_USERNAME -d $DB_DATABASE | gzip > "$BACKUP_DIR/db_backup_$TIMESTAMP.sql.gz"
    echo "Backup created: $BACKUP_DIR/db_backup_$TIMESTAMP.sql.gz"
# For MySQL
elif [ -n "$DB_CONNECTION" ] && [ "$DB_CONNECTION" = "mysql" ]; then
    mysqldump -h $DB_HOST -u $DB_USERNAME -p$DB_PASSWORD $DB_DATABASE | gzip > "$BACKUP_DIR/db_backup_$TIMESTAMP.sql.gz"
    echo "Backup created: $BACKUP_DIR/db_backup_$TIMESTAMP.sql.gz"
else
    echo "Unsupported database connection"
    exit 1
fi

# Keep only last 7 days of backups
find $BACKUP_DIR -name "db_backup_*.sql.gz" -mtime +7 -delete
echo "Old backups cleaned up"
