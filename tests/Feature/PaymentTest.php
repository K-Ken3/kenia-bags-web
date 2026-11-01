<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\PaymentEvent;
use App\Models\PaymentProof;
use App\Models\User;
use App\Payments\SandboxProvider;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class PaymentTest extends StoreTestCase
{
    public function test_customer_can_upload_private_manual_payment_proof(): void
    {
        Storage::fake('local');
        [$order, $payment] = $this->createManualOrder();

        $this->withSession(['verified_order_reference' => $order->public_reference])
            ->post('/order/'.$order->public_reference.'/proof', [
                'proof' => UploadedFile::fake()->image('payment-proof.jpg', 100, 100),
                'reference' => 'TEST-REF-01',
            ])->assertRedirect();

        $proof = PaymentProof::firstOrFail();
        Storage::disk('local')->assertExists($proof->path);
        $this->assertSame('under_review', $payment->fresh()->status);
        $this->assertSame('under_review', $order->fresh()->payment_status);
        $this->assertSame('payment_review', $order->fresh()->order_status);
    }

    public function test_admin_can_approve_or_reject_manual_payment_proofs(): void
    {
        Storage::fake('local');
        $admin = User::factory()->create(['role' => 'admin']);
        [$order, $payment, $variant] = $this->createManualOrder(withItem: true);
        $payment->update(['status' => 'under_review']);
        $order->update(['payment_status' => 'under_review', 'order_status' => 'payment_review', 'status' => 'payment_review']);
        $approvedProof = $this->createProof($order, $payment);

        $this->actingAs($admin)
            ->post('/admin/proofs/'.$approvedProof->id.'/approve', ['review_note' => 'Verified in merchant account'])
            ->assertRedirect();
        $this->assertSame('paid', $order->fresh()->payment_status);
        $this->assertSame('confirmed', $order->fresh()->order_status);
        $this->assertSame(4, $variant->fresh()->stock_quantity);
        $this->assertSame(0, $variant->fresh()->reserved);
        $this->assertDatabaseHas('audit_logs', ['action' => 'payment.manual_approved', 'user_id' => $admin->id]);

        [$rejectedOrder, $rejectedPayment] = $this->createManualOrder();
        $rejectedPayment->update(['status' => 'under_review']);
        $rejectedOrder->update(['payment_status' => 'under_review', 'order_status' => 'payment_review', 'status' => 'payment_review']);
        $rejectedProof = $this->createProof($rejectedOrder, $rejectedPayment);
        $this->post('/admin/proofs/'.$rejectedProof->id.'/reject', ['review_note' => 'Reference could not be verified'])
            ->assertRedirect();
        $this->assertSame('pending', $rejectedOrder->fresh()->payment_status);
        $this->assertSame('awaiting_payment', $rejectedOrder->fresh()->order_status);
        $this->assertSame('rejected', $rejectedProof->fresh()->status);
    }

    public function test_sandbox_webhook_is_idempotent_and_consumes_reserved_stock_only_once(): void
    {
        config(['payments.sandbox_enabled' => true]);
        [, , $variant] = $this->createStoreProduct(stock: 5);
        $variant->update(['reserved' => 1]);
        [$order, $payment] = $this->createManualOrder(withItem: true, variant: $variant, provider: 'sandbox');
        $eventId = (string) Str::uuid();
        $payload = [
            'event_id' => $eventId,
            'payment_id' => $payment->id,
            'status' => 'paid',
            'amount_rwf' => $payment->amount_rwf,
        ];
        $provider = app(SandboxProvider::class);

        $this->assertTrue($provider->handleWebhook(new Request($payload))['processed']);
        $this->assertFalse($provider->handleWebhook(new Request($payload))['processed']);
        $this->assertSame('paid', $order->fresh()->payment_status);
        $this->assertSame(4, $variant->fresh()->stock_quantity);
        $this->assertSame(0, $variant->fresh()->reserved);
        $this->assertDatabaseCount('payment_events', 1);
    }

    public function test_sandbox_rejects_amount_mismatches_without_recording_an_event(): void
    {
        config(['payments.sandbox_enabled' => true]);
        [$order, $payment] = $this->createManualOrder(provider: 'sandbox');

        try {
            app(SandboxProvider::class)->handleWebhook(new Request([
                'event_id' => (string) Str::uuid(),
                'payment_id' => $payment->id,
                'status' => 'paid',
                'amount_rwf' => $payment->amount_rwf + 1,
            ]));
            $this->fail('An amount mismatch must be rejected.');
        } catch (ValidationException) {
            $this->assertSame('pending', $order->fresh()->payment_status);
            $this->assertDatabaseCount('payment_events', 0);
        }
    }

    public function test_admin_can_simulate_sandbox_payment_only_when_enabled_in_testing(): void
    {
        config(['payments.sandbox_enabled' => true]);
        $admin = User::factory()->create(['role' => 'admin']);
        [$order] = $this->createManualOrder(provider: 'sandbox');

        $this->actingAs($admin)
            ->post('/admin/orders/'.$order->public_reference.'/sandbox', ['status' => 'paid'])
            ->assertRedirect();
        $this->assertSame('paid', $order->fresh()->payment_status);

        config(['payments.sandbox_enabled' => false]);
        $this->post('/admin/orders/'.$order->public_reference.'/sandbox', ['status' => 'pending'])
            ->assertNotFound();
    }

    private function createManualOrder(bool $withItem = false, $variant = null, string $provider = 'manual_momo'): array
    {
        if ($withItem && ! $variant) {
            [, , $variant] = $this->createStoreProduct();
            $variant->update(['reserved' => 1]);
        }

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
        if ($withItem) {
            OrderItem::create([
                'order_id' => $order->id,
                'product_id' => $variant->product_id,
                'variant_id' => $variant->id,
                'product_variant_id' => $variant->id,
                'product_name' => $variant->product->name,
                'variant_name' => $variant->name,
                'unit_price_rwf' => 42000,
                'quantity' => 1,
            ]);
        }
        $payment = Payment::create([
            'order_id' => $order->id,
            'method' => $provider,
            'provider' => $provider,
            'amount_rwf' => 42000,
            'amount' => 42000,
            'currency' => 'RWF',
            'status' => 'pending',
            'raw_status' => 'pending',
        ]);

        return [$order, $payment, $variant];
    }

    private function createProof(Order $order, Payment $payment): PaymentProof
    {
        return PaymentProof::create([
            'payment_id' => $payment->id,
            'order_id' => $order->id,
            'path' => 'payment-proofs/'.Str::uuid().'.webp',
            'status' => 'pending',
            'uploaded_at' => now(),
        ]);
    }
}
