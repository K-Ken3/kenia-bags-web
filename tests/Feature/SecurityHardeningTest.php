<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\PaymentProof;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class SecurityHardeningTest extends StoreTestCase
{
    public function test_generic_payment_webhook_is_disabled_by_default(): void
    {
        [$order, $payment] = $this->createOrderWithPayment();

        $this->postJson('/webhooks/payment', [
            'event_id' => 'evt-unsigned',
            'payment_id' => $payment->id,
            'status' => 'paid',
            'amount_rwf' => $order->total_rwf,
        ])->assertNotFound();

        $this->assertSame('pending', $payment->fresh()->status);
        $this->assertSame('pending', $order->fresh()->payment_status);
    }

    public function test_generic_payment_webhook_requires_a_valid_signature(): void
    {
        [$order, $payment] = $this->createOrderWithPayment();
        config(['payments.webhook_enabled' => true, 'payments.webhook_secret' => 'test-secret']);

        $payload = [
            'event_id' => 'evt-signed-1',
            'payment_id' => $payment->id,
            'status' => 'paid',
            'amount_rwf' => $order->total_rwf,
        ];
        $body = json_encode($payload);

        $this->signedWebhook($body, 'deadbeef')->assertUnauthorized();

        $this->signedWebhook($body, hash_hmac('sha256', $body, 'test-secret'))
            ->assertOk()
            ->assertJson(['processed' => true]);

        $this->assertSame('paid', $order->fresh()->payment_status);
    }

    public function test_idempotency_key_cannot_expose_another_customers_order(): void
    {
        $this->enableManualMomo();
        [, , $variant] = $this->createStoreProduct();
        $payload = $this->checkoutPayload();

        $this->withSession(['cart' => [$variant->id => 1]])
            ->post('/checkout', $payload)->assertRedirect();

        $order = Order::firstOrFail();
        $this->flushSession();

        $this->withSession(['cart' => [$variant->id => 1]])
            ->post('/checkout', $this->checkoutPayload([
                'customer_phone' => '+250788888888',
                'idempotency_key' => $payload['idempotency_key'],
            ]))
            ->assertSessionHasErrors('idempotency_key');

        $this->assertSame(1, Order::count());
        $this->assertSame($order->public_reference, Order::firstOrFail()->public_reference);
    }

    public function test_admin_can_download_payment_proof_but_customers_cannot(): void
    {
        Storage::fake('local');
        [$order, $payment] = $this->createOrderWithPayment();
        $path = 'payment-proofs/'.Str::uuid().'.webp';
        Storage::disk('local')->put($path, 'proof-bytes');
        $proof = PaymentProof::create([
            'payment_id' => $payment->id,
            'order_id' => $order->id,
            'path' => $path,
            'status' => 'pending',
            'uploaded_at' => now(),
        ]);

        $admin = $this->createVerifiedAdmin();
        $this->actingAsVerifiedAdmin($admin)->get('/admin/proofs/'.$proof->id.'/file')->assertOk();

        $this->actingAs(User::factory()->create(['role' => 'customer']))
            ->get('/admin/proofs/'.$proof->id.'/file')
            ->assertForbidden();
    }

    public function test_admin_session_expires_after_inactivity(): void
    {
        $admin = $this->createVerifiedAdmin();

        $this->actingAsVerifiedAdmin($admin)->withSession([
            'admin_login_at' => now()->subMinutes(90)->getTimestamp(),
            'admin_last_activity' => now()->subMinutes(61)->getTimestamp(),
        ])->get('/admin')->assertRedirect('/admin/login');

        $this->assertGuest();
    }

    public function test_repeated_admin_login_failures_are_rate_limited(): void
    {
        $payload = ['email' => 'nobody@example.test', 'password' => 'wrong-password'];

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->post('/admin/login', $payload)
                ->assertSessionHasErrors(['email' => 'These credentials do not match an administrator account.']);
        }

        $this->post('/admin/login', $payload)->assertStatus(429);
    }

    public function test_order_and_payment_payloads_do_not_expose_internal_fields(): void
    {
        [$order, $payment] = $this->createOrderWithPayment();

        $response = $this->withSession(['verified_order_reference' => $order->public_reference])
            ->get('/order/'.$order->public_reference)
            ->assertOk();

        $response->assertDontSee('"idempotency_key"', false);
        $response->assertDontSee('"user_id"', false);
        $response->assertDontSee('"provider_reference"', false);
        $response->assertDontSee('"verified_by"', false);
        $this->assertNotEmpty($payment->reference);
    }

    public function test_production_headers_add_browser_protections_and_no_store_on_admin(): void
    {
        $this->app->detectEnvironment(fn () => 'production');
        config(['payments.webhook_enabled' => false]);

        $admin = $this->createVerifiedAdmin();
        $response = $this->actingAsVerifiedAdmin($admin)->get('/admin')->assertOk();

        $response->assertHeader('Cross-Origin-Opener-Policy', 'same-origin-allow-popups');
        $response->assertHeader('Cross-Origin-Resource-Policy', 'same-origin');
        $response->assertHeader('X-Permitted-Cross-Domain-Policies', 'none');
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        $this->assertStringContainsString(
            "style-src 'self' 'unsafe-inline'",
            (string) $response->headers->get('Content-Security-Policy'),
        );
    }

    private function createOrderWithPayment(): array
    {
        [, , $variant] = $this->createStoreProduct(stock: 5);
        $variant->update(['reserved' => 1]);

        $order = Order::create([
            'public_reference' => 'KB-'.Str::upper(Str::random(24)),
            'idempotency_key' => (string) Str::uuid(),
            'customer_name' => 'Test Customer',
            'customer_phone' => '+250700000000',
            'delivery_location' => 'Kigali, Rwanda',
            'subtotal_rwf' => 42000,
            'total_rwf' => 42000,
            'order_status' => 'awaiting_payment',
            'payment_status' => 'pending',
            'delivery_status' => 'not_dispatched',
            'expires_at' => now()->addHour(),
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $variant->product_id,
            'product_variant_id' => $variant->id,
            'variant_id' => $variant->id,
            'product_name' => $variant->product->name,
            'variant_name' => $variant->name,
            'unit_price' => 42000,
            'unit_price_rwf' => 42000,
            'quantity' => 1,
        ]);

        $payment = Payment::create([
            'order_id' => $order->id,
            'method' => 'manual_momo',
            'provider' => 'manual_momo',
            'amount' => 42000,
            'amount_rwf' => 42000,
            'currency' => 'RWF',
            'status' => 'pending',
            'reference' => $order->public_reference.'-1',
            'raw_status' => 'pending',
        ]);

        return [$order, $payment, $variant];
    }

    private function signedWebhook(string $body, string $signature)
    {
        return $this->call('POST', '/webhooks/payment', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_SIGNATURE' => $signature,
            'HTTP_X_PAYMENT_PROVIDER' => 'manual_momo',
        ], $body);
    }
}
