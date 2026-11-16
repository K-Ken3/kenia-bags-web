<?php

namespace Tests\Feature;

use App\Models\OperationalEvent;
use App\Models\Order;
use App\Models\Payment;
use App\Support\Monitoring;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

class MonitoringTest extends StoreTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Http::fake();
        config([
            'monitoring.enabled' => true,
            'monitoring.alert_webhook' => 'https://alerts.example.test/hook',
            'monitoring.window_minutes' => 15,
            'monitoring.thresholds' => [
                'failed_jobs' => 1,
                'stuck_payments_minutes' => 20,
                'stuck_payments_count' => 1,
                'webhook_failures' => 1,
                'admin_failures' => 1,
            ],
        ]);
    }

    public function test_server_errors_are_recorded_and_alerted_without_pii(): void
    {
        Route::get('/__test/boom', function () {
            throw new \RuntimeException('Database failure for customer@example.test +250788123456');
        });

        $this->get('/__test/boom')->assertStatus(500);

        $event = OperationalEvent::where('type', 'server_error')->firstOrFail();
        $this->assertSame('critical', $event->severity);
        $this->assertStringNotContainsString('customer@example.test', $event->message);
        $this->assertStringNotContainsString('+250788123456', $event->message);
        $this->assertStringNotContainsString('customer@example.test', json_encode($event->context));

        Http::assertSent(fn ($request) => $request->url() === 'https://alerts.example.test/hook'
            && str_contains((string) $request['text'], 'Unhandled 500 error'));
    }

    public function test_client_errors_and_missing_pages_do_not_raise_alerts(): void
    {
        $this->get('/this-page-does-not-exist')->assertNotFound();
        $this->postJson('/client-errors', ['message' => ''])->assertStatus(422);

        $this->assertSame(0, OperationalEvent::count());
        Http::assertNothingSent();
    }

    public function test_health_endpoint_reports_readiness_without_details(): void
    {
        $this->getJson('/health')
            ->assertOk()
            ->assertExactJson(['status' => 'ok']);
    }

    public function test_webhook_signature_failures_are_recorded_without_the_payload(): void
    {
        config(['payments.xentripay.webhook_secret' => 'webhook-test-secret']);

        $this->postJson('/webhooks/xentripay', [
            'data' => ['reference' => 'PAY-123', 'amount' => 5000, 'currency' => 'RWF'],
        ], ['X-Xentripay-Signature' => 'sha256=deadbeef'])->assertStatus(401);

        $event = OperationalEvent::where('type', 'webhook_failure')->firstOrFail();
        $this->assertSame('xentripay', $event->context['provider']);
        $this->assertArrayNotHasKey('payload', $event->context);
        $this->assertStringNotContainsString('PAY-123', json_encode($event->context));
    }

    public function test_webhook_amount_mismatch_is_recorded_as_reconciliation_failure(): void
    {
        config([
            'payments.xentripay.webhook_secret' => 'webhook-test-secret',
        ]);

        $order = Order::create([
            'public_reference' => 'KB-'.Str::upper(Str::random(20)),
            'customer_name' => 'Test Customer',
            'customer_phone' => '+250700000000',
            'delivery_location' => 'Kigali, Rwanda',
            'subtotal_rwf' => 42000,
            'total_rwf' => 42000,
            'order_status' => 'awaiting_payment',
            'payment_status' => 'pending',
        ]);
        $payment = Payment::create([
            'order_id' => $order->id,
            'method' => 'xentripay_momo',
            'amount' => 42000,
            'amount_rwf' => 42000,
            'currency' => 'RWF',
            'status' => 'pending',
            'provider' => 'xentripay_momo',
            'reference' => 'PAY-RECON-1',
        ]);

        $payload = json_encode([
            'data' => ['reference' => 'PAY-RECON-1', 'amount' => 100, 'currency' => 'RWF', 'status' => 'SUCCESSFUL'],
        ], JSON_THROW_ON_ERROR);
        $signature = 'sha256='.hash_hmac('sha256', $payload, 'webhook-test-secret');

        $this->call('POST', '/webhooks/xentripay', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X-XENTRIPAY-SIGNATURE' => $signature,
        ], $payload)->assertStatus(422);

        $event = OperationalEvent::where('type', 'payment_reconciliation_failure')->firstOrFail();
        $this->assertSame($payment->id, $event->context['payment_id']);
        $this->assertSame($payment->status, $payment->fresh()->status);
    }

    public function test_monitor_command_alerts_on_failed_jobs_and_records_heartbeat(): void
    {
        DB::table('failed_jobs')->insert([
            'uuid' => (string) Str::uuid(),
            'connection' => 'database',
            'queue' => 'default',
            'payload' => '{}',
            'exception' => 'RuntimeException: test failure',
            'failed_at' => now(),
        ]);

        $this->artisan('ops:monitor')->assertFailed();

        Http::assertSent(fn ($request) => str_contains((string) $request['text'], 'failed queue job'));
        $this->assertNotNull(Cache::get('ops:monitor:last_run'));
    }

    public function test_monitor_flags_stuck_pending_payments(): void
    {
        $order = Order::create([
            'public_reference' => 'KB-'.Str::upper(Str::random(20)),
            'customer_name' => 'Test Customer',
            'customer_phone' => '+250700000000',
            'delivery_location' => 'Kigali, Rwanda',
            'subtotal_rwf' => 5000,
            'total_rwf' => 5000,
            'order_status' => 'awaiting_payment',
            'payment_status' => 'pending',
        ]);
        $payment = Payment::create([
            'order_id' => $order->id,
            'method' => 'xentripay_momo',
            'amount' => 5000,
            'amount_rwf' => 5000,
            'currency' => 'RWF',
            'status' => 'pending',
            'provider' => 'xentripay_momo',
            'reference' => 'PAY-STUCK-1',
        ]);
        DB::table('payments')->where('id', $payment->id)->update(['created_at' => now()->subHour()]);

        $this->artisan('ops:monitor')->assertFailed();

        Http::assertSent(fn ($request) => str_contains((string) $request['text'], 'need reconciliation'));
    }

    public function test_client_error_reports_are_scrubbed_and_rate_limited(): void
    {
        $this->postJson('/client-errors', [
            'message' => 'Failed for customer@example.test on +250788123456',
            'source' => 'app.js',
            'line' => 42,
            'page' => '/checkout?token=secret',
        ])->assertNoContent();

        $event = OperationalEvent::where('type', 'client_error')->firstOrFail();
        $this->assertStringNotContainsString('customer@example.test', $event->message);
        $this->assertStringNotContainsString('+250788123456', $event->message);
        $this->assertSame('/checkout', $event->context['page']);

        for ($i = 0; $i < 10; $i++) {
            $this->postJson('/client-errors', ['message' => 'Repeated failure '.$i]);
        }

        $this->postJson('/client-errors', ['message' => 'One too many'])->assertStatus(429);
    }

    public function test_alerts_are_deduplicated_and_skipped_without_a_webhook(): void
    {
        $monitoring = app(Monitoring::class);
        $monitoring->alert('critical', 'Repeated operational problem');
        $monitoring->alert('critical', 'Repeated operational problem');

        Http::assertSentCount(1);

        config(['monitoring.alert_webhook' => null]);
        $monitoring->alert('critical', 'A different problem');
        Http::assertSentCount(1);
    }

    public function test_monitoring_can_be_disabled_entirely(): void
    {
        config(['monitoring.enabled' => false]);

        app(Monitoring::class)->record('server_error', 'critical', 'Should not be stored.');

        $this->assertSame(0, OperationalEvent::count());
        Http::assertNothingSent();
    }

    public function test_retention_command_removes_old_events_only(): void
    {
        OperationalEvent::create([
            'type' => 'server_error',
            'severity' => 'critical',
            'message' => 'Old event',
            'occurred_at' => now()->subDays(60),
        ]);
        OperationalEvent::create([
            'type' => 'server_error',
            'severity' => 'critical',
            'message' => 'Recent event',
            'occurred_at' => now(),
        ]);

        $this->artisan('ops:prune-events')->assertSuccessful();

        $this->assertDatabaseHas('operational_events', ['message' => 'Recent event']);
        $this->assertDatabaseMissing('operational_events', ['message' => 'Old event']);
    }

    public function test_health_command_reports_ok(): void
    {
        $this->artisan('ops:health')->assertSuccessful();
    }
}
