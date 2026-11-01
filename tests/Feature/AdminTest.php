<?php

namespace Tests\Feature;

use App\Models\PolicyPage;
use App\Models\Order;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AdminTest extends StoreTestCase
{
    public function test_admin_routes_reject_guests_and_non_admin_users(): void
    {
        $this->get('/admin')->assertRedirect('/admin/login');
        $this->actingAs(User::factory()->create(['role' => 'customer']))
            ->get('/admin')->assertForbidden();
    }

    public function test_admin_can_create_and_archive_a_product(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $category = \App\Models\Category::create(['name' => 'Totes', 'slug' => 'totes']);

        $this->actingAs($admin)->post('/admin/products', [
            'category_id' => $category->id,
            'name' => 'Admin Tote',
            'slug' => 'admin-tote',
            'description' => 'Test product',
            'price_rwf' => 25000,
            'status' => 'active',
            'is_featured' => '1',
            'variants' => [
                ['name' => 'Brown', 'colour' => 'Brown', 'price_rwf' => 25000, 'stock_quantity' => 3],
            ],
        ])->assertRedirect();

        $product = \App\Models\Product::where('slug', 'admin-tote')->firstOrFail();
        $this->assertSame(25000, $product->price_rwf);
        $this->assertSame(3, $product->variants()->firstOrFail()->stock_quantity);
        $this->delete('/admin/products/'.$product->id)->assertRedirect();
        $this->assertSame('archived', $product->fresh()->status);
    }

    public function test_admin_cannot_confirm_or_dispatch_an_unpaid_order(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $order = Order::create([
            'public_reference' => 'KB-'.Str::upper(Str::random(24)),
            'customer_name' => 'Test Customer',
            'customer_phone' => '+250700000000',
            'delivery_location' => 'Kigali, Rwanda',
            'subtotal_rwf' => 10000,
            'total_rwf' => 10000,
            'order_status' => 'awaiting_payment',
            'payment_status' => 'pending',
        ]);

        $this->actingAs($admin)
            ->put('/admin/orders/'.$order->public_reference, ['order_status' => 'confirmed'])
            ->assertSessionHasErrors('order_status');
        $this->put('/admin/orders/'.$order->public_reference, ['order_status' => 'packing'])
            ->assertSessionHasErrors('order_status');
        $this->assertSame('awaiting_payment', $order->fresh()->order_status);
        $this->assertSame('pending', $order->fresh()->payment_status);
    }

    public function test_admin_can_upload_and_reencode_brand_logo_and_update_store_details(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->put('/admin/settings', [
            'brand_name' => 'Test Store',
            'brand_logo' => UploadedFile::fake()->image('logo.png', 100, 100),
            'contact_email' => 'store@example.test',
            'contact_phone' => '+250700000000',
            'whatsapp_number' => '',
            'delivery_fee_rwf' => 0,
            'delivery_fee_confirmed' => '1',
            'delivery_information' => 'Delivery details confirmed for testing.',
            'momo_pay_code' => '',
            'unpaid_order_expiry_minutes' => 60,
        ]);
        $response->assertRedirect()->assertSessionHasNoErrors();

        $logoPath = Setting::get('brand_logo');
        $this->assertStringEndsWith('.webp', $logoPath);
        Storage::disk('public')->assertExists($logoPath);
        $this->assertSame('1', Setting::get('delivery_fee_confirmed'));
        $this->assertSame('Delivery details confirmed for testing.', Setting::get('delivery_information'));
    }

    public function test_admin_can_edit_multiple_variants_without_deactivating_other_options(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $category = \App\Models\Category::create(['name' => 'Totes', 'slug' => 'totes']);
        $this->actingAs($admin)->post('/admin/products', [
            'category_id' => $category->id,
            'name' => 'Colour Tote',
            'slug' => 'colour-tote',
            'price_rwf' => 25000,
            'status' => 'active',
            'variants' => [
                ['name' => 'Brown', 'colour' => 'Brown', 'price_rwf' => 25000, 'stock_quantity' => 3],
                ['name' => 'Black', 'colour' => 'Black', 'price_rwf' => 26000, 'stock_quantity' => 2],
            ],
        ])->assertRedirect();
        $product = \App\Models\Product::where('slug', 'colour-tote')->firstOrFail();
        $variants = $product->variants()->orderBy('id')->get();

        $this->put('/admin/products/'.$product->id, [
            'category_id' => $category->id,
            'name' => 'Colour Tote',
            'slug' => 'colour-tote',
            'price_rwf' => 25000,
            'status' => 'active',
            'variants' => [
                ['id' => $variants[0]->id, 'name' => 'Brown', 'colour' => 'Brown', 'price_rwf' => 25000, 'stock_quantity' => 3],
                ['id' => $variants[1]->id, 'name' => 'Black', 'colour' => 'Black', 'price_rwf' => 26000, 'stock_quantity' => 2],
            ],
        ])->assertRedirect();
        $this->assertSame(2, $product->variants()->where('is_active', true)->count());

        $variants[0]->update(['reserved' => 1]);
        $this->put('/admin/products/'.$product->id, [
            'category_id' => $category->id,
            'name' => 'Colour Tote',
            'slug' => 'colour-tote',
            'price_rwf' => 25000,
            'status' => 'active',
            'variants' => [
                ['id' => $variants[0]->id, 'name' => 'Brown', 'colour' => 'Brown', 'price_rwf' => 25000, 'stock_quantity' => 0],
                ['id' => $variants[1]->id, 'name' => 'Black', 'colour' => 'Black', 'price_rwf' => 26000, 'stock_quantity' => 2],
            ],
        ])->assertSessionHasErrors('variants.0.stock_quantity');
    }

    public function test_admin_can_edit_policy_content_but_public_draft_stays_hidden(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $policy = PolicyPage::create([
            'slug' => 'privacy',
            'title' => 'Privacy',
            'body' => '[DRAFT — OWNER REVIEW]',
            'is_published' => false,
        ]);
        $this->get('/policies/privacy')->assertNotFound();

        $this->actingAs($admin)->put('/admin/policies/'.$policy->id, [
            'title' => 'Privacy information',
            'body' => 'Reviewed test policy.',
            'is_published' => '1',
        ])->assertRedirect();
        $this->get('/policies/privacy')->assertOk()->assertSee('Reviewed test policy.');
    }

    public function test_security_headers_include_production_csp_and_hsts(): void
    {
        $this->app->detectEnvironment(fn () => 'production');
        $this->get('/')
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains')
            ->assertHeader('Content-Security-Policy');
    }
}
