<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Payment;
use App\Models\Setting;

class CheckoutTest extends StoreTestCase
{
    public function test_checkout_creates_an_order_with_server_prices_and_reserves_stock(): void
    {
        $this->enableManualMomo();
        [, $product, $variant] = $this->createStoreProduct(price: 42000, stock: 5);
        $this->post('/cart/add', ['variant_id' => $variant->id, 'quantity' => 2])->assertRedirect();
        $this->get('/checkout')->assertOk()->assertSee($product->name)->assertSee('84000');

        $payload = $this->checkoutPayload(['total_rwf' => 1, 'price' => 1]);
        $this->post('/checkout', $payload)->assertRedirect();

        $order = Order::with('items')->firstOrFail();
        $this->assertSame(84000, $order->subtotal_rwf);
        $this->assertSame(0, $order->delivery_fee_rwf);
        $this->assertSame(84000, $order->total_rwf);
        $this->assertSame('Kigali Tote', $order->items->first()->product_name);
        $this->assertSame(42000, $order->items->first()->unit_price_rwf);
        $this->assertSame(2, $order->items->first()->quantity);
        $this->assertSame(2, $variant->fresh()->reserved);
        $this->assertSame('manual_momo', Payment::firstOrFail()->provider);
        $this->assertSame([], session('cart', []));
    }

    public function test_duplicate_checkout_key_returns_the_existing_order(): void
    {
        $this->enableManualMomo();
        [, , $variant] = $this->createStoreProduct();
        $this->post('/cart/add', ['variant_id' => $variant->id, 'quantity' => 1])->assertRedirect();
        $payload = $this->checkoutPayload();

        $this->post('/checkout', $payload)->assertRedirect();
        $this->post('/checkout', $payload)->assertRedirect();

        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('order_items', 1);
        $this->assertDatabaseCount('payments', 1);
    }

    public function test_invalid_checkout_and_unavailable_payment_method_are_rejected(): void
    {
        $this->post('/checkout', [])->assertSessionHasErrors([
            'customer_name', 'customer_phone', 'delivery_location', 'payment_method', 'idempotency_key',
        ]);

        [, , $variant] = $this->createStoreProduct();
        $this->post('/cart/add', ['variant_id' => $variant->id, 'quantity' => 1])->assertRedirect();
        $this->post('/checkout', $this->checkoutPayload())->assertSessionHasErrors('payment_method');
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_order_cannot_be_placed_until_delivery_fee_is_confirmed(): void
    {
        $this->enableManualMomo();
        Setting::updateOrCreate(['key' => 'delivery_fee_confirmed'], ['value' => '0']);
        [, , $variant] = $this->createStoreProduct();
        $this->post('/cart/add', ['variant_id' => $variant->id, 'quantity' => 1])->assertRedirect();

        $this->get('/checkout')
            ->assertSee('"deliveryLabel":"Delivery fee not yet confirmed"', false)
            ->assertSee('"canPlaceOrder":false', false);
        $this->post('/checkout', $this->checkoutPayload())->assertSessionHasErrors('delivery_fee');
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_last_unit_cannot_be_reserved_twice(): void
    {
        $this->enableManualMomo();
        [, , $variant] = $this->createStoreProduct(stock: 1);

        $this->withSession(['cart' => [$variant->id => 1]])
            ->post('/checkout', $this->checkoutPayload())->assertRedirect();
        $this->withSession(['cart' => [$variant->id => 1]])
            ->post('/checkout', $this->checkoutPayload())->assertSessionHasErrors('cart');

        $this->assertDatabaseCount('orders', 1);
        $this->assertSame(1, $variant->fresh()->reserved);
    }
}
