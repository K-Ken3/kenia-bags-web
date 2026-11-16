# Database Backup and Restore Guide

Backups are only useful if a restore has been proven. Treat an untested backup as no backup.

## 1. Automated provider backups

- **Railway PostgreSQL:** configure backups in the database service settings and confirm the retention window.
- **Supabase:** automated backups depend on the plan; on the free tier, schedule the script below.
- **MySQL (managed):** enable automated snapshots in the provider dashboard.

Provider backups do not cover uploaded files. Back up `storage/app` (payment proofs and local public uploads) or rely on versioned S3 storage as well.

## 2. Manual logical backup

`backup-db.sh` supports PostgreSQL and MySQL, writes an atomically renamed gzip archive, verifies the archive, and emits a SHA-256 checksum.

```bash
export DB_CONNECTION=pgsql
export DB_HOST=db.example.internal
export DB_PORT=5432
export DB_DATABASE=store
export DB_USERNAME=backup_user
export DB_PASSWORD='...'          # prefer .pgpass / ~/.my.cnf where supported
export DB_SSLMODE=require
./backup-db.sh
```

Optional variables:

| Variable | Default | Purpose |
|---|---|---|
| `BACKUP_DIR` | `./backups` | Destination directory (ignored by git) |
| `RETENTION_DAYS` | `7` | Local retention before old archives are deleted |
| `DB_PORT` | `5432` / `3306` | Database port |
| `DB_SSLMODE` | `prefer` | PostgreSQL TLS mode |

The script:

- fails fast (`set -euo pipefail`) and never prints the password,
- passes credentials via `PGPASSWORD` / `MYSQL_PWD` rather than command-line arguments,
- writes to a private temporary file and renames only after `gzip -t` succeeds,
- removes partial files on failure,
- stores a `.sha256` checksum beside each archive.

Use a least-privilege backup role (read-only dump permissions), not the application account.

## 3. Store backups off-site and encrypted

Copy archives to object storage with server-side encryption or encrypt locally before upload:

```bash
gpg --symmetric --cipher-algo AES256 db_backup_YYYYMMDD_HHMMSS.sql.gz
```

- Keep the passphrase in a secret manager, never with the backup.
- Enable object versioning and a lifecycle rule.
- Restrict access to the backup bucket; backups contain customer and order data.
- Keep at least 7–30 daily copies plus weekly/monthly copies depending on business requirements.

## 4. Restore procedure

> Always restore to a **new** database (or a staging instance) first. Never restore over production without a fresh snapshot of the current state.

### PostgreSQL

```bash
sha256sum -c db_backup_YYYYMMDD_HHMMSS.sql.gz.sha256
gunzip -c db_backup_YYYYMMDD_HHMMSS.sql.gz \
  | PGPASSWORD="$RESTORE_PASSWORD" psql \
      --host="$DB_HOST" --port="${DB_PORT:-5432}" \
      --username="$DB_USERNAME" --dbname="$RESTORE_DATABASE" \
      --set ON_ERROR_STOP=on
```

### MySQL

```bash
sha256sum -c db_backup_YYYYMMDD_HHMMSS.sql.gz.sha256
gunzip -c db_backup_YYYYMMDD_HHMMSS.sql.gz \
  | MYSQL_PWD="$RESTORE_PASSWORD" mysql \
      --host="$DB_HOST" --port="${DB_PORT:-3306}" \
      --user="$DB_USERNAME" "$RESTORE_DATABASE"
```

### After restoring

1. Confirm expected row counts for `orders`, `order_items`, `payments`, `payment_events`, `products`, `product_variants`, and `users`.
2. Check the most recent orders and payments for referential integrity.
3. Reconcile payments in the backup window against provider records.
4. Run `php artisan migrate --force` if the backup predates the current schema (only additive migrations should apply).
5. Point a staging application instance at the restored database and complete a smoke test before any production cutover.

## 5. Restore drill — performed 2026-11-12

A restore drill was executed locally against **isolated, synthetic data**. No production
backup was available to this environment, and no production database was touched.

Environment: PostgreSQL 18.3 server initialized in a disposable cluster on
`127.0.0.1:55432`, PHP 8.3.33 with `pdo_pgsql` enabled for the drill.

Procedure:

1. Initialized a throwaway cluster with `initdb --auth=trust` and created two empty
   databases, `kenia_source` and `kenia_restore`.
2. Ran all 21 migrations against `kenia_source` (this also proves the schema is
   portable to PostgreSQL; the default local connection is SQLite).
3. Seeded the catalog/settings/policies and inserted synthetic rows: one admin user,
   one order, one order item, one payment, one payment event, one audit log, one
   operational event.
4. Captured a baseline: per-table row counts, per-table MD5 checksums
   (`md5(string_agg(row_to_json(t)::text, '|' ORDER BY id))`), and constraint counts
   from `pg_constraint`.
5. Dumped with `pg_dump -Fc --no-owner --no-privileges` and recorded the archive
   SHA-256 (`d46e2c4d…a799ab88`, 67,206 bytes).
6. Restored into `kenia_restore` with
   `pg_restore --no-owner --no-privileges --exit-on-error`.
7. Re-ran the baseline on the restored database and diffed.

Results:

| Check | Result |
| --- | --- |
| Row counts (all 21 tables) | Match |
| MD5 data checksums (14 data tables) | Match |
| Constraints (15 FKs, 22 PKs, 10 unique, 108 NOT NULL) | Match |
| Sequence state (`nextval('orders_id_seq')`) | 2 (correct for one existing order) |
| Referential integrity (orders without items) | None found |
| FK enforcement after restore | Bogus `order_items.order_id` and `payments.order_id` correctly rejected |
| Application read check against restored DB (`ops:health`, `migrate:status`, Eloquent relationships/JSON casts) | Passed |
| Repeatability (`dropdb` → `createdb` → `pg_restore` → checksum) | Second restore produced the same `orders` checksum |

Exact repeatable procedure (use your real backup archive and a disposable target):

```bash
export PATH="/path/to/postgres/bin:$PATH"
export PGHOST=127.0.0.1 PGPORT=55432 PGUSER=postgres
createdb kenia_restore

sha256sum -c db_backup_YYYYMMDD_HHMMSS.sql.gz.sha256
pg_restore --no-owner --no-privileges --exit-on-error \
  -d kenia_restore db_backup_YYYYMMDD_HHMMSS.dump   # custom-format archive

psql -d kenia_restore -Atc "select count(*) from orders"
psql -d kenia_restore -Atc "select md5(coalesce(string_agg(row_to_json(x)::text,'|' order by id),'')) from (select * from orders) x"
psql -d kenia_restore -Atc "select contype, count(*) from pg_constraint where connamespace='public'::regnamespace group by contype order by contype"

# Application-level verification
DB_CONNECTION=pgsql DB_DATABASE=kenia_restore php artisan migrate:status
DB_CONNECTION=pgsql DB_DATABASE=kenia_restore php artisan ops:health
```

Notes and limitations:

- The drill proves the dump/restore tooling, schema portability, checksum
  verification, sequence restoration, and FK enforcement. It does **not** prove that
  a provider-managed backup exists or that its retention is configured.
- An actual production restore still requires the operator to (a) confirm provider
  backups/retention, (b) supply the archive, and (c) run the drill against a staging
  database. Until then, production restore is **not independently verified**.
- **RPO:** depends on the provider backup schedule; with daily provider backups the
  RPO is up to 24 hours. Take manual dumps before risky migrations to reduce it.
- **RTO:** the 67 KB synthetic restore completed in seconds. Extrapolate from the
  production dump size during the first staging drill and record the measured time.
- Uploaded files (`storage/app`) are not part of the database dump and must be
  restored separately.

Record each future drill here with date, archive name/checksum, restore duration,
row-count results, and any gaps found.

## 6. Security notes

- Backups contain personal data. Encrypt at rest and in transit; limit access.
- Never commit backups; `/backups` is already git-ignored.
- Do not paste credentials into shell history. Use a secret manager or password file.
- Delete expired backups securely according to the business retention policy.
