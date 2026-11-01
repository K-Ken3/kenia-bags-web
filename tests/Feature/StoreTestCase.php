<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

abstract class StoreTestCase extends TestCase
{
    use RefreshDatabase;

    protected function createStoreProduct(int $price = 42000, int $stock = 5, string $name = 'Kigali Tote'): array
    {
        $category = Category::firstOrCreate(
            ['slug' => 'totes'],
            ['name' => 'Totes', 'is_active' => true],
        );
        $product = Product::create([
            'category_id' => $category->id,
            'name' => $name,
            'slug' => Str::slug($name),
            'description' => 'An everyday bag.',
            'base_price' => $price,
            'price_rwf' => $price,
            'stock_quantity' => $stock,
            'is_active' => true,
            'is_featured' => true,
            'status' => 'active',
        ]);
        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'name' => 'Standard',
            'colour' => 'Brown',
            'price' => $price,
            'price_rwf' => $price,
            'stock_quantity' => $stock,
            'reserved' => 0,
            'is_active' => true,
        ]);

        return [$category, $product, $variant];
    }

    protected function enableManualMomo(): void
    {
        Setting::updateOrCreate(['key' => 'manual_momo_enabled'], ['value' => '1']);
        Setting::updateOrCreate(['key' => 'momo_pay_code'], ['value' => 'TEST-PAY-CODE']);
        Setting::updateOrCreate(['key' => 'delivery_fee_confirmed'], ['value' => '1']);
    }

    protected function checkoutPayload(array $overrides = []): array
    {
        return array_merge([
            'customer_name' => 'Test Customer',
            'customer_email' => 'customer@example.test',
            'customer_phone' => '+250700000000',
            'delivery_location' => 'Kigali, Rwanda',
            'notes' => '',
            'payment_method' => 'manual_momo',
            'idempotency_key' => (string) Str::uuid(),
        ], $overrides);
    }
}
