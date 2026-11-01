<?php

namespace Tests\Feature;

use App\Models\Setting;

class CartTest extends StoreTestCase
{
    public function test_customer_can_add_update_and_remove_a_variant(): void
    {
        [, $product, $variant] = $this->createStoreProduct();

        $this->post('/cart/add', ['variant_id' => $variant->id, 'quantity' => 2])
            ->assertRedirect();
        $this->get('/cart')->assertOk()
            ->assertSee($product->name)
            ->assertSee('84000')
            ->assertSee('To be confirmed by the store');
        Setting::updateOrCreate(['key' => 'delivery_fee_confirmed'], ['value' => '1']);
        $this->get('/cart')->assertSee('Calculated at checkout');
        $this->patch('/cart/'.$variant->id, ['quantity' => 3])->assertRedirect();
        $this->get('/cart')->assertSee('126000');
        $this->delete('/cart/'.$variant->id)->assertRedirect();
        $this->get('/cart')->assertSee('"items":[]', false);
    }

    public function test_customer_can_add_a_variant_with_a_json_response_for_async_cart_updates(): void
    {
        [, $product, $variant] = $this->createStoreProduct();

        $this->postJson('/cart/add', ['variant_id' => $variant->id, 'quantity' => 2])
            ->assertCreated()
            ->assertJsonPath('success', $product->name.' added to your bag.')
            ->assertJsonPath('cartCount', 2)
            ->assertJsonPath('cartPreview.0.slug', $product->slug)
            ->assertJsonPath('cartPreview.0.quantity', 2);

        $this->postJson('/cart/add', ['variant_id' => $variant->id, 'quantity' => 1])
            ->assertCreated()
            ->assertJsonPath('cartCount', 3)
            ->assertJsonPath('cartPreview.0.quantity', 3);

        $this->postJson('/cart/add', ['variant_id' => $variant->id, 'quantity' => 4])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('quantity');
    }

    public function test_customer_cannot_add_more_than_available_stock(): void
    {
        [, , $variant] = $this->createStoreProduct(stock: 1);

        $this->from('/products/kigali-tote')
            ->post('/cart/add', ['variant_id' => $variant->id, 'quantity' => 2])
            ->assertSessionHasErrors('quantity');
    }
}
