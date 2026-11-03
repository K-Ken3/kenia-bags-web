# Database Backup Guide

## Automated Backups

### For Supabase
Supabase provides automated backups on paid plans. For free tier, set up manual backups.

### For Railway PostgreSQL
Railway provides automated backups. Configure in Railway dashboard > Database > Backups.

### Manual Backup Script
Use the provided `backup-db.sh` script:

```bash
chmod +x backup-db.sh
./backup-db.sh
```

### Restore from Backup

**PostgreSQL:**
```bash
gunzip < backups/db_backup_YYYYMMDD_HHMMSS.sql.gz | PGPASSWORD=$DB_PASSWORD psql -h $DB_HOST -p $DB_PORT -U $DB_USERNAME -d $DB_DATABASE
```

**MySQL:**
```bash
gunzip < backups/db_backup_YYYYMMDD_HHMMSS.sql.gz | mysql -h $DB_HOST -u $DB_USERNAME -p$DB_PASSWORD $DB_DATABASE
```

## Best Practices
- Store backups off-site (S3, Cloudflare R2)
- Encrypt backups
- Test restores regularly
- Keep at least 7-30 days of backups
- Document restore procedures
