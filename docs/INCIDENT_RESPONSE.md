# Incident Response Plan

This plan covers security and availability incidents for the storefront and admin application. It is intentionally short enough to follow under pressure.

## 1. Roles

| Role | Responsibility |
|---|---|
| Incident lead | Owns the timeline, decisions, and communications. Default: store owner or on-call engineer. |
| Technical responder | Investigates logs, code, database, and infrastructure; applies containment. |
| Communications | Notifies customers/regulator as required; drafts statements. |
| Record keeper | Keeps a timestamped incident log (actions, evidence, decisions). |

Fill in current names and contact channels in the private operations runbook. Do not store personal phone numbers in this repository.

## 2. Severity

| Level | Definition | Examples | Response target |
|---|---|---|---|
| SEV-1 | Confirmed or likely compromise of payment/data, or full outage | Unauthorized admin access, payment marked paid without funds, customer data exfiltration, database destroyed | Immediate, all hands |
| SEV-2 | Major function degraded or credible exploitation attempt | Webhook signature failures spiking, admin lockout, checkout unavailable, duplicate charge risk | Within 1 hour |
| SEV-3 | Limited impact with workaround | Single product/image failure, scheduler lag, non-critical error spike | Same business day |
| SEV-4 | Minor | Cosmetic issues, individual failed login complaints | Normal backlog |

## 3. Detection sources

- Provider notifications or payment reconciliation mismatches.
- Customer reports (wrong order status, unexpected charge).
- Application logs: failed admin logins, webhook 401/404, 5xx, exceptions.
- In-application monitoring (`operational_events`) and alerts for 5xx, failed queue jobs, stuck payments, webhook/reconciliation failures and admin/MFA failures — see [MONITORING.md](MONITORING.md).
- `GET /health` (503 when the database or cache is unreachable) and `php artisan ops:health`.
- Platform alerts: high CPU/memory, database connection failures, restart loops.
- Dependency advisory feeds (`composer audit`, `npm audit`, GitHub security alerts).

If the alert webhook and external uptime monitor are not configured and tested, treat alerting as degraded. This is still a launch blocker; see [FINAL_LAUNCH_CHECKLIST.md](FINAL_LAUNCH_CHECKLIST.md).

## 4. First 15 minutes

1. Declare the incident and severity in the incident channel; assign the lead.
2. Start the timestamped log.
3. Preserve evidence **before** changing anything:
   - export relevant log ranges,
   - record affected order/payment IDs (never copy full card data — none is stored),
   - take a database snapshot if data integrity is in question.
4. Contain, based on the scenario below. Prefer reversible containment.
5. Communicate an initial status to the owner; external communication only with approval.

**Do not** delete logs, rotate all secrets reflexively before capturing evidence, or "clean up" the database before a backup.

## 5. Playbooks

### 5.1 Suspected admin account compromise

Indicators: logins from unexpected IPs, unexpected product/price changes, suspicious payment approvals, audit-log gaps.

1. Disable the account and invalidate sessions:
   - `php artisan admin:reset-password` for the account (also deletes DB sessions), or delete the user if unauthorized.
   - If sessions are cached elsewhere, flush the session store.
2. Review `audit_logs` for actions by that user and surrounding timestamps.
3. Compare orders/payments created or approved during the window against provider records.
4. Rotate `APP_KEY` only if session/cookie forgery is suspected; this logs everyone out and forces all administrators to re-enroll MFA.
5. Reset the compromised account's MFA with `php artisan admin:reset-mfa <email>` (clears the secret and recovery codes, deletes that user's database sessions, writes an audit entry).
6. Re-enable access with a new unique password and a fresh TOTP enrollment; issue new recovery codes.
7. Determine initial access vector (phishing, reused password, leaked secret, stolen recovery codes) and fix it.

### 5.1a Administrator locked out by MFA

Indicators: a legitimate administrator cannot complete the TOTP challenge, or has lost the authenticator and recovery codes.

1. Confirm identity out of band; do not reset MFA on an unverified request.
2. Run `php artisan admin:reset-mfa <email>` on the server (interactive confirmation, or `--force` for automation). This invalidates that user's sessions.
3. Have the administrator sign in, enroll a new authenticator, and store the new recovery codes offline.
4. Check `operational_events`/logs for repeated `admin_mfa_failure` entries before the lockout — they may indicate an attack rather than a lost device.

### 5.2 Payment tampering or fraudulent "paid" state

Indicators: order paid with no matching provider transaction, amount mismatch, repeated webhook events, 401/404 spikes.

1. Immediately disable the affected path:
   - Generic webhook: set `PAYMENT_WEBHOOK_ENABLED=false` and redeploy.
   - XentriPay: set `XENTRIPAY_ENABLED=false` if collections must pause; do not delete the webhook secret until you have captured failed-signature logs.
2. Freeze suspicious payments: mark them failed/unpaid in the admin UI after confirming with the provider. Do not delete records.
3. Reconcile every payment in the window against provider statements by order reference, amount, and currency.
4. Rotate `XENTRIPAY_API_KEY` and `XENTRIPAY_WEBHOOK_SECRET`; update the provider dashboard.
5. Verify webhook signature enforcement and amount/currency checks are active (tests cover this).
6. Notify affected customers if money moved incorrectly; follow the business refund process.

### 5.3 Leaked secret or credential

1. Identify scope: `APP_KEY`, database password, provider API key, webhook secret.
2. Capture evidence of misuse from logs.
3. Rotate the credential at the source, then update the platform variables and redeploy.
   - `APP_KEY` rotation invalidates sessions and encrypted data tied to it.
   - Database password rotation requires updating the app and confirming connections.
4. Check for unauthorized access during the exposure window.
5. Remove the secret from git history if committed; assume it is public if pushed.

### 5.4 Customer data exposure or loss

1. Identify what data and how many records.
2. Stop the exposure (fix authorization, remove public file, revoke link).
3. Preserve logs and access records.
4. Assess legal/regulatory notification duties (Rwanda data-protection law and any applicable provider contracts). Notify the owner immediately; legal counsel decides external notices.
5. Offer affected customers guidance; do not speculate publicly.

### 5.5 Database corruption or accidental deletion

1. Stop writes if corruption is ongoing (maintenance mode: `php artisan down`).
2. Take a snapshot of the current state before any restore.
3. Restore from the most recent verified backup to a **new** database instance first.
4. Validate row counts and integrity (orders, payments, products, users) before switching traffic.
5. Reconcile payments against provider records for the gap window.
6. Document the restore time and data-loss window.

### 5.6 Denial of service / traffic spike

1. Enable platform-level rate limiting/WAF or scale the service.
2. Confirm the app's route throttles are active (checkout, login, webhooks, proof upload).
3. If malicious, block offending ranges at the edge; do not add application code under pressure.
4. Watch database connections and queue backlog; shed non-critical work if needed.

## 6. Recovery and verification

- Confirm the fix with the failing scenario, not just a successful login.
- Rerun `php artisan test`, `composer audit --locked`, `php artisan ops:preflight`, and smoke tests (`/health`, `/`, `/admin`).
- Verify orders/payments affected by the incident are reconciled.
- Remove temporary access, flags, and debug logging.
- Restore normal monitoring thresholds.

## 7. Communications

- Internal: short status updates at fixed intervals (start, containment, recovery, closure).
- External: only with owner/legal approval. State facts, impact, actions taken, and next update time. Never speculate or blame.
- Keep a single source of truth; avoid conflicting messages across channels.

## 8. Post-incident review

Within five business days:

1. Timeline of detection, containment, recovery.
2. Root cause (technical and process).
3. What worked and what slowed response.
4. Action items with owners and due dates.
5. Update this plan, tests, and monitoring.
6. If a code defect was involved, add a regression test and update [PRODUCTION_SECURITY_AUDIT.md](PRODUCTION_SECURITY_AUDIT.md).

## 9. Quick reference

```bash
# Maintenance mode
php artisan down --secret="<temporary-secret>"
php artisan up

# Invalidate sessions (database driver)
php artisan session:table   # only if table is missing
php artisan migrate --force

# Reset an admin password (interactive, also clears DB sessions)
php artisan admin:reset-password

# Reset administrator MFA (clears TOTP + recovery codes, clears DB sessions)
php artisan admin:reset-mfa <email> [--force]

# Operational status and monitoring
php artisan ops:health
php artisan ops:monitor
php artisan ops:preflight

# Clear caches after config change
php artisan optimize:clear

# Restart workers
php artisan queue:restart
```

Log locations: the configured Laravel log channel (`stderr` in containers; `storage/logs/laravel.log` by default locally); platform logs for web/worker processes. Persisted monitoring events live in `operational_events` and are pruned after `MONITORING_RETENTION_DAYS`.

Backup and restore: [BACKUP_GUIDE.md](../BACKUP_GUIDE.md).
Monitoring and alerts: [MONITORING.md](MONITORING.md).
Security controls and secrets: [SECURITY.md](SECURITY.md).
Deployment and rollback: [DEPLOYMENT.md](DEPLOYMENT.md).
