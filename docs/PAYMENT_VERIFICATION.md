# Payment Lifecycle Verification

Status date: 2026-11-12. Scope: XentriPay collections (MTN MoMo, Airtel Money, Visa/Mastercard) and the generic signed webhook.

## 1. What is verified

Automated coverage lives in `tests/Feature/XentriPayTest.php` (20 tests, 131 assertions) and
`tests/Feature/MonitoringTest.php`. All tests use Laravel's HTTP fake; **no test performs a real
provider transaction**.

| Scenario | Result | Where |
| --- | --- | --- |
| Initiate MTN MoMo collection, normalized phone, correct amount/currency/customerRef | Pass | `test_checkout_starts_a_xentripay_collection_with_normalised_phone` |
| Initiate Airtel collection; reject wrong network number | Pass | `test_airtel_checkout_uses_the_mobile_money_channel`, `test_each_mobile_money_method_rejects_the_other_network` |
| Card checkout redirect and gateway return settlement | Pass | `test_card_checkout_redirects_to_the_gateway_and_stores_the_url`, `test_card_gateway_return_settles_the_payment` |
| Status polling confirms payment; stock consumed exactly once | Pass | `test_status_check_confirms_payment_and_consumes_stock_once`, `test_scheduled_poller_settles_pending_collections` |
| Failed collection keeps the order payable; retry creates a new attempt | Pass | `test_failed_collection_can_be_retried_with_a_new_payment_attempt`, `test_failed_card_payment_retries_with_a_new_gateway_redirect` |
| Interrupted/rejected initiation leaves the order retryable | Pass | `test_initiation_failure_keeps_the_order_payable_by_retry` |
| Retry blocked while a previous attempt is still pending | Pass | `test_retry_is_rejected_while_an_attempt_is_still_pending` |
| Missing/invalid signature rejected; payload never settles | Pass | `test_signed_webhook_is_idempotent_and_rejects_bad_signatures` |
| Duplicate/replayed webhook (same idempotency key) is a no-op | Pass | `test_signed_webhook_is_idempotent_and_rejects_bad_signatures` |
| Altered amount rejected | Pass | `test_signed_success_webhook_without_an_amount_is_rejected`, `test_webhook_amount_mismatch_is_recorded_as_reconciliation_failure` |
| Altered currency rejected | Pass | `test_signed_webhook_with_a_wrong_currency_is_rejected` |
| Wrong/unknown reference ignored without state change | Pass | `test_webhook_for_an_unknown_reference_is_ignored` |
| Out-of-order: failure after success does not downgrade a paid order | Pass | `test_late_failure_webhook_does_not_downgrade_a_paid_order` |
| Out-of-order: success after failure confirms the order | Pass | `test_failure_then_success_webhook_confirms_the_order` |
| Payment after order expiry is recorded but not auto-confirmed and stock is not double-consumed | Pass | `test_payment_arriving_after_order_expiry_is_not_auto_confirmed` |
| Reconciliation failures raise operator alerts | Pass | `MonitoringTest` |

Concurrency: settlement, retry and webhook handling use database transactions plus
`lockForUpdate()` and re-validate state inside the lock
(`app/Actions/SettleXentriPayPayment.php`, `app/Http/Controllers/XentriPayController.php`).
A true parallel-request test is not executed because the test database is SQLite; the
guards are covered sequentially. A PostgreSQL staging test with two simultaneous retries
is recommended before high-volume launch.

## 2. Real sandbox connectivity (read-only)

A read-only authenticated probe was executed against the configured sandbox:

```
GET https://merchant.test.xentripay.com/api/collections/status/launch-probe-nonexistent
X-XENTRIPAY-KEY: (configured key, not printed)
```

Result: HTTP 400 with `No collection found for reference: launch-probe-nonexistent`.

Interpretation: the sandbox host is reachable from the development machine and the configured
API key is accepted (an unauthenticated key would be rejected). This proves connectivity and
credential acceptance only. It is **not** a payment lifecycle test.

No real collection was initiated: doing so sends a payment prompt to a real phone or requires a
sandbox handset that is not available in this environment. No transaction, webhook or provider
dashboard check is being reported as completed.

## 3. What is required for a full real-sandbox E2E

Missing inputs / actions (all must be supplied by the operator):

1. A provider sandbox test handset (MTN MoMo or Airtel Money) that can approve prompts, or a
   sandbox test card.
2. Access to the XentriPay sandbox dashboard to observe collection state.
3. `XENTRIPAY_WEBHOOK_SECRET` configured and the public webhook URL registered in the provider
   dashboard (`/webhooks/xentripay`). The secret is currently empty, so the webhook endpoint
   returns 404 and the application relies on status polling (`app:poll-xentripay`).
4. A staging deployment with `APP_ENV=production`-like settings but sandbox credentials and no
   real customer data.

Procedure once the above exist:

1. On staging, add a product to the cart and check out with the sandbox handset number.
2. Confirm the order page shows a pending XentriPay payment and that a collection exists in the
   provider dashboard with the expected amount (RWF) and `customerRef`.
3. Approve the prompt on the handset (or complete the card page).
4. Run `php artisan app:poll-xentripay` or click "Check status" and confirm:
   order `confirmed`, payment `paid`, stock decremented once, `verified_at` set.
5. Register the webhook, set the secret, and repeat with a second collection. Re-send the same
   webhook twice and confirm the second response is `processed:false` and no duplicate stock
   movement occurs.
6. Send a webhook with a wrong signature and confirm 401 with no state change.
7. Record the collection references, timestamps and results in this document.

Until steps 1–7 are executed against the real sandbox and recorded, the payment lifecycle is
**verified by automated tests only**, not by a real provider transaction.

## 4. Secret handling

- `XENTRIPAY_API_KEY` and `XENTRIPAY_WEBHOOK_SECRET` are read from the environment only; they are
  never sent to the browser or written to logs in this repository.
- Monitoring events and logs record provider/payment/order identifiers and reasons, not payloads,
  phone numbers or keys.
