# Deployment Guide

Target environments: **Railway** (primary), a Railway-managed or Supabase PostgreSQL database, and object storage for catalog images. The guide also applies to any PHP 8.3+ PaaS or VM with nginx + PHP-FPM.

The repository contains Railway config-as-code:

| File | Service | Config file path setting |
|---|---|---|
| `railway.json` | Web | default (leave empty) |
| `railway.worker.json` | Queue worker | `railway.worker.json` |
| `railway.scheduler.json` | Scheduler | `railway.scheduler.json` |

Railway builds with **Railpack**, which detects Laravel, installs PHP extensions from `composer.json` and `RAILPACK_PHP_EXTENSIONS`, runs the Vite build (`npm run build`), and on boot runs migrations, `storage:link`, and the optimized caches before starting FrankenPHP. No `Dockerfile`, `Procfile`, or `nixpacks.toml` is required.

## 0. Railway quick start

1. Push this repository to GitHub `main`.
2. Create a Railway project from the GitHub repo. Railway builds the web service with `railway.json`; the health check is `GET /health` (300 s grace for the first migration).
3. Add the **PostgreSQL** database from the project canvas.
4. Open the web service → **Settings → Networking → Generate Domain** and copy the `https://…up.railway.app` URL.
5. Set the variables below on the web service (Railway redeploys automatically).

| Variable | Value |
|---|---|
| `APP_NAME` | `Isimbi Mart` |
| `APP_ENV` | `production` |
| `APP_DEBUG` | `false` |
| `APP_KEY` | `php artisan key:generate --show` output, generated once |
| `APP_URL` | The generated `https://` domain, no trailing slash |
| `TRUSTED_HOSTS` | The same hostname without scheme (defaults to the `APP_URL` host, but set it explicitly) |
| `DB_CONNECTION` | `pgsql` |
| `DB_URL` | `${{Postgres.DATABASE_URL}}` |
| `SESSION_DRIVER` | `database` |
| `SESSION_ENCRYPT` | `true` |
| `SESSION_SECURE_COOKIE` | `true` |
| `CACHE_STORE` | `database` |
| `QUEUE_CONNECTION` | `database` |
| `LOG_CHANNEL` | `stderr` |
| `LOG_LEVEL` | `warning` |
| `BCRYPT_ROUNDS` | `12` |
| `TRUST_PROXY_HEADERS` | `true` |
| `FILESYSTEM_DISK` | `local` |
| `CATALOG_DISK` | `public` |
| `RAILPACK_PHP_EXTENSIONS` | `gd,pdo_pgsql` |
| `PAYMENT_SANDBOX_ENABLED` | `false` |
| `PAYMENT_WEBHOOK_ENABLED` | `false` |
| `XENTRIPAY_ENABLED` | `false` until gateway credentials are live |

Do **not** set `APP_URL` to `localhost`; the trusted-host middleware rejects mismatched hosts and the health check fails.

6. Attach a **volume** to the web service mounted at `/app/storage/app` (1 GB is enough to start). This persists catalog uploads (`storage/app/public`) and private payment proofs (`storage/app/private`) across deploys. Without it, uploads disappear on every redeploy.
7. Deploy. Confirm `https://<domain>/health` returns `{"status":"ok"}`.
8. Add a second service from the same repo for the queue worker: set **Config file path** to `railway.worker.json` and connect it to the same Postgres and variables. It needs no public domain.
9. Add a third service for the scheduler: set **Config file path** to `railway.scheduler.json`. Copy the same variables. Do not give it a domain or a health check.
10. Open a shell on the web service and create the first administrator: `php artisan admin:create`. Sign in, complete TOTP enrollment, and store the recovery codes. Then run `php artisan ops:preflight` and fix any **FAIL** rows.
11. In **Admin → Settings**, set brand, contact channels, delivery fee, and (if used before the gateway is live) manual MoMo with a pay code. Add products and variants.
12. Re-run `php artisan ops:preflight --strict` once content and monitoring are configured.

Images: the volume is the launch-time choice. Cloudinary is not S3-compatible and would need a custom storage adapter, so it is intentionally deferred. To move catalog images to external storage later, use an S3-compatible bucket (AWS S3, Cloudflare R2, Backblaze B2, Supabase Storage) with `CATALOG_DISK=s3` and the `AWS_*` variables from section 6; no application code changes are required.

## 1. Prerequisites

- PHP 8.3+ with `pdo`, the driver for your database (`pdo_pgsql` or `pdo_mysql`), `gd` with WebP, `fileinfo`, `mbstring`, `openssl`, `tokenizer`, `xml`, `curl`, `zip`.
- Composer 2.
- Node.js 20+ and npm for asset build (CI/build stage only).
- A PostgreSQL or MySQL database reachable over TLS.
- A persistent disk or S3-compatible bucket for uploads.
- HTTPS termination at the platform edge.

## 2. Environment variables

Set these in the hosting dashboard. Never commit real values.

### Core

| Variable | Production value |
|---|---|
| `APP_NAME` | Store name |
| `APP_ENV` | `production` |
| `APP_KEY` | Output of `php artisan key:generate --show` (set once, then keep) |
| `APP_DEBUG` | `false` |
| `APP_URL` | `https://your-domain` (exact origin, no trailing slash) |
| `LOG_CHANNEL` | `stderr` (container logs must reach the platform collector) |
| `LOG_LEVEL` | `warning` (raise temporarily during diagnosis) |
| `BCRYPT_ROUNDS` | `12` |

### Database

| Variable | Notes |
|---|---|
| `DB_CONNECTION` | `pgsql` or `mysql` |
| `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` | From the managed database |
| `DB_SSLMODE` | `require` for Supabase and most managed Postgres |

### Session / cache / queue

| Variable | Production value |
|---|---|
| `SESSION_DRIVER` | `database` (default) |
| `SESSION_ENCRYPT` | `true` |
| `SESSION_SECURE_COOKIE` | `true` |
| `SESSION_SAME_SITE` | `lax` (default) |
| `SESSION_LIFETIME` | `120` |
| `ADMIN_IDLE_TIMEOUT` | `60` |
| `ADMIN_SESSION_LIFETIME` | `480` |
| `CACHE_STORE` | `database` (or Redis if provisioned) |
| `QUEUE_CONNECTION` | `database` (or Redis) |

### Proxy, host and client-IP handling

| Variable | Production value |
|---|---|
| `TRUSTED_PROXIES` | Empty for Railway (no published stable edge ranges). Set only to explicit IPs/CIDRs documented by the platform. |
| `TRUST_PROXY_HEADERS` | `true` on Railway; only safe while direct container access is blocked |
| `TRUSTED_HOSTS` | The exact public hostname(s); defaults to the `APP_URL` host |

### Monitoring

| Variable | Production value |
|---|---|
| `MONITORING_ENABLED` | `true` |
| `MONITORING_ALERT_WEBHOOK` | HTTPS Slack-compatible webhook (secret URL, set in the dashboard only) |
| `MONITORING_RETENTION_DAYS` | `30` |
| `MONITORING_*_THRESHOLD` | Defaults are documented in `config/monitoring.php`; tune after observing normal traffic |

### Storage

| Variable | Notes |
|---|---|
| `FILESYSTEM_DISK` | `local` for private proofs |
| `CATALOG_DISK` | `public` (with persistent volume) or `s3` |
| `AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY`, `AWS_DEFAULT_REGION`, `AWS_BUCKET` | Only for S3-compatible storage |
| `AWS_ENDPOINT`, `AWS_USE_PATH_STYLE_ENDPOINT` | For non-AWS S3 providers |

### Payments

| Variable | Notes |
|---|---|
| `PAYMENT_SANDBOX_ENABLED` | `false` |
| `PAYMENT_WEBHOOK_ENABLED` | `false` unless a custom adapter is implemented |
| `PAYMENT_WEBHOOK_SECRET` | High-entropy value if enabled |
| `XENTRIPAY_ENABLED` | `true` only after provider approval |
| `XENTRIPAY_BASE_URL` | Sandbox or production URL from XentriPay |
| `XENTRIPAY_API_KEY` | Server-side only |
| `XENTRIPAY_WEBHOOK_SECRET` | Register the same value with the provider |
| `XENTRIPAY_CHARGES_INCLUDED` | Must match the merchant contract |

### Mail

The MVP uses `MAIL_MAILER=log` and sends no email. If notifications are added, configure a real mailer and verify deliverability before launch.

## 3. Build and release commands

Configure these in the platform (or run them in a release phase):

```bash
composer install --no-dev --optimize-autoloader --no-interaction
npm ci
npm run typecheck
npm run build
php artisan migrate --force
php artisan storage:link
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan ops:preflight
```

Notes:

- On Railway, `railway.json` runs `npm run build` in the build phase and Railpack's Laravel start script applies migrations, `storage:link` and the optimized caches at boot. The commands above are the manual path for other platforms or a pre-deploy hook.
- `php artisan ops:preflight` validates the production configuration (debug mode, HTTPS URL, session/cookie flags, trusted hosts, monitoring, payment sandbox flag, storage permissions, database reachability, admin MFA enrollment). It prints **FAIL** rows and exits non-zero when a mandatory production condition is violated; run it as a release gate after the environment variables and caches are in place. It never prints secrets. Outside `APP_ENV=production` most checks are skipped.
- `php artisan ops:preflight --strict` also fails on warnings (for example, monitoring alert webhook unset or an XentriPay sandbox URL in production). Use it in the release gate once the deployment is complete.
- Run `storage:link` only when `CATALOG_DISK=public` and a persistent volume is mounted.
- `config:cache` must run **after** all environment variables are set; changing env vars later requires re-running it (or a redeploy).
- Do not run `migrate:fresh`, `db:wipe`, or seeders in production. Seeders create sample catalog data.
- Migrations in this project are additive/reversible. The security index migration only creates indexes.

## 4. Runtime services

Run the web process and two background services.

**Web:** platform-managed PHP runtime behind the edge proxy. Health checks:

- `GET /health` — application-level probe; 200 `{"status":"ok"}` when the database and cache respond, 503 `{"status":"degraded"}` otherwise. No infrastructure details are exposed.
- `GET /up` — Laravel's built-in liveness probe.

Point the platform health check and any external uptime monitor at `/health`.

If the platform requires an explicit start command and does not provide nginx/PHP-FPM, use:

```bash
php artisan serve --host=0.0.0.0 --port=$PORT
```

This is acceptable for low traffic but is a single-process development server. For production scale, run nginx + PHP-FPM (or Laravel Octane) as the web service; do not expose `artisan serve` to high traffic.

**Scheduler:** run continuously or via platform cron:

```bash
php artisan schedule:work
```

or cron every minute:

```bash
* * * * * cd /app && php artisan schedule:run >> /dev/null 2>&1
```

Scheduled jobs:

- `app:expire-orders` every five minutes (expires unpaid orders and releases stock).
- `app:poll-xentripay` every minute (reconciles pending provider payments).
- `ops:monitor` every five minutes (counts server errors, failed jobs, stuck payments, webhook/reconciliation failures, admin failures; sends deduplicated alerts).
- `ops:prune-events` daily (removes monitoring events older than `MONITORING_RETENTION_DAYS`).

If the scheduler is not running, unpaid orders never expire, provider payments may not settle without a customer-triggered check, and no alerts are evaluated. Verify it with `php artisan ops:health` (the "Last ops:monitor run" row must be recent).

**Queue worker:** required when queued jobs exist:

```bash
php artisan queue:work --tries=3 --timeout=90
```

Restart workers after each deploy (`php artisan queue:restart`).

## 5. Database

### Railway PostgreSQL / managed Postgres
1. Provision the database and copy connection values into the app service variables.
2. Set `DB_SSLMODE=require` when the provider requires TLS.
3. Run `php artisan migrate --force` in the release phase.
4. Confirm the connection from the app service, not just locally.

### Supabase
1. Use the **pooled** connection string for the web service if the platform has many short-lived connections; use the direct connection for migrations if the pooler rejects DDL.
2. Set `DB_SSLMODE=require`.
3. Confirm `search_path` is `public` (the default config sets this).

### SQLite
SQLite is for local development only. It is not suitable for multi-instance production deployment.

## 6. Storage and uploads

- Payment proofs are written to the **private** disk and served only through authorized admin routes. Do not move them to the public disk or expose `storage/app` directly.
- Product and brand images use `CATALOG_DISK`.
- With `CATALOG_DISK=public`, mount a persistent volume at `storage/app/public`; otherwise uploads disappear on redeploy. Run `php artisan storage:link`.
- With S3, configure the bucket, credentials, and (if needed) `AWS_ENDPOINT`. The CSP automatically allows the configured S3 host for images in production.

## 7. Reverse proxy, client IPs and host headers

Railway does not publish stable edge proxy IP ranges, so the repository does **not** invent any. Two modes are supported:

1. **Explicit proxies (preferred when the platform publishes ranges).** Set `TRUSTED_PROXIES` to the exact IPs/CIDRs. Laravel's standard trusted-proxy handling is used and only those hops are trusted.
2. **Rightmost-`X-Forwarded-For` normalization (Railway default).** With `TRUSTED_PROXIES` empty and `TRUST_PROXY_HEADERS=true`, `App\Http\Middleware\NormalizeProxyHeaders` replaces `REMOTE_ADDR` with the **rightmost** valid address in `X-Forwarded-For` and collapses the header. Client-supplied entries to the left cannot spoof the client IP. `X-Forwarded-Proto` is used to detect HTTPS.

Mode 2 is only safe if the application cannot be reached directly: no public TCP proxy, no published port, and no alternate hostname that bypasses Railway's edge. If direct access is possible, forwarded headers are attacker-controlled and rate limiting, audit IPs and HTTPS detection become unreliable. In that case set explicit `TRUSTED_PROXIES` or block direct access at the network layer.

`TRUSTED_HOSTS` is enforced by `App\Http\Middleware\EnforceTrustedHosts` and rejects requests with an unexpected `Host` header (HTTP 400). It defaults to the `APP_URL` host; set it explicitly when several hostnames are served.

Verification after deployment:

```bash
# Direct request with a spoofed leftmost XFF must still be rate-limited/audited by the real client IP.
curl -s -o /dev/null -w '%{http_code}\n' -H 'X-Forwarded-For: 1.2.3.4' https://your-domain/
# An unexpected Host must be rejected once TRUSTED_HOSTS is set.
curl -s -o /dev/null -w '%{http_code}\n' -H 'Host: attacker.example' https://your-domain/
```

`tests/Feature/ProxyTrustTest.php` covers rightmost-XFF selection, `X-Real-IP` fallback, scheme detection, spoofed leftmost values, and trusted-host rejection. Automated tests do not prove that the platform blocks direct access; that is an infrastructure control the operator must verify.

## 8. HTTPS and headers

- Terminate TLS at the platform; force HTTPS redirects if the platform does not.
- `URL::forceScheme('https')` is applied automatically in production.
- Security headers (CSP, HSTS, COOP, CORP, no-store on private pages) are applied by middleware. Verify after deployment:

```bash
curl -sI https://your-domain | grep -iE "strict-transport|content-security|x-frame|content-type-options|cross-origin"
```

## 9. First-run checklist

1. Deploy with all environment variables set and `APP_DEBUG=false`.
2. Run migrations; confirm no errors and no destructive statements.
3. Create the first administrator: `php artisan admin:create` (interactive; ≥12-character password).
4. Sign in at `/admin/login`. The account is not admitted to the admin area until TOTP enrollment is complete: scan the QR code (or enter the secret) in an authenticator app, confirm a 6-digit code, and store the one-time recovery codes offline. Verify `ops:preflight` reports all administrators enrolled.
5. Configure **Admin → Settings**: brand, contact channels, delivery fee (explicitly confirmed), manual MoMo if used.
6. Add products and variants; verify images load from the configured disk.
7. Publish policy pages after owner review.
8. If XentriPay is live: enable it, register the webhook URL `https://your-domain/webhooks/xentripay`, set the same secret in both places, and send a provider test event.
9. Verify the scheduler (`ops:health` shows a recent monitor run) and queue worker are running.
10. Configure `MONITORING_ALERT_WEBHOOK` and confirm a test alert arrives (see `docs/MONITORING.md`).
11. Place a low-value real or sandbox order end to end and confirm the payment settles exactly once.

Administrator recovery: if an enrolled admin loses the authenticator and recovery codes, run `php artisan admin:reset-mfa <email>` from the server (interactive confirmation; `--force` for automation). It clears the TOTP secret, recovery codes and verified timestamp, invalidates that user's database sessions, and writes an audit entry. The admin must re-enroll at the next login.

## 10. Smoke tests after deploy

```bash
curl -I https://your-domain/up                 # 200
curl -I https://your-domain/health             # 200 {"status":"ok"}
curl -I https://your-domain/                   # 200, security headers
curl -I https://your-domain/admin              # 302 to /admin/login when signed out
curl -I https://your-domain/webhooks/xentripay # 404 when secret not configured (or 401 unsigned)
```

Then in a browser: browse catalog → add to cart → checkout → pay → confirm the order page shows the correct state. Sign in as admin, complete MFA if prompted, and confirm the order and payment are visible.

## 11. Rollback

1. Redeploy the previous release image/commit.
2. Run `php artisan migrate:rollback --step=1` **only** if the new release included a migration and you have verified it is safe to reverse. Never roll back data-destroying migrations blindly.
3. Clear caches: `php artisan optimize:clear`.
4. Restart queue workers.
5. Re-run smoke tests and `php artisan ops:preflight`.

For database restore, follow [BACKUP_GUIDE.md](../BACKUP_GUIDE.md) and take a fresh backup of the current state before restoring.

## 12. Deployment hardening checklist

- [ ] `APP_DEBUG=false`, `APP_ENV=production`
- [ ] `php artisan ops:preflight` passes with no FAIL rows (`--strict` after the environment is fully configured)
- [ ] HTTPS enforced; HSTS present; `APP_URL` is the public `https://` origin
- [ ] Unique `APP_KEY`; `SESSION_ENCRYPT=true`; secure, HTTP-only, `SameSite=lax` cookies
- [ ] `TRUSTED_HOSTS` set; `TRUSTED_PROXIES` explicit or direct container access blocked
- [ ] All administrators have completed TOTP enrollment; recovery codes stored offline
- [ ] Database TLS required (`DB_SSLMODE=require` where applicable)
- [ ] Private storage for proofs; persistent volume or S3 for public uploads
- [ ] Scheduler running (`ops:monitor` heartbeat recent) and queue worker running
- [ ] `MONITORING_ENABLED=true`; alert webhook configured and a test alert observed
- [ ] External uptime monitor targets `/health` and is verified
- [ ] Backups configured; restore drill recorded in `BACKUP_GUIDE.md`; production restore still operator-verified
- [ ] `composer audit --locked` and `npm audit` clean
- [ ] Webhook secrets configured and a provider test event accepted
- [ ] `npm run typecheck`, `npm run build`, and `php artisan test` pass on the release commit
- [ ] Admin accounts limited and unique
