<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\PaymentEvent;
use App\Models\Setting;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class XentriPayTest extends StoreTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'payments.xentripay.enabled' => true,
            'payments.xentripay.api_key' => 'sandbox-key',
            'payments.xentripay.base_url' => 'https://merchant.test.xentripay.com',
            'payments.xentripay.webhook_secret' => 'whsec-test',
        ]);

        Setting::updateOrCreate(['key' => 'delivery_fee_confirmed'], ['value' => '1']);
    }

    public function test_checkout_starts_a_xentripay_collection_with_normalised_phone(): void
    {
        Http::fake([
            'https://merchant.test.xentripay.com/api/collections/initiate' => Http::response([
                'success' => 1,
                'retcode' => 0,
                'reply' => 'Collection request accepted.',
                'refid' => 'REF-123',
                'tid' => 'TID-1',
                'url' => null,
            ]),
        ]);
        [, , $variant] = $this->createStoreProduct(price: 42000, stock: 5);
        $this->post('/cart/add', ['variant_id' => $variant->id, 'quantity' => 1])->assertRedirect();

        $this->post('/checkout', $this->xentriPayPayload())
            ->assertRedirect()
            ->assertSessionHas('success');

        $order = Order::with('payments')->firstOrFail();
        $payment = $order->payments->firstOrFail();
        $this->assertSame('xentripay_momo', $payment->provider);
        $this->assertSame('pending', $payment->status);
        $this->assertSame($order->public_reference.'-'.$payment->id, $payment->reference);
        $this->assertSame('REF-123', $payment->provider_reference);
        $this->assertSame('awaiting_payment', $order->order_status);

        Http::assertSent(function (ClientRequest $request) use ($order, $payment) {
            $data = $request->data();

            return $request->url() === 'https://merchant.test.xentripay.com/api/collections/initiate'
                && $request->hasHeader('X-XENTRIPAY-KEY', 'sandbox-key')
                && $data['email'] === 'customer@example.test'
                && $data['amount'] === 42000
                && $data['currency'] === 'RWF'
                && $data['pmethod'] === 'momo'
                && $data['chargesIncluded'] === true
                && $data['cnumber'] === '0780123456'
                && $data['msisdn'] === '250780123456'
                && $data['customerRef'] === $order->public_reference.'-'.$payment->id;
        });
    }

    public function test_email_is_required_for_xentripay_checkout(): void
    {
        Http::fake();
        [, , $variant] = $this->createStoreProduct();
        $this->post('/cart/add', ['variant_id' => $variant->id, 'quantity' => 1])->assertRedirect();

        $this->post('/checkout', $this->xentriPayPayload(['customer_email' => '']))
            ->assertSessionHasErrors('customer_email');

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_status_check_confirms_payment_and_consumes_stock_once(): void
    {
        [$order, $payment, $variant] = $this->createXentriPayOrder();
        Http::fake([
            'https://merchant.test.xentripay.com/api/collections/status/*' => Http::response([
                'customerRef' => $payment->reference,
                'rid' => 'RID-1',
                'status' => 'SUCCESS',
            ]),
        ]);

        $this->withSession(['verified_order_reference' => $order->public_reference])
            ->post('/order/'.$order->public_reference.'/xentripay/check')
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame('paid', $order->fresh()->payment_status);
        $this->assertSame('confirmed', $order->fresh()->order_status);
        $this->assertSame(4, $variant->fresh()->stock_quantity);
        $this->assertSame(0, $variant->fresh()->reserved);
        $this->assertNotNull($payment->fresh()->verified_at);

        $this->post('/order/'.$order->public_reference.'/xentripay/check')->assertRedirect();
        $this->assertSame(4, $variant->fresh()->stock_quantity);
    }

    public function test_failed_collection_can_be_retried_with_a_new_payment_attempt(): void
    {
        [$order, $payment, $variant] = $this->createXentriPayOrder();
        Http::fake([
            'https://merchant.test.xentripay.com/api/collections/status/*' => Http::response([
                'customerRef' => $payment->reference,
                'status' => 'FAILED',
            ]),
        ]);

        $this->withSession(['verified_order_reference' => $order->public_reference])
            ->post('/order/'.$order->public_reference.'/xentripay/check')
            ->assertRedirect();

        $this->assertSame('failed', $payment->fresh()->status);
        $this->assertSame('failed', $order->fresh()->payment_status);
        $this->assertSame('awaiting_payment', $order->fresh()->order_status);
        $this->assertSame(1, $variant->fresh()->reserved);

        Http::fake([
            'https://merchant.test.xentripay.com/api/collections/initiate' => Http::response([
                'success' => 1,
                'retcode' => 0,
                'refid' => 'REF-456',
            ]),
        ]);
        $this->post('/order/'.$order->public_reference.'/xentripay/retry')
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseCount('payments', 2);
        $latest = $order->payments()->latest('id')->firstOrFail();
        $this->assertSame('pending', $latest->status);
        $this->assertSame('REF-456', $latest->provider_reference);
        $this->assertNotSame($payment->reference, $latest->reference);
        $this->assertSame('pending', $order->fresh()->payment_status);
    }

    public function test_initiation_failure_keeps_the_order_payable_by_retry(): void
    {
        Http::fake([
            'https://merchant.test.xentripay.com/api/collections/initiate' => Http::response([
                'success' => 0,
                'retcode' => 607,
                'reply' => 'The mobile money number was rejected.',
            ]),
        ]);
        [, , $variant] = $this->createStoreProduct(price: 42000, stock: 5);
        $this->post('/cart/add', ['variant_id' => $variant->id, 'quantity' => 1])->assertRedirect();

        $this->post('/checkout', $this->xentriPayPayload())
            ->assertRedirect()
            ->assertSessionHas('error');

        $order = Order::with('payments')->firstOrFail();
        $this->assertSame('failed', $order->payments->firstOrFail()->status);
        $this->assertSame('failed', $order->payment_status);
        $this->assertSame('awaiting_payment', $order->order_status);
    }

    public function test_signed_webhook_is_idempotent_and_rejects_bad_signatures(): void
    {
        [$order, , $variant] = $this->createXentriPayOrder();
        $payload = [
            'event' => 'COLLECTION_SUCCESSFUL',
            'data' => [
                'status' => 'SUCCESSFUL',
                'reference' => 'REF-1',
                'amount' => 42000,
                'currency' => 'RWF',
            ],
            'idempotencyKey' => 'idem-xentri-1',
        ];
        $body = json_encode($payload);

        $this->postRawWebhook($body, 'sha256=deadbeef')->assertStatus(401);

        $this->postRawWebhook($body, 'sha256='.hash_hmac('sha256', $body, 'whsec-test'))
            ->assertOk()
            ->assertJson(['processed' => true]);

        $this->assertSame('paid', $order->fresh()->payment_status);
        $this->assertSame(4, $variant->fresh()->stock_quantity);
        $this->assertSame(0, $variant->fresh()->reserved);
        $this->assertSame(1, PaymentEvent::where('event_id', 'idem-xentri-1')->count());

        $this->postRawWebhook($body, 'sha256='.hash_hmac('sha256', $body, 'whsec-test'))
            ->assertOk()
            ->assertJson(['processed' => false]);

        $this->assertSame(4, $variant->fresh()->stock_quantity);
    }

    public function test_scheduled_poller_settles_pending_collections(): void
    {
        [$order, $payment, $variant] = $this->createXentriPayOrder();
        Http::fake([
            'https://merchant.test.xentripay.com/api/collections/status/*' => Http::response([
                'customerRef' => $payment->reference,
                'status' => 'SUCCESS',
            ]),
        ]);

        $this->artisan('app:poll-xentripay')->assertSuccessful();

        $this->assertSame('paid', $order->fresh()->payment_status);
        $this->assertSame('confirmed', $order->fresh()->order_status);
        $this->assertSame(4, $variant->fresh()->stock_quantity);
    }

    public function test_airtel_checkout_uses_the_mobile_money_channel(): void
    {
        Http::fake([
            'https://merchant.test.xentripay.com/api/collections/initiate' => Http::response([
                'success' => 1,
                'retcode' => 0,
                'refid' => 'REF-AIRTEL',
            ]),
        ]);
        [, , $variant] = $this->createStoreProduct(price: 42000, stock: 5);
        $this->post('/cart/add', ['variant_id' => $variant->id, 'quantity' => 1])->assertRedirect();

        $this->post('/checkout', $this->xentriPayPayload([
            'payment_method' => 'xentripay_airtel',
            'customer_phone' => '+250 730 123 456',
        ]))->assertRedirect()->assertSessionHas('success');

        $order = Order::with('payments')->firstOrFail();
        $this->assertSame('xentripay_airtel', $order->payments->firstOrFail()->provider);

        Http::assertSent(function (ClientRequest $request) {
            $data = $request->data();

            return $data['pmethod'] === 'momo'
                && $data['cnumber'] === '0730123456'
                && $data['msisdn'] === '250730123456';
        });
    }

    public function test_each_mobile_money_method_rejects_the_other_network(): void
    {
        Http::fake();
        [, , $variant] = $this->createStoreProduct();
        $this->post('/cart/add', ['variant_id' => $variant->id, 'quantity' => 1])->assertRedirect();

        $this->post('/checkout', $this->xentriPayPayload([
            'payment_method' => 'xentripay_momo',
            'customer_phone' => '0730123456',
        ]))->assertSessionHasErrors('customer_phone');

        $this->post('/checkout', $this->xentriPayPayload([
            'payment_method' => 'xentripay_airtel',
            'customer_phone' => '0780123456',
        ]))->assertSessionHasErrors('customer_phone');

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_card_checkout_redirects_to_the_gateway_and_stores_the_url(): void
    {
        Http::fake([
            'https://merchant.test.xentripay.com/api/collections/initiate' => Http::response([
                'success' => 1,
                'retcode' => 0,
                'refid' => 'REF-CARD',
                'url' => 'https://secure.xentripay.test/pay/abc',
            ]),
        ]);
        [, , $variant] = $this->createStoreProduct(price: 42000, stock: 5);
        $this->post('/cart/add', ['variant_id' => $variant->id, 'quantity' => 1])->assertRedirect();

        $this->post('/checkout', $this->xentriPayPayload(['payment_method' => 'xentripay_card']))
            ->assertRedirect('https://secure.xentripay.test/pay/abc');

        $order = Order::with('payments')->firstOrFail();
        $payment = $order->payments->firstOrFail();
        $this->assertSame('xentripay_card', $payment->provider);
        $this->assertSame('https://secure.xentripay.test/pay/abc', $payment->checkout_url);
        $this->assertSame('REF-CARD', $payment->provider_reference);
        $this->assertSame('awaiting_payment', $order->order_status);

        Http::assertSent(function (ClientRequest $request) use ($order) {
            $data = $request->data();

            return $data['pmethod'] === 'cc'
                && $data['redirecturl'] === route('xentripay.return', $order)
                && $data['returl'] === route('xentripay.return', $order)
                && ! isset($data['msisdn']);
        });
    }

    public function test_card_gateway_return_settles_the_payment(): void
    {
        [$order, $payment, $variant] = $this->createXentriPayOrder(method: 'xentripay_card');
        $payment->update(['checkout_url' => 'https://secure.xentripay.test/pay/abc']);
        Http::fake([
            'https://merchant.test.xentripay.com/api/collections/status/*' => Http::response([
                'customerRef' => $payment->reference,
                'status' => 'SUCCESS',
            ]),
        ]);

        $this->withSession(['verified_order_reference' => $order->public_reference])
            ->get('/order/'.$order->public_reference.'/xentripay/return')
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame('paid', $order->fresh()->payment_status);
        $this->assertSame('confirmed', $order->fresh()->order_status);
        $this->assertSame(4, $variant->fresh()->stock_quantity);
    }

    public function test_failed_card_payment_retries_with_a_new_gateway_redirect(): void
    {
        [$order, $payment] = $this->createXentriPayOrder(method: 'xentripay_card');
        Http::fake([
            'https://merchant.test.xentripay.com/api/collections/status/*' => Http::response([
                'customerRef' => $payment->reference,
                'status' => 'FAILED',
            ]),
        ]);
        $this->withSession(['verified_order_reference' => $order->public_reference])
            ->post('/order/'.$order->public_reference.'/xentripay/check')
            ->assertRedirect();

        Http::fake([
            'https://merchant.test.xentripay.com/api/collections/initiate' => Http::response([
                'success' => 1,
                'retcode' => 0,
                'refid' => 'REF-CARD-2',
                'url' => 'https://secure.xentripay.test/pay/new',
            ]),
        ]);
        $this->post('/order/'.$order->public_reference.'/xentripay/retry')
            ->assertRedirect('https://secure.xentripay.test/pay/new');

        $latest = $order->payments()->latest('id')->firstOrFail();
        $this->assertSame('xentripay_card', $latest->provider);
        $this->assertSame('https://secure.xentripay.test/pay/new', $latest->checkout_url);
        $this->assertSame('pending', $order->fresh()->payment_status);
    }

    public function test_webhook_settles_an_airtel_payment(): void
    {
        [$order, , $variant] = $this->createXentriPayOrder(method: 'xentripay_airtel');
        $body = json_encode([
            'event' => 'COLLECTION_SUCCESSFUL',
            'data' => [
                'status' => 'SUCCESSFUL',
                'reference' => 'REF-1',
                'amount' => 42000,
                'currency' => 'RWF',
            ],
            'idempotencyKey' => 'idem-xentri-airtel',
        ]);

        $this->postRawWebhook($body, 'sha256='.hash_hmac('sha256', $body, 'whsec-test'))
            ->assertOk()
            ->assertJson(['processed' => true]);

        $this->assertSame('paid', $order->fresh()->payment_status);
        $this->assertSame(4, $variant->fresh()->stock_quantity);
    }

    private function xentriPayPayload(array $overrides = []): array
    {
        return array_merge([
            'customer_name' => 'Test Customer',
            'customer_email' => 'customer@example.test',
            'customer_phone' => '+250 780 123 456',
            'delivery_location' => 'Kigali, Rwanda',
            'notes' => '',
            'payment_method' => 'xentripay_momo',
            'idempotency_key' => (string) Str::uuid(),
        ], $overrides);
    }

    private function createXentriPayOrder(int $stock = 5, string $method = 'xentripay_momo'): array
    {
        [, , $variant] = $this->createStoreProduct(price: 42000, stock: $stock);
        $variant->update(['reserved' => 1]);

        $order = Order::create([
            'public_reference' => 'KB-'.Str::upper(Str::random(24)),
            'idempotency_key' => (string) Str::uuid(),
            'customer_name' => 'Test Customer',
            'customer_email' => 'customer@example.test',
            'customer_phone' => '+250780123456',
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
            'variant_id' => $variant->id,
            'product_variant_id' => $variant->id,
            'product_name' => $variant->product->name,
            'variant_name' => $variant->name,
            'unit_price_rwf' => 42000,
            'quantity' => 1,
        ]);

        $payment = Payment::create([
            'order_id' => $order->id,
            'method' => $method,
            'provider' => $method,
            'amount' => 42000,
            'amount_rwf' => 42000,
            'currency' => 'RWF',
            'status' => 'pending',
            'reference' => $order->public_reference.'-1',
            'provider_reference' => 'REF-1',
            'raw_status' => 'xentripay.initiated',
        ]);

        return [$order, $payment, $variant];
    }

    private function postRawWebhook(string $body, string $signature)
    {
        return $this->call('POST', '/webhooks/xentripay', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_XENTRIPAY_SIGNATURE' => $signature,
            'HTTP_X_XENTRIPAY_EVENT' => 'COLLECTION_SUCCESSFUL',
        ], $body);
    }
}
