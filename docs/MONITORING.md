# Production Monitoring and Alerting

This document describes the monitoring layer implemented in the application, what is verified, and what still requires operator action. External monitoring vendors are **not** configured by this repository; do not treat any external service as operational until an account has been created and a test alert has been received.

## What is implemented and tested

| Capability | Implementation | Verified by |
| --- | --- | --- |
| 5xx / uncaught error tracking | `App\Support\Monitoring::reportException()` registered in `bootstrap/app.php`; stores an `operational_events` row and raises a critical alert | `tests/Feature/MonitoringTest.php` |
| Browser error tracking | `POST /client-errors` (rate limited, scrubbed) plus `resources/js/monitoring.ts` | `tests/Feature/MonitoringTest.php` |
| Payment webhook failures | Signature failures and disabled endpoints record `webhook_failure` | `tests/Feature/MonitoringTest.php`, `tests/Feature/XentriPayTest.php` |
| Payment reconciliation failures | Amount/currency/order mismatches record `payment_reconciliation_failure` (critical) | `tests/Feature/MonitoringTest.php` |
| Failed queue jobs | `ops:monitor` counts recent `failed_jobs` rows | `tests/Feature/MonitoringTest.php` |
| Stuck payments | `ops:monitor` flags pending online payments older than `MONITORING_STUCK_PAYMENTS_MINUTES` whose order is still `awaiting_payment` | `tests/Feature/MonitoringTest.php` |
| Unusual admin activity | Failed admin sign-in and MFA failures record `admin_auth_failure` / `admin_mfa_failure` (IP hashed, no email/phone) | `tests/Feature/MonitoringTest.php`, `tests/Feature/AdminMfaTest.php` |
| Alert delivery | Slack-compatible JSON webhook with per-signal deduplication (`MONITORING_ALERT_WEBHOOK`) | `tests/Feature/MonitoringTest.php` |
| Health / uptime probe | `GET /health` returns `{"status":"ok"}` and 200, or 503 when the database or cache is unreachable. No infrastructure details are exposed. Laravel's built-in `GET /up` remains available. | `tests/Feature/MonitoringTest.php` |
| Operator health summary | `php artisan ops:health` prints drivers, counters and the last monitor run | `tests/Feature/MonitoringTest.php` |
| Event retention | `php artisan ops:prune-events` deletes events older than `MONITORING_RETENTION_DAYS` | `tests/Feature/MonitoringTest.php` |
| Scheduler heartbeat | `ops:monitor` stores `ops:monitor:last_run` in the cache; `ops:health` warns when it is missing | `tests/Feature/MonitoringTest.php` |

Scheduling (in `routes/console.php`):

```php
Schedule::command('ops:monitor')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('ops:prune-events')->daily()->withoutOverlapping();
```

## Data handling

- Stored events contain event type, severity, a short message, and identifiers/counters only.
- Free-text messages are scrubbed of e-mail addresses, phone numbers and bearer tokens.
- Context keys containing `password`, `secret`, `token`, `signature`, `authorization`, `cookie`, `session`, `card`, `cvv`, `pin`, `email`, `phone`, `msisdn`, `account`, `payload`, `body` or `proof` are replaced with `[redacted]`.
- Raw exception messages, request bodies and webhook payloads are never copied into monitoring events. Full exceptions remain in the application log (stdout on Railway).
- Client IPs are stored as a truncated SHA-256 hash when used for correlation.

## Environment behaviour

- `MONITORING_ENABLED` defaults to `true` only when `APP_ENV=production`.
- In `local` and `testing`, monitoring is disabled unless explicitly enabled, so development errors do not page anyone and do not write rows.
- If `MONITORING_ALERT_WEBHOOK` is empty, alerts are written to the application log only. `ops:monitor` prints a warning in that case.

## Operator setup (required before launch)

1. **Alert destination.** Create a Slack (or Mattermost) incoming webhook, or any endpoint that accepts `{"text": "..."}`. Set:

   ```env
   MONITORING_ENABLED=true
   MONITORING_ALERT_WEBHOOK=https://hooks.slack.com/services/...
   ```

   Send a test alert with:

   ```sh
   php artisan tinker --execute="app(App\Support\Monitoring::class)->alert('critical', 'Monitoring test alert', [], 'manual:test');"
   ```

   Confirm the message arrives. If it does not, alerts are not operational.

2. **Scheduler.** `ops:monitor` only runs if the Laravel scheduler runs. On Railway run the web process and a worker/scheduler process that executes:

   ```sh
   php artisan schedule:work
   ```

   (or add a Railway cron service running `php artisan schedule:run` every minute). Verify with `php artisan ops:health` that "Last ops:monitor run" is recent.

3. **Queue worker.** Failed job counts are only meaningful when a worker runs the queue:

   ```sh
   php artisan queue:work --tries=3 --timeout=120
   ```

4. **Uptime monitor.** Add an external uptime check (UptimeRobot, Better Stack, Pingdom, etc.) against `https://<your-domain>/health` with a 60-second interval and alert on non-200. This repository cannot create that account. Railway's own health check can also target `/health`.

5. **Log retention.** On Railway, set `LOG_CHANNEL=stderr` or `LOG_STACK=stderr` so logs reach the platform log stream. The file-based default is not durable in containers.

## What is *not* configured

- No Sentry/Bugsnag/Rollbar account or DSN is configured. The monitoring layer deliberately has no vendor dependency; if you add one, wire it into `Monitoring::reportException()` in addition to the existing event store.
- No alert webhook URL is present in the repository (secrets must never be committed).
- No external uptime monitor exists yet.
- Until items 1–4 above are completed and a test alert is observed, production error alerting is **configured in code but not verified against an external service**.

## Alert response

See `docs/INCIDENT_RESPONSE.md`. Useful commands during an incident:

```sh
php artisan ops:health
php artisan ops:monitor
php artisan queue:failed
php artisan app:poll-xentripay
php artisan admin:reset-mfa --force admin@example.com
```
