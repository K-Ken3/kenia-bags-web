# Final Launch Checklist

**Status date:** 2026-11-12
**Application:** Isimbi Mart (Laravel 13 + React 19)
**Overall classification:** **CONDITIONALLY READY**

This checklist separates what is proven from what is merely configured. Do not upgrade the classification to **PRODUCTION READY** until every item in categories 2 and 4 is complete and independently verified. No deployment was performed during this review, and no production credentials or customer data were used.

Legend for severity: **Critical** blocks any live payment handling · **High** blocks launch · **Medium** should be resolved before meaningful volume · **Low/Info** is acceptable with a recorded owner decision.

---

## 1. Implemented and tested in this repository

Evidence was produced by commands actually executed on 2026-11-12. "Tested" means automated coverage exists and passed; it does not mean an external service was contacted.

| # | Control | Evidence | Status |
|---|---|---|---|
| 1.1 | Trusted proxy handling: explicit `TRUSTED_PROXIES` or rightmost-`X-Forwarded-For` normalization; spoofed leftmost values ignored; `X-Real-IP` fallback; scheme detection | `tests/Feature/ProxyTrustTest.php` (7 tests) | Implemented + tested |
| 1.2 | Trusted `Host` allow-list rejects unexpected hosts | `EnforceTrustedHosts`, `ProxyTrustTest` | Implemented + tested |
| 1.3 | Mandatory administrator TOTP MFA: QR enrollment, confirmation before activation, invalid/reused code rejection, recovery-code single use, password reauthentication, session invalidation, rate limiting | `tests/Feature/AdminMfaTest.php` (12 tests) | Implemented + tested |
| 1.4 | MFA secrets encrypted at rest; recovery codes bcrypt-hashed; no secrets in logs or audit entries | `User` encrypted casts, `AdminMfaService`, `AdminMfaTest` | Implemented + tested |
| 1.5 | MFA recovery command (`admin:reset-mfa`) clears secret/codes and DB sessions, writes audit entry | `app/Console/Commands/ResetAdminMfa.php` | Implemented + tested |
| 1.6 | No admin-area access before MFA confirmation; alternative login routes cannot bypass the gate | `EnsureAdmin`, `AdminLoginController`, `AdminMfaTest` | Implemented + tested |
| 1.7 | Payment webhook signature enforcement, amount/currency/reference reconciliation, duplicate replay, out-of-order and post-expiry handling, retry-while-pending rejection | `tests/Feature/XentriPayTest.php` (20 tests) | Implemented + tested |
| 1.8 | Monitoring event store, PII scrubbing, deduplicated alerts, 5xx reporting, failed jobs, stuck payments, webhook/reconciliation/admin failures, `/health`, `ops:monitor`, `ops:health`, `ops:prune-events` | `tests/Feature/MonitoringTest.php` (12 tests) | Implemented + tested (code path) |
| 1.9 | Production configuration gate `ops:preflight` fails unsafe settings and never prints secrets | `tests/Feature/PreflightTest.php` (6 tests) | Implemented + tested |
| 1.10 | Frontend type checking wired into npm/CI | `tsconfig.json`, `resources/js/vite-env.d.ts`, `npm run typecheck` → exit 0 | Implemented + tested |
| 1.11 | Backup script hardening and isolated restore drill (checksums, row counts, constraints, FK rejection, app read) | `BACKUP_GUIDE.md` §5 | Implemented + tested with synthetic data |
| 1.12 | PHP formatting and dependency/security audits | Pint 159 files clean; `composer audit` clean; `npm audit` 0 | Implemented + tested |
| 1.13 | Full backend regression suite | `php artisan test` → **91 passed, 505 assertions** | Implemented + tested |
| 1.14 | Production build | `npm run build` → succeeded | Implemented + tested |

---

## 2. Verified against external services

Only the following external interactions were actually performed and observed.

| # | Item | Observed result | Limit of the evidence |
|---|---|---|---|
| 2.1 | XentriPay sandbox host reachability and API-key acceptance | Authenticated `GET /api/collections/status/launch-probe-nonexistent` returned HTTP 400 `No collection found for reference: launch-probe-nonexistent` | Proves DNS/TLS/connectivity and that the key is accepted. It is **not** a payment, collection, or webhook test. |
| 2.2 | Composer advisory database | `composer audit --locked` → no advisories | Point-in-time result; re-run before every release. |
| 2.3 | npm advisory database | `npm audit --audit-level=high` → 0 vulnerabilities | Point-in-time result; re-run before every release. |

**Not verified externally:** alert delivery, uptime monitoring, real payment collection, provider webhook delivery, production database restore, Railway deployment behaviour, TLS/HSTS on the real domain.

---

## 3. Configured but unverified

These exist in code or documentation but have not been exercised against the target environment.

| # | Item | Severity | Why it is unverified | Exact resolution |
|---|---|---|---|---|
| 3.1 | Monitoring alert webhook (`MONITORING_ALERT_WEBHOOK`) | High | No real webhook URL exists in the repository and none was contacted | Create a Slack/Mattermost incoming webhook; set `MONITORING_ENABLED=true` and the URL in the production environment; run the test command in `docs/MONITORING.md`; confirm the message arrives; record the date in this file. |
| 3.2 | External uptime monitor | Medium | No account is provisioned | Add an HTTPS check on `https://<domain>/health` at ≤60 s; verify a forced non-200 (stop the service in staging) produces an alert. |
| 3.3 | Scheduler heartbeat | High | Only meaningful on the deployed platform | Start `php artisan schedule:work`; confirm `php artisan ops:health` shows a recent "Last ops:monitor run". |
| 3.4 | Queue worker | Medium | No queued work was observed in a deployed environment | Run `php artisan queue:work --tries=3 --timeout=120`; confirm failed-job counts and `queue:restart` behaviour in staging. |
| 3.5 | CI workflow (`.github/workflows/ci.yml`) | Low | Added/updated locally; not observed running on GitHub | Push a branch/PR, confirm the tests, type-check, build, audit and style jobs execute; fix any environment drift. |
| 3.6 | XentriPay webhook endpoint with a registered secret | High | `XENTRIPAY_WEBHOOK_SECRET` is empty, so the endpoint returns 404 and polling is used | Register `https://<domain>/webhooks/xentripay` in the provider dashboard, set the same secret, send a provider test event, and complete §4.5. |
| 3.7 | Automated provider backups | High | No provider dashboard access in this environment | Enable and confirm retention in the Railway/Supabase/managed-DB dashboard; note the retention window and RPO in `BACKUP_GUIDE.md`. |

---

## 4. Requires manual operator action

These are mandatory launch conditions. Each is outside what the repository can prove.

| # | Action | Severity | Exact resolution / acceptance criteria |
|---|---|---|---|
| 4.1 | Set production environment variables | Critical | Configure every variable in `docs/DEPLOYMENT.md` §2 in the platform dashboard: `APP_ENV=production`, `APP_DEBUG=false`, unique `APP_KEY`, `APP_URL=https://…`, `SESSION_ENCRYPT=true`, `SESSION_SECURE_COOKIE=true`, `TRUSTED_HOSTS`, `MONITORING_ENABLED=true`, `PAYMENT_SANDBOX_ENABLED=false`, database credentials. Do not commit values. |
| 4.2 | Run the release gate | Critical | On the target environment, run `php artisan config:cache && php artisan ops:preflight --strict`. It must exit 0 with no FAIL rows. Investigate every WARN; do not deploy on FAIL. |
| 4.3 | Verify network isolation / proxy trust | Critical | Confirm the app service has no public TCP proxy or published port and is reachable only through Railway's edge. From outside, send `X-Forwarded-For: 1.2.3.4` and confirm rate limiting/audit IPs use the real client IP. Send `Host: attacker.example` and confirm HTTP 400. If direct access exists, set explicit `TRUSTED_PROXIES` or block the port before launch. |
| 4.4 | Verify HTTPS, HSTS and security headers on the real domain | High | `curl -sI https://<domain>/` and confirm `Strict-Transport-Security`, CSP, `X-Frame-Options: DENY`, `nosniff`, COOP/CORP. Confirm `http://` redirects to `https://`. |
| 4.5 | Complete a real sandbox payment lifecycle | High | Follow `docs/PAYMENT_VERIFICATION.md` §3 with a sandbox handset/card and staging credentials: initiate → approve → poller settles once → stock decremented once → signed webhook accepted → duplicate webhook `processed:false` → bad signature 401. Record references, timestamps and results in that document. |
| 4.6 | Verify production-like backup restoration | High | Follow `BACKUP_GUIDE.md` §4–§5 against an isolated database, **never** over production. Verify row counts, checksums, constraints, FK behaviour and a staging app read. Record date, archive hash, duration and result in `BACKUP_GUIDE.md`. |
| 4.7 | Enroll every administrator in MFA | Critical | Create administrators with `php artisan admin:create`; each must complete QR enrollment, confirm a code, and store recovery codes offline. `ops:preflight` must report all administrators enrolled. Store recovery codes in the business's password manager, not in the repository. |
| 4.8 | Restrict and review admin accounts | High | Use one named account per person, remove departed staff immediately, and keep the admin list minimal. Password reset: `admin:reset-password`; MFA reset: `admin:reset-mfa <email>`. |
| 4.9 | Confirm database TLS and least-privilege credentials | High | Set `DB_SSLMODE=require` where supported; use an application role with only the needed DML privileges and a separate backup role for dumps. |
| 4.10 | Verify private proof storage and durable catalog storage | High | Confirm payment proofs are on the private disk and downloadable only by admins. If `CATALOG_DISK=public`, mount persistent storage and run `storage:link`; otherwise uploads vanish on redeploy. |
| 4.11 | Verify alert and uptime delivery | High | Complete 3.1 and 3.2 and record the observed test-alert timestamps here. Until then, external alerting is not operational. |
| 4.12 | Rotate any secret used during setup | Medium | If sandbox/production keys were shared in chat, screenshots or tickets, rotate them in the provider dashboards and redeploy. Never paste secrets into documentation. |

---

## 5. Not completed

These are known gaps. None is a hidden code defect, but several are launch conditions depending on the business decision.

| # | Gap | Severity | Impact | Resolution |
|---|---|---|---|---|
| 5.1 | Real provider sandbox transaction | High | Payment lifecycle is proven only by automated fakes and a read-only probe | Execute §4.5. |
| 5.2 | Production backup restore | High | Restore tooling and schema portability are proven; the production dataset/backup path is not | Execute §4.6 and record the result. |
| 5.3 | External alerting and uptime | High | Code records and attempts alerts, but no external recipient has been verified | Complete §3.1, §3.2, §4.11. |
| 5.4 | Browser E2E and accessibility testing | Medium | No automated proof of checkout/admin flows in a real browser | Add Playwright smoke tests (catalog → cart → checkout → order lookup; admin login + MFA + order view) and an accessibility check before high volume. |
| 5.5 | Load/performance and LCP/INP/CLS measurement | Medium | Capacity and Core Web Vitals are unknown | Run a load test against staging and record budgets; recheck after launch. |
| 5.6 | External penetration test / SAST | Medium | No independent adversarial review | Schedule before high-volume launch; scope payment and admin paths. |
| 5.7 | True parallel concurrency test | Low | Settlement/retry rely on transactions and row locks; tested sequentially on SQLite | Run two simultaneous retries/webhooks against PostgreSQL staging and confirm one payment/stock movement. |
| 5.8 | Granular staff RBAC | Medium | Every verified admin can perform every action | Define roles/policies before creating non-owner staff accounts. |
| 5.9 | Refund workflow | Medium (business) | Refunds are manual and unaudited in-app | Document the refund policy and an auditable process before accepting cards at scale. |
| 5.10 | Generic `/webhooks/payment` adapter | Low | Scaffold remains disabled | Keep disabled, or implement a real provider adapter with the same signature guarantees. |
| 5.11 | Unused stub directories and dead scaffolding | Info | Confusing but harmless | Remove in a dedicated cleanup change with tests re-run. |

---

## 6. Final decision

**CONDITIONALLY READY — not PRODUCTION READY.**

Rationale:

- No known critical or high **code** vulnerability remains open in the reviewed scope.
- All 91 automated tests, the frontend type check, the production build, formatting, and both dependency audits pass.
- MFA, trusted proxy/host handling, monitoring, preflight validation, and payment edge cases are implemented and tested.
- The classification cannot be lifted because mandatory conditions 4.1–4.11 require an operator with production access and external accounts. In particular, external alert delivery, real sandbox payment settlement with signed webhooks, network isolation, and production-like restore verification have **not** been independently observed.

Upgrade to **PRODUCTION READY** only when:

1. Every item in §4 is complete, with its acceptance evidence recorded.
2. `php artisan ops:preflight --strict` exits 0 on the production configuration.
3. A real sandbox payment lifecycle and a signed-webhook replay are recorded in `docs/PAYMENT_VERIFICATION.md`.
4. A production-like restore is recorded in `BACKUP_GUIDE.md`.
5. An external test alert and a forced uptime failure have both been observed.

No deployment was performed as part of this work. Deployment requires explicit authorization from the owner.
