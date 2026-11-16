# Security Policy and Operations

This document describes how the application protects customer, order, and payment data, and what operators must do to keep it that way. It complements [PRODUCTION_SECURITY_AUDIT.md](PRODUCTION_SECURITY_AUDIT.md).

## 1. Supported versions

| Version | Supported |
|---|---|
| Current `main` deployment | Yes |
| Older deployments | No — redeploy from `main` and rerun the migration set |

## 2. Reporting a vulnerability

Do **not** open a public issue for a suspected vulnerability. Send a private report to the address configured in the `contact_email` store setting (or the owner's direct address), including:

- affected URL/feature,
- reproduction steps,
- impact and data involved,
- any proof-of-concept (never include real customer data).

Target acknowledgement: 2 business days. Target remediation for confirmed high/critical issues: as soon as practical, with a coordinated disclosure date.

## 3. Secrets management

- All credentials live in environment variables. Never commit `.env`, `composer.auth.json`, database dumps, or provider keys.
- The XentriPay API key and webhook secrets are server-side only. They are never sent to the browser or embedded in Vite variables (`VITE_*` must stay non-secret).
- Administrator TOTP secrets are encrypted at rest with Laravel's encrypter (`two_factor_secret` is an encrypted cast). Recovery codes are stored only as bcrypt hashes and are shown once. Neither value is written to logs or audit entries.
- The monitoring alert webhook URL is itself a secret (it can be used to post messages). Store it only in the platform environment, never in the repository.
- Rotate immediately if a secret may have leaked, then redeploy. Rotating a payment key invalidates outstanding provider calls; check in-flight payments first.
- Use a unique `APP_KEY` per environment. Changing it invalidates encrypted sessions, TOTP secrets and any encrypted payloads — administrators must re-enroll MFA after an `APP_KEY` rotation.
- `PAYMENT_SANDBOX_ENABLED` must remain `false` in production; sandbox simulations are hard-disabled outside `local`/`testing`.

Required production variables (values omitted deliberately):

| Variable | Requirement |
|---|---|
| `APP_ENV` | `production` |
| `APP_DEBUG` | `false` |
| `APP_URL` | Exact HTTPS origin (used by CORS and URL generation) |
| `TRUSTED_HOSTS` | Exact public hostname(s); do not leave the default implicit value unverified |
| `TRUSTED_PROXIES` | Empty for Railway with direct access blocked, or explicit platform CIDRs |
| `MONITORING_ENABLED` | `true` in production |
| `MONITORING_ALERT_WEBHOOK` | Secret HTTPS webhook; presence does not prove delivery |
| `LOG_CHANNEL` | `stderr` in containers |
| `APP_KEY` | Strong, unique, generated with `php artisan key:generate` |
| `SESSION_SECURE_COOKIE` | `true` behind HTTPS |
| `SESSION_ENCRYPT` | `true` |
| `ADMIN_IDLE_TIMEOUT` | Minutes of inactivity before admin logout (default 60) |
| `ADMIN_SESSION_LIFETIME` | Absolute admin session cap in minutes (default 480) |
| `PAYMENT_WEBHOOK_ENABLED` | `false` unless a real generic adapter is configured |
| `PAYMENT_WEBHOOK_SECRET` | High-entropy secret when the generic webhook is enabled |
| `XENTRIPAY_ENABLED` | `true` only when credentials are live-approved |
| `XENTRIPAY_API_KEY` | Server-side key |
| `XENTRIPAY_WEBHOOK_SECRET` | High-entropy secret registered with the provider |
| `XENTRIPAY_BASE_URL` | `https://merchant.test.xentripay.com` for sandbox; provider's production URL for live |

## 4. Authentication, MFA and sessions

- Admin authentication is session-based, server-enforced, and limited to users with `role = admin`. Customer accounts are not required and MFA applies to administrators only.
- Five failed attempts per email+IP within ten minutes trigger a lockout response. Failures are logged with a hashed email, never the raw password.
- Successful login regenerates the session ID. Logout invalidates the session and regenerates the CSRF token.
- **MFA is mandatory for administrators.** A password-only login leaves the account unauthenticated for the admin area and redirects to enrollment (first time) or a TOTP challenge:
  - TOTP (RFC 6238, 6 digits, 30-second period, one-step drift) via `pragmarx/google2fa`; QR enrollment with manual-secret fallback.
  - The secret is not activated until the user confirms a valid code from the authenticator; `two_factor_confirmed_at` gates all admin access.
  - TOTP replay is blocked by storing the last accepted timestep (`two_factor_last_used_timestep`).
  - Eight single-use recovery codes are generated at activation, shown once, and stored as bcrypt hashes. A recovery code is removed atomically after use.
  - Verification and enrollment confirmation are rate limited (5 attempts/minute per session+IP) with a generic failure message.
  - Changing MFA settings (enrollment, recovery-code regeneration, disabling) requires the current password and invalidates the user's other database sessions.
  - `two_factor_secret` and `two_factor_recovery_codes` are encrypted at rest; codes are additionally hashed. Nothing is logged.
  - Recovery when a device is lost: `php artisan admin:reset-mfa <email>` (server access required; invalidates that user's sessions and writes an audit entry).
- Admin sessions expire after `ADMIN_IDLE_TIMEOUT` of inactivity and after `ADMIN_SESSION_LIFETIME` absolutely, whichever comes first. Expiry destroys the session. Security-sensitive changes also invalidate other sessions.
- Initial admin creation: `php artisan admin:create`. Password reset: `php artisan admin:reset-password`. Both are interactive by default and refuse short passwords (<12 characters). The reset command also deletes database sessions for that user and writes an audit log entry. Neither command bypasses MFA: the account must still enroll and verify before reaching `/admin`.

## 5. Authorization model

- Every `/admin/*` route is behind `auth` + `admin` middleware.
- Sensitive actions re-check the acting user (for example, proof review and payment approval).
- Customers can view an order only after phone verification in the current session, using the random public reference. The reference alone is not sufficient.
- Tests assert allow/deny behavior for admin proof download and cross-customer idempotency replay.

If staff roles are introduced, add Laravel policies/gates per resource rather than branching on role names in controllers.

## 6. Payment security

### Manual MoMo
- Merchant pay code and manual-verification toggle are configured by an admin.
- Customers upload proof to private storage; images are validated and re-encoded.
- Only an authenticated admin approval marks a payment paid. Rejection leaves it unpaid.
- The customer is never asked for a PIN in the app.

### XentriPay (MTN MoMo, Airtel Money, card)
- Collection is initiated server-side with the server-held API key.
- Amount and currency are fixed server-side from the order; the client cannot set them.
- Provider status is polled by the scheduled command and can be checked on demand.
- Settlement (`SettleXentriPayPayment`) enforces:
  - provider/payment correspondence,
  - paid status requires a non-null amount and `RWF`,
  - amount matches the order total,
  - legal state transitions only,
  - event idempotency (`payment_events.event_id`) so replays do not double-apply,
  - row locking so concurrent callbacks serialize.
- Provider payloads are redacted before logging (phone, name, email, card fields).
- Gateway URLs must use HTTPS in production and must not resolve to localhost/private/reserved addresses.

### Webhooks
Both webhook endpoints are CSRF-exempt by necessity and rely on signatures instead.

| Endpoint | Secret | Header | Behavior |
|---|---|---|---|
| `POST /webhooks/xentripay` | `XENTRIPAY_WEBHOOK_SECRET` | `X-Xentripay-Signature` (`sha256=<hex>` or bare hex) | 404 if secret unset; 401 on bad signature; otherwise verifies and settles |
| `POST /webhooks/payment` | `PAYMENT_WEBHOOK_SECRET` | `X-Signature` or `X-Webhook-Signature` | 404 unless enabled with secret; 401 on bad signature; reconciles provider/amount/currency/order total |

Signature is `HMAC-SHA256(raw_request_body, secret)`, compared in constant time.

**Operational rules**
- Never mark an order paid from a browser redirect.
- Never trust amount or currency supplied only by the client.
- Keep webhook secrets distinct from API keys.
- Monitor 401 rates on webhook routes; sustained failures indicate misconfiguration or probing.

## 7. Data protection

- Order records contain fulfilment contact, address, and payment metadata. No card numbers or PINs are stored.
- Sensitive fields (`idempotency_key`, internal IDs, provider references, proof paths, reviewer IDs) are hidden from serialized page payloads.
- Payment proofs live on the private disk and are served only through admin-authorized routes.
- Sessions are encrypted at rest in the session store.
- Responses for admin, order, cart, and checkout pages are marked `Cache-Control: no-store`.
- Define a retention period with the business owner and delete expired orders/proofs on schedule. Automatic purge is **not** implemented.

## 8. HTTP security headers

Set by `App\Http\Middleware\SecurityHeaders` on every web response:

- `X-Content-Type-Options: nosniff`
- `X-Frame-Options: DENY`
- `Referrer-Policy: strict-origin-when-cross-origin`
- `Permissions-Policy: camera=(), microphone=(), geolocation=()`
- `Cross-Origin-Opener-Policy: same-origin-allow-popups`
- `Cross-Origin-Resource-Policy: same-origin`
- `X-Permitted-Cross-Domain-Policies: none`
- `Cache-Control: no-store, private` on private pages
- In production only: `Strict-Transport-Security` and a CSP with `script-src 'self'`, `object-src 'none'`, `frame-ancestors 'none'`, `upgrade-insecure-requests`. `style-src` allows `'unsafe-inline'` because React emits inline style attributes; scripts remain first-party only.

When adding new third-party embeds, extend the CSP deliberately and document why.

## 9. Dependency and supply-chain management

```bash
composer audit --locked
npm audit
```

CI runs both and fails on known advisories. Dependabot/Renovate is recommended but not configured. Pin lockfiles and review lockfile diffs in pull requests.

## 10. Logging and monitoring

- Application logs: Laravel log channel. In containers use `LOG_CHANNEL=stderr` so logs reach the platform collector.
- In-application monitoring persists structured `operational_events` (no request bodies, payloads, emails or phone numbers) and raises deduplicated alerts over an optional Slack-compatible webhook. See [MONITORING.md](MONITORING.md).
- Monitored signals: uncaught 5xx errors, failed queue jobs, stuck pending payments, webhook signature/processing failures, payment reconciliation mismatches, failed admin sign-in/MFA, and browser errors reported by the frontend.
- `GET /health` returns 200 when the database and cache are reachable, 503 otherwise. `php artisan ops:health` prints an operator summary; `php artisan ops:monitor` evaluates thresholds (scheduled every five minutes).
- Recommended external alerts: `/health` non-200, sustained 5xx, failed-job backlog, webhook 401/404 spikes, login-failure bursts, disk/database saturation, backup job failure.
- Do not log raw request bodies for payment endpoints outside the redaction path.

## 11. Secure development practices

- Run `php artisan test`, `composer audit --locked`, `npm audit`, `npm run typecheck`, `npm run build`, and `vendor/bin/pint --test` before merging.
- Run `php artisan ops:preflight` against staging/production configuration before a release.
- Add regression tests for every security fix (see `tests/Feature/SecurityHardeningTest.php`, `AdminMfaTest.php`, `ProxyTrustTest.php`, `MonitoringTest.php`).
- Validate all input at the boundary; never interpolate user input into SQL, shell, or HTML.
- Use server-side authorization for every new route; do not rely on hidden UI.
- Migrations must be reversible and additive. Do not drop or rewrite customer/order/payment columns without a documented backup and rollback plan.
- Keep `APP_DEBUG=false` and verbose error output off in production.

## 12. Known security limitations

- Single admin role; no per-resource staff permissions. Every verified administrator can do everything.
- The rightmost-`X-Forwarded-For` fallback assumes direct container access is blocked; this is an infrastructure control the application cannot verify from inside.
- Monitoring has no external vendor integration. Alert delivery to a real destination is configured in code but unverified until an operator sets `MONITORING_ALERT_WEBHOOK` and observes a test alert.
- No external uptime monitor is created by this repository.
- Built-in log shipping/PII retention tooling is not included beyond event pruning; logs rely on the platform collector.
- No automated browser E2E, accessibility, load or penetration testing has been executed.
- The generic payment webhook remains a disabled scaffold; only XentriPay has a concrete provider integration.
- A true parallel-request test for payment settlement/retry was not executed (SQLite test database); correctness relies on transactions and row locks.
- Production backup restoration has not been independently verified; only an isolated drill with synthetic data is recorded.
