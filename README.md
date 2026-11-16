# Rwanda handbag store MVP

This is a Laravel 13 application with a React/TypeScript storefront hydrated from a Laravel page payload. The storefront uses a clean, high-contrast, product-led layout, with normal browser page navigation. Product, variant, stock, order, payment, policy, and store-setting data are persisted in the database. Prices and order totals use integer RWF values, and the server recalculates totals at checkout.

## Requirements

- PHP 8.3+ with PDO SQLite or the PDO driver for the selected database, fileinfo, and GD with WebP support
- Composer 2
- Node.js and npm
- SQLite for local development, or MySQL/PostgreSQL for deployment

## Local setup

```powershell
composer install
Copy-Item .env.example .env
php artisan key:generate
npm install
php artisan migrate
php artisan db:seed --class=SettingsSeeder
php artisan db:seed --class=CategorySeeder
php artisan db:seed --class=ProductSeeder
php artisan db:seed --class=PolicyPageSeeder
php artisan storage:link
npm run build
php artisan serve
```

Set `DB_CONNECTION` and its matching connection values in `.env` before migrating if you are not using SQLite. The development product seeder creates clearly named sample products; replace their copy, prices, and photos before launch. The settings and policy seeders use visible placeholders and unpublished draft policies.

For frontend development, run `npm run dev` in a separate terminal. For a production build, use `npm run build`.

## Environment configuration

Laravel connection, filesystem, queue, and mail variables use the standard `.env` options. App-specific values:

| Variable | Purpose |
|---|---|
| `CATALOG_DISK` | Public disk for product and brand WebP images. Defaults to the local `public` disk; use the configured `s3` disk for object storage. |
| `PAYMENT_SANDBOX_ENABLED` | Enables the clearly labelled sandbox only in `local` and `testing`; it cannot be enabled in production. Defaults to `false`. |
| `PAYMENT_WEBHOOK_ENABLED` | Enables the generic signed webhook. Defaults to `false`; the endpoint returns 404 unless this is `true` and a secret is set. |
| `PAYMENT_WEBHOOK_SECRET` | HMAC-SHA256 secret for `POST /webhooks/payment`. Required when the generic webhook is enabled. |
| `XENTRIPAY_ENABLED` | Enables XentriPay collections (MTN MoMo, Airtel Money, card). Keep `false` until credentials are approved. |
| `XENTRIPAY_BASE_URL` | Provider API base URL; `https://merchant.test.xentripay.com` for sandbox. |
| `XENTRIPAY_API_KEY` | Server-side XentriPay API key. Never expose it to the frontend. |
| `XENTRIPAY_CHARGES_INCLUDED` | Must match the merchant contract: `true` deducts the fee from the customer amount, `false` adds it on top. |
| `XENTRIPAY_WEBHOOK_SECRET` | HMAC-SHA256 secret for `POST /webhooks/xentripay`. Polling works without it. |
| `SESSION_SECURE_COOKIE` | Set to `true` behind HTTPS in production. The application defaults this to secure cookies in production. |
| `SESSION_ENCRYPT` | Encrypts session payloads. Defaults to `true`. |
| `ADMIN_IDLE_TIMEOUT` | Minutes of admin inactivity before the session is destroyed. Default `60`. |
| `ADMIN_SESSION_LIFETIME` | Absolute admin session cap in minutes. Default `480`. |
| `ADMIN_NAME` | Optional one-time name for `php artisan admin:create`. |
| `ADMIN_EMAIL` | Optional one-time email for `php artisan admin:create`. |
| `ADMIN_PASSWORD` | Optional one-time password for `php artisan admin:create`; use a unique secret of at least 12 characters. Prefer the interactive prompt and remove temporary environment values after use. |

Use `APP_ENV=production` and `APP_DEBUG=false` in production. Do not put credentials in source control. Configure HTTPS, a production database, private storage, backups, and monitoring before launch.

## Admin access

Create the initial admin after migrations:

```powershell
php artisan admin:create
```

The command prompts for a name, email, and password, or reads the three `ADMIN_*` values from the process environment. There is no seeded administrator account. Admin pages require an authenticated user with the `admin` role; the role check is enforced server-side. Admin login is rate limited (5 attempts per email + IP per 10 minutes), and sessions expire after the configured idle and absolute limits.

To reset an administrator password:

```powershell
php artisan admin:reset-password
```

The reset command prompts twice, requires at least 12 characters, deletes existing database sessions for that user, and writes an audit log entry.

## Store management

- Products and variants are created under **Admin → Products**. Images are validated and converted to WebP.
- Store identity, confirmed delivery fees, delivery announcement, contact details, and manual MoMo configuration are under **Admin → Settings**.
- Privacy, terms, delivery, returns, and contact pages are editable under **Admin → Policies**. They are unpublished draft text until the owner reviews and publishes them.
- Sample catalog entries are development fixtures, not a production catalog.

Delivery fees must be explicitly confirmed before checkout can create an order. A zero fee can be used only when the owner has confirmed that delivery is free.

## Payments

### Manual MoMo

Manual MoMo is available as an alternative to XentriPay. The owner must enter a real pay code and explicitly enable manual verification in store settings. Customers receive the exact RWF amount and unguessable order reference, then can upload an image proof. Proofs are stored on the private local disk, re-encoded as WebP, and only available to authenticated admins. An admin must compare the proof with the merchant account and approve it; only that authenticated action marks the payment paid. Rejection leaves the order unpaid.

### Sandbox

Set `PAYMENT_SANDBOX_ENABLED=true` only in a local or test environment. The admin order page then exposes success, failure, pending, and expiry simulations, all labelled **SANDBOX — NOT REAL MONEY**. Sandbox event IDs are persisted for retry idempotency, and payment amount/currency/order values are matched before state changes.

### XentriPay

XentriPay collections are supported for MTN MoMo, Airtel Money, and card when `XENTRIPAY_ENABLED=true` and a server-side API key is configured. Amounts and currency (`RWF`) are taken from the server-side order, never from the browser. The scheduled `app:poll-xentripay` command reconciles pending payments every minute, and the customer-facing check button triggers an on-demand status check.

Signed provider webhooks are supported at `POST /webhooks/xentripay` using `X-Xentripay-Signature: sha256=<hmac>` over the raw request body. Settlement enforces provider/payment matching, amount and currency reconciliation, legal state transitions, and event idempotency, and serializes concurrent callbacks with a database row lock. Provider payloads are redacted before logging.

The generic `POST /webhooks/payment` endpoint is a disabled scaffold. It returns 404 unless `PAYMENT_WEBHOOK_ENABLED=true` and `PAYMENT_WEBHOOK_SECRET` is set, and it requires `X-Signature` (or `X-Webhook-Signature`) with a valid HMAC-SHA256 signature.

Never collect customer PINs or mark an order paid from a browser redirect.

## Orders and stock

Checkout accepts a session cart of variant IDs and quantities only. It reloads prices and available stock, computes totals on the server, creates an order/payment/item snapshot, and reserves stock within a database transaction. A unique idempotency key prevents duplicate orders. Orders are accessible to the customer through the random public reference only after phone verification in that session.

Unpaid orders expire after the configured interval. Run the scheduler in production:

```powershell
php artisan schedule:work
```

Use a persistent queue worker if asynchronous jobs are introduced:

```powershell
php artisan queue:work
```

The current MVP does not send email or WhatsApp messages.

## Deployment checklist

1. Configure production environment variables with `APP_DEBUG=false`, `APP_ENV=production`, HTTPS, a secure session cookie, session encryption, and a production database.
2. Deploy code and run `php artisan migrate --force`.
3. Configure `CATALOG_DISK` for public catalog image storage (the S3 disk is available); keep payment proofs on private storage. Run `php artisan storage:link` when using the local public disk.
4. Build assets with `npm ci` and `npm run build`.
5. Create the first admin interactively, then configure the store through the protected admin area.
6. Run `php artisan schedule:work` under a process manager (or configure the platform scheduler to run `php artisan schedule:run` each minute). The scheduler expires unpaid orders and reconciles XentriPay payments.
7. Configure database/file backups, log retention, monitoring, and an incident process.
8. Review and publish business/legal policies and confirm product photography, prices, stock, delivery areas/fees, contact channels, and payment instructions.

Customer records contain only fulfilment contact and delivery details. Define and implement a retention/deletion schedule with the business owner before launch; the MVP does not automatically purge order records.

See [docs/DEPLOYMENT.md](docs/DEPLOYMENT.md) for platform-specific steps, environment variables, rollback, and smoke tests.

## Security and operations documentation

- [docs/PRODUCTION_SECURITY_AUDIT.md](docs/PRODUCTION_SECURITY_AUDIT.md) — findings, remediation, verification, readiness classification
- [docs/FINAL_LAUNCH_CHECKLIST.md](docs/FINAL_LAUNCH_CHECKLIST.md) — launch conditions by verification status
- [docs/SECURITY.md](docs/SECURITY.md) — controls, secrets, MFA, webhook contract, known limitations
- [docs/MONITORING.md](docs/MONITORING.md) — operational events, alerts, health probes
- [docs/DEPLOYMENT.md](docs/DEPLOYMENT.md) — deployment, release commands, rollback
- [docs/INCIDENT_RESPONSE.md](docs/INCIDENT_RESPONSE.md) — severity levels and playbooks
- [docs/PAYMENT_VERIFICATION.md](docs/PAYMENT_VERIFICATION.md) — payment lifecycle evidence and sandbox E2E procedure
- [BACKUP_GUIDE.md](BACKUP_GUIDE.md) — backup, encryption, restore drills

## Validation commands

```powershell
php artisan test
php artisan route:list
php artisan ops:preflight
npm run typecheck
npm run build
composer audit --locked
npm audit
```

Feature tests cover catalog/product access, cart stock checks, server-priced checkout, idempotency binding, order lookup, manual proof review, sandbox webhook idempotency/amount matching, admin authorization, product management, draft policy visibility, webhook signature enforcement, admin session expiry, login throttling, mandatory admin MFA, trusted-proxy/host handling, monitoring and alerts, preflight validation, and payload field exposure. These tests are not a penetration test or a full browser/device accessibility audit.

## Known limitations and launch requirements

- Administrators must enroll in TOTP MFA; there is still a single `admin` role (no granular staff permissions).
- In-application monitoring exists, but the external alert webhook and uptime monitor must be configured and verified by an operator.
- An isolated backup restore drill is recorded; production restore verification is still required.
- A real provider sandbox payment lifecycle has not been completed; see [docs/PAYMENT_VERIFICATION.md](docs/PAYMENT_VERIFICATION.md).
- Owner-supplied brand assets, product photography/copy/prices, delivery rules, payment details, contact channels, and approved policy text are required.
- There is no automated browser/device E2E suite or verified LCP/INP/CLS measurement.
- Manual MoMo is a human review process, not an automatic payment integration.
- The generic payment webhook remains disabled until a real adapter is implemented.
