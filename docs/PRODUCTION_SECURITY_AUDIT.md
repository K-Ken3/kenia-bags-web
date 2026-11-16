# Production Security Audit

**Application:** Isimbi Mart (Laravel 13 storefront + custom React 19 frontend)
**Audit date:** 2026-11-12 (final hardening pass)
**Auditor:** Automated engineering review (agent-assisted, code-level)
**Scope:** Application code, routes, database schema, authentication, MFA, authorization, payment flows, webhooks, file uploads, configuration, trusted proxies/hosts, monitoring, backup/restore tooling, dependencies, deployment assumptions, frontend type/build
**Classification:** **CONDITIONALLY READY**

> This classification means no known critical or high-severity code defects remain open in the reviewed scope, the automated suite passes, and the launch blockers that can be closed in code have been closed. It is **not** a certification. Items that require an external account, a real provider handset, or production infrastructure access remain open in [§6 Residual risk](#6-residual-risk-and-open-items) and are also listed in [FINAL_LAUNCH_CHECKLIST.md](FINAL_LAUNCH_CHECKLIST.md).

---

## 1. Executive summary

The application was reviewed end to end: public storefront, cart/checkout, order lookup, manual MoMo proof review, XentriPay collections, webhooks, admin area, storage, session handling, security headers, database indexes, and dependency tree.

The highest-impact issues were:

1. **An unauthenticated generic payment webhook could mark orders paid.** The endpoint accepted unsigned POSTs and settled payments from the request body. **Fixed:** it now fails closed (404) unless explicitly enabled with a configured secret, and it requires a valid HMAC-SHA256 signature over the raw body before any state change.
2. **Admin authentication had no brute-force lockout and no session lifetime controls.** **Fixed:** role-constrained credential attempt, per-email+IP rate limiting, generic failure message, session regeneration, idle and absolute session expiry enforced server-side.
3. **Checkout idempotency keys were not bound to the customer's phone number.** A replayed key could return another customer's order to the attacker's session. **Fixed:** keys are honored only when the submitted phone normalizes to the phone stored on the existing order.
4. **XentriPay retry could create duplicate payments under concurrency.** **Fixed:** retry runs in a transaction with a row lock and re-validates state.
5. **Nine Composer security advisories in Symfony components.** **Fixed:** affected packages updated; `composer audit --locked` is now clean.
6. **Admin proof download was broken by a non-existent attribute check**, and admin sessions could live indefinitely. **Fixed.**

The remaining open items are operational or external-service verifications (alert destination, uptime account, real provider sandbox transaction, production restore verification, browser E2E/pen test), not silent code flaws.

### Final hardening pass (2026-11-12)

The follow-up pass closed the largest remaining launch blockers:

1. **Trusted proxy handling and host validation.** Removed the blanket `trustProxies('*')`. The app now uses explicit `TRUSTED_PROXIES` when the platform publishes ranges; otherwise `NormalizeProxyHeaders` trusts only the rightmost `X-Forwarded-For` value. `EnforceTrustedHosts` rejects unexpected `Host` headers. Covered by `tests/Feature/ProxyTrustTest.php`.
2. **Mandatory administrator MFA.** TOTP with QR enrollment and confirmation before activation, replay protection, eight single-use hashed recovery codes, password reauthentication for security changes, session invalidation, rate-limited verification, encrypted secrets, and an audited reset command. Covered by `tests/Feature/AdminMfaTest.php`.
3. **Production monitoring.** Persisted operational events, deduplicated alerts, 5xx/exception reporting, failed-job/stuck-payment/webhook/reconciliation/admin-failure signals, `/health`, scheduler heartbeat, event pruning, and an operator summary. Covered by `tests/Feature/MonitoringTest.php`.
4. **Backup restore drill.** An isolated PostgreSQL 18.3 cluster was restored from a real `pg_dump` archive with checksum, row-count, constraint and relationship verification. Recorded in [BACKUP_GUIDE.md](../BACKUP_GUIDE.md).
5. **Payment edge cases.** Added wrong-currency, unknown-reference, late-failure-after-success, failure-then-success, post-expiry settlement, and retry-while-pending coverage.
6. **Release gate and static checks.** Added `php artisan ops:preflight` (fails unsafe production configuration without printing secrets), a frontend `tsconfig.json` + type-check script, CI type-check, and normalized PHP formatting across the application.

---

## 2. Method and evidence

| Activity | Result |
|---|---|
| Baseline test suite before changes | 39 tests passed / 257 assertions |
| Mid-audit suite | 48 tests passed / 303 assertions |
| **Final full suite** | **91 tests passed / 505 assertions** |
| New focused security tests | `AdminMfaTest.php` (12), `ProxyTrustTest.php` (7), `MonitoringTest.php` (12), `PreflightTest.php` (6), `XentriPayTest.php` (20), `SecurityHardeningTest.php` (8) |
| Frontend type check | `npm run typecheck` (`tsc -p tsconfig.json --noEmit`) — clean |
| Frontend production build | `npm run build` succeeded (built in ~6 s; main bundle 227.13 kB / 71.36 kB gzip) |
| PHP dependency audit | `composer audit --locked` — no advisories |
| JS dependency audit | `npm audit` — 0 vulnerabilities |
| Style check | `vendor/bin/pint --test app config routes tests database` — 159 files clean |
| Production configuration gate | `php artisan ops:preflight` implemented and tested; run locally against non-production config (0 FAIL, 3 expected warnings) |
| Backup restore drill | Isolated PostgreSQL 18.3 restore with checksums, row counts and constraints verified — see [BACKUP_GUIDE.md](../BACKUP_GUIDE.md) |
| Real provider sandbox connectivity | Read-only authenticated status probe returned HTTP 400 "No collection found" (host reachable, key accepted) — **not** a payment transaction |
| Manual code review | Controllers, middleware, models, providers, migrations, routes, config |
| Penetration testing (OWASP ZAP / Burp) | **Not executed** |
| SAST (SonarQube) / SCA (Snyk) | **Not executed** (Composer/npm built-in audits were used) |
| Real provider live transaction | **Not executed** (automated fakes only; sandbox read-only probe described above) |
| Load / performance testing | **Not executed** |
| Browser E2E / accessibility audit | **Not executed** |

No production customer data was accessed or modified. All tests ran against an isolated test database.

---

## 3. Findings and remediation

Severity uses a standard five-band scale. "Fixed" means code was changed and covered by an automated test where feasible.

### 3.1 Critical

| ID | Finding | Status |
|---|---|---|
| C-1 | `POST /webhooks/payment` accepted unauthenticated, unsigned requests and could confirm arbitrary payments from the request body. | **Fixed** — endpoint returns 404 unless `PAYMENT_WEBHOOK_ENABLED=true` and `PAYMENT_WEBHOOK_SECRET` is set; signature is compared with `hash_equals` over the raw body; provider must match the payment; amount, currency, and order total are reconciled before settlement. |

### 3.2 High

| ID | Finding | Status |
|---|---|---|
| H-1 | Admin login had no brute-force protection, no role-constrained attempt, and leaked distinguishable failure behavior. | **Fixed** — `Auth::attempt([...credentials, 'role' => 'admin'])`, 5 attempts / 600 s per email+IP, generic message, failed attempts logged with hashed email and IP, limiter cleared on success, session regenerated. |
| H-2 | Admin sessions had no idle or absolute timeout; a stolen session remained valid indefinitely. | **Fixed** — `EnsureAdmin` enforces `ADMIN_IDLE_TIMEOUT` (default 60 min) and `ADMIN_SESSION_LIFETIME` (default 480 min), logs out and invalidates the session on expiry. |
| H-3 | Checkout idempotency key could replay another customer's order (order disclosure / denial of checkout). | **Fixed** — key is only honored when the submitted phone normalizes to the stored order phone; otherwise validation fails. Verified by test. |
| H-4 | Concurrent XentriPay retry requests could create duplicate payment records. | **Fixed** — retry runs inside a transaction with `Order::lockForUpdate()` and re-checks paid status, order status, expiry, and pending payment. |
| H-5 | Nine Composer advisories (Symfony serializer/http-foundation/http-kernel/routing/yaml and contracts). | **Fixed** — updated to patched releases; audit clean. |
| H-6 | Admin proof download always returned 403 due to a non-existent `is_admin` attribute. Availability defect with a security-adjacent cause (authorization logic not type-checked). | **Fixed** — uses `isAdmin()`; covered by an allow/deny test. |

### 3.3 Medium

| ID | Finding | Status |
|---|---|---|
| M-1 | Sensitive server fields (`user_id`, `idempotency_key`, internal addresses/totals, provider references, reviewer IDs, proof paths) were serialized into page payloads. | **Fixed** — `$hidden` added to `Order`, `Payment`, `PaymentProof`; payload assertions added to tests. |
| M-2 | Security headers were incomplete (no COOP/CORP, no cache policy on authenticated pages, CSP not scoped). | **Fixed** — COOP, CORP, `X-Permitted-Cross-Domain-Policies`, `Cache-Control: no-store` on admin/order/cart/checkout, CSP with strict `script-src`, `frame-ancestors 'none'`, HSTS in production. |
| M-3 | Session cookies were not encrypted by default and production HTTPS was not forced at the URL layer. | **Fixed** — `SESSION_ENCRYPT=true` default, secure cookies in production, `URL::forceScheme('https')` in production. |
| M-4 | CORS configuration was absent/unbounded. | **Fixed** — `config/cors.php` limits paths to `api/*` and `sanctum/csrf-cookie`, single allowed origin from `APP_URL`, explicit methods/headers, credentials disabled. |
| M-5 | Payment provider payloads were logged with customer PII (phone, name, email, card fields). | **Fixed** — recursive redaction before logging. |
| M-6 | Provider gateway URL was accept-any-string; risk of SSRF/misconfiguration. | **Fixed** — HTTPS required in production; localhost/private/reserved hosts rejected. |
| M-7 | Modifying a checkout order was possible when only one of two phone fields was supplied. | **Fixed** — both phone fields normalized and compared on idempotency reuse. |
| M-8 | Payment status mapping occurred before amount validation, so a "paid" status without an amount could be processed. | **Fixed** — paid transitions require a non-null matching amount and `RWF` currency; covered by a test. |
| M-9 | Generic webhook sanitization and provider/payment binding were missing. | **Fixed** — provider is sanitized and must match the payment record. |

### 3.4 Low / informational

| ID | Finding | Status |
|---|---|---|
| L-1 | Product/category mutations had no audit trail. | **Fixed** — `product.created/updated/archived`, `category.created/updated/deleted` audit entries. |
| L-2 | Missing indexes on payment, proof, order-item, and audit-log lookups. | **Fixed** — reversible migration `2026_11_12_000000_add_security_and_performance_indexes.php`. |
| L-3 | No admin password-recovery path. | **Fixed** — `php artisan admin:reset-password` (interactive, ≥12 chars, invalidates DB sessions, writes audit log). |
| L-4 | Duplicate/esoteric stub files (`app/Contracts/Contracts/*`, `app/Enums/Enums/*`, empty requests/rules/policies/jobs). | **Open (cleanup)** — harmless but confusing; not removed to avoid unrelated churn. |
| L-5 | README claimed live payments/webhooks were entirely unimplemented, which is stale now that XentriPay exists. | **Fixed** — README updated. |
| L-6 | `backup-db.sh` did not quote variables, did not fail fast, and passed DB passwords through process arguments. | **Fixed** — `set -euo pipefail`, quoted expansion, `PGPASSWORD`/`MYSQL_PWD` environment variables, trap-based cleanup of partial dumps. |

### 3.5 Final hardening findings

| ID | Finding | Status |
|---|---|---|
| T-1 | Proxy trust was blanket (`at: '*'`), so any direct client could spoof `X-Forwarded-For`/`X-Forwarded-Proto`. | **Fixed** — explicit `TRUSTED_PROXIES` or rightmost-XFF normalization; only safe with direct access blocked (documented, tested). |
| T-2 | No `Host` header allow-list; host-header poisoning could affect URL generation/cache keys. | **Fixed** — `EnforceTrustedHosts` rejects unexpected hosts (HTTP 400). |
| T-3 | Administrators had password-only access. | **Fixed** — mandatory TOTP MFA with enrollment confirmation, replay protection, recovery codes, rate limiting, and session invalidation. |
| T-4 | MFA secrets/recovery codes had no defined storage protection. | **Fixed** — encrypted casts; recovery codes bcrypt-hashed and single-use. |
| T-5 | No application-level error tracking, alerting, or operator health surface. | **Fixed in code** — `operational_events`, alert webhook, `/health`, `ops:monitor`, `ops:health`, `ops:prune-events`. External delivery remains unverified. |
| T-6 | Failed/late/duplicate payment edge cases were only partially tested. | **Fixed** — added wrong currency, unknown reference, late failure, failure→success, post-expiry, and retry-while-pending tests. |
| T-7 | No production configuration release gate. | **Fixed** — `ops:preflight` fails unsafe settings and never prints secrets. |
| T-8 | Frontend had no TypeScript project or type-check step. | **Fixed** — `tsconfig.json`, `resources/js/vite-env.d.ts`, `npm run typecheck`, CI step. |
| T-9 | Backup restore procedure was documented but never exercised. | **Fixed for tooling/schema** — isolated PostgreSQL drill completed; production restore still requires operator verification. |

---

## 4. Control coverage

| Control area | Implementation |
|---|---|
| Authentication | Session auth for admins; role-constrained attempt; rate limited; session regeneration on login/logout; idle/absolute expiry; mandatory TOTP MFA with confirmation, replay protection and recovery codes. |
| Authorization | Server-side `auth` + `admin` middleware on every admin route; MFA-confirmation and session-verification gates inside `EnsureAdmin`; `isAdmin()` checks inside sensitive actions; customer order/proof access bound to session phone verification and public reference. |
| Host / proxy trust | `TRUSTED_HOSTS` allow-list; explicit proxies or rightmost-XFF normalization; scheme derived from the platform hop; spoofed leftmost XFF ignored. |
| MFA | TOTP (RFC 6238), encrypted secret, bcrypt-hashed single-use recovery codes, 5/min verification throttle, last-used-timestep replay block, password reauthentication for changes, audit entries, `admin:reset-mfa`. |
| CSRF | Laravel CSRF on all state-changing web routes; only `webhooks/*` exempt (signature-protected instead). |
| Password storage | Bcrypt (`BCRYPT_ROUNDS=12`). |
| Mass assignment | Explicit `$fillable` on models; validated FormRequests/controller validation. |
| Output encoding | React escapes by default; page payload JSON is HTML-safe via Blade `@json`-style encoding path; CSP restricts scripts. |
| File uploads | Image validation, re-encoding to WebP, proof images on private disk, admin-only download. |
| Webhooks | HMAC-SHA256 over raw body, constant-time compare, provider/payment binding, amount/currency reconciliation, event idempotency, state-transition guards. |
| Money | Integer RWF; server recomputes totals; amount/currency asserted on settlement; row locks on settlement and retry. |
| Secrets | Server-only env vars; no secrets in frontend bundle; `.env` not tracked. |
| Headers | HSTS, CSP, X-Frame-Options, nosniff, Referrer-Policy, Permissions-Policy, COOP, CORP, no-store on private pages. |
| Logging | Structured Laravel logs; sensitive payload redaction; admin login/MFA failures and webhook rejections logged; monitoring events scrub e-mail, phone and token values. |
| Monitoring | `operational_events` store, deduplicated alert webhook, 5xx reporting, failed-job/stuck-payment/webhook/reconciliation/admin-failure signals, `/health`, scheduler heartbeat, retention pruning. External alert destination **unverified**. |
| Release gate | `ops:preflight` validates production env, cookies, hosts, monitoring, payment flags, storage, DB reachability and admin MFA enrollment without printing secrets. |
| Dependencies | `composer audit` and `npm audit` clean at audit time; CI job added. |

---

## 5. Verification performed

Commands executed and observed on 2026-11-12:

```
php artisan test                              → 91 passed (505 assertions)
npm run typecheck                             → exit 0 (tsc -p tsconfig.json --noEmit)
npm run build                                 → succeeded (main bundle 227.13 kB, 71.36 kB gzip)
composer audit --locked                       → no advisories
npm audit --audit-level=high                  → 0 vulnerabilities
vendor/bin/pint --test app config routes tests database → 159 files clean
php artisan ops:preflight                     → 0 FAIL, 3 WARN locally (not production)
```

Focused regression tests assert:

- Generic webhook is disabled by default and cannot settle a payment.
- A signed webhook with an invalid signature is rejected; a valid signature settles the order.
- An idempotency key cannot expose another customer's order.
- Admin proof download is allowed for admins and denied for customers.
- Admin idle expiry redirects to login and logs the session out.
- Repeated admin login failures are rate limited (5 attempts, then HTTP 429).
- Administrators cannot reach `/admin` before MFA confirmation; valid codes admit, invalid/reused codes and recovery-code reuse are rejected; security changes require the password and revoke other sessions.
- Rightmost `X-Forwarded-For` is trusted while a spoofed leftmost value is ignored; `X-Real-IP` fallback works; unexpected `Host` headers are rejected.
- 5xx errors are recorded without PII; 4xx validation/auth errors are not alerted; `/health` reports degraded on dependency failure.
- Payment webhooks reject wrong currency, unknown references and missing amounts; a late failure cannot downgrade a paid order; a payment after expiry does not auto-confirm or consume released stock.
- `ops:preflight` fails unsafe production settings and never echoes key material.
- Order/payment JSON omits `idempotency_key`, `user_id`, `provider_reference`, `verified_by`.
- Production responses include COOP/CORP/no-store and a CSP that permits only first-party scripts.

---

## 6. Residual risk and open items

These items are **not** code defects introduced or left silently. They require an external account, real provider access, production infrastructure access, or a business decision. Each one has an exact resolution in [FINAL_LAUNCH_CHECKLIST.md](FINAL_LAUNCH_CHECKLIST.md).

| ID | Item | Severity | Required action |
|---|---|---|---|
| R-1 | Alert delivery to an external destination is unverified. The code records events and posts to `MONITORING_ALERT_WEBHOOK`, but no real webhook has been configured or observed. | High (operational) | Create the Slack/Mattermost webhook, set `MONITORING_ENABLED=true` and the URL, trigger the documented test alert, and confirm receipt. |
| R-2 | No external uptime monitor is configured. | Medium | Add an uptime check against `https://<domain>/health` and verify a deliberate non-200 raises an alert. |
| R-3 | No real provider sandbox transaction has been completed. Automated tests use HTTP fakes; the read-only probe only proves reachability and key acceptance. | High | Use a sandbox handset/card and staging deployment to complete `docs/PAYMENT_VERIFICATION.md` §3, including webhook registration and replay. |
| R-4 | Production backup restoration is not independently verified. An isolated PostgreSQL drill with synthetic data succeeded. | High | Perform the restore drill against a production-like backup in an isolated database and record it in `BACKUP_GUIDE.md`. Never restore over production. |
| R-5 | Platform network isolation is unverified. The rightmost-XFF fallback is safe only if the container cannot be reached directly. | High (operational) | Confirm Railway exposes no public TCP proxy/port for the app and that `Host` spoofing is rejected; otherwise configure explicit `TRUSTED_PROXIES`. |
| R-6 | Single `admin` role; no granular staff RBAC. | Medium | Add policies/roles if staff accounts will be created. Today every verified admin can do everything. |
| R-7 | No browser E2E, accessibility, load, or external penetration testing. | Medium | Add Playwright smoke tests and schedule an external penetration test before high volume. |
| R-8 | No refund workflow; manual reconciliation only. | Medium (business) | Define the refund policy and an auditable admin process before accepting cards at scale. |
| R-9 | True parallel-request concurrency was not tested (SQLite test DB); settlement/retry protections are covered sequentially. | Low | Run a PostgreSQL staging test with two simultaneous retries/webhooks. |
| R-10 | Generic `/webhooks/payment` remains a scaffold. | Low | Keep disabled; use provider-specific endpoints or implement a real adapter with the same signature guarantees. |
| R-11 | No customer accounts/password reset. | Info | By design for guest checkout; order lookup uses reference + phone verification. |
| R-12 | Cleanup of unused stub directories (`app/Contracts/Contracts/*`, `app/Enums/Enums/*`, empty requests/rules/policies/jobs) was not performed. | Info | Remove in a dedicated cleanup change if desired; harmless. |

**No known critical or high code vulnerabilities remain open.** R-1 through R-5 are launch conditions that cannot be closed from the repository alone: they require an operator with access to external accounts and production infrastructure.

Items resolved since the initial audit: mandatory admin MFA (was R-1), proxy trust and host validation (was R-3), in-application monitoring and alerting (was R-4, external destination still pending), and a restore drill (was R-5, production verification still pending).

---

## 7. Readiness decision

**CONDITIONALLY READY.**

Every code-level mandatory control requested for this pass is implemented and covered by automated tests. The classification remains conditional because the following mandatory launch conditions require external resources or production access and could not be independently verified from this environment:

1. Configure `MONITORING_ALERT_WEBHOOK` and observe a real test alert; configure an external uptime check against `/health`.
2. Verify platform network isolation (no direct container access) and `TRUSTED_HOSTS` rejection in production.
3. Complete one real sandbox payment lifecycle including signed webhook delivery and replay, per `docs/PAYMENT_VERIFICATION.md`.
4. Perform and record a production-like backup restore (isolated database, never over production).
5. Deploy with `ops:preflight --strict` passing and all administrators enrolled in MFA.

Once these are completed and recorded, rerun this audit's verification commands and update this document. Until then, do not describe the application as PRODUCTION READY.
