<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Support\Str;

class OrderTest extends StoreTestCase
{
    public function test_customer_must_verify_reference_and_phone_before_viewing_order(): void
    {
        $order = Order::create([
            'public_reference' => 'KB-'.Str::upper(Str::random(24)),
            'customer_name' => 'Test Customer',
            'customer_phone' => '+250700000000',
            'delivery_location' => 'Kigali, Rwanda',
            'subtotal_rwf' => 42000,
            'total_rwf' => 42000,
            'order_status' => 'awaiting_payment',
            'payment_status' => 'pending',
            'delivery_status' => 'not_dispatched',
        ]);
        OrderItem::create([
            'order_id' => $order->id,
            'product_name' => 'Kigali Tote',
            'unit_price_rwf' => 42000,
            'quantity' => 1,
        ]);

        $this->get('/order/'.$order->public_reference)->assertRedirect('/order/lookup');
        $this->post('/order/lookup', [
            'public_reference' => $order->public_reference,
            'phone' => '+250799999999',
        ])->assertRedirect()->assertSessionHas('error');
        $this->post('/order/lookup', [
            'public_reference' => $order->public_reference,
            'phone' => $order->customer_phone,
        ])->assertRedirect('/order/'.$order->public_reference);

        $this->get('/order/'.$order->public_reference)
            ->assertOk()
            ->assertSee($order->public_reference)
            ->assertSee('Kigali Tote')
            ->assertSee('"total_rwf":42000', false);
    }
}
