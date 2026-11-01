<?php

namespace Tests\Feature;

class ProductTest extends StoreTestCase
{
    public function test_homepage_fills_four_product_cards_and_only_uses_demo_photos_outside_production(): void
    {
        foreach (range(1, 5) as $number) {
            [, $product] = $this->createStoreProduct(name: "Store Bag {$number}");
            $product->update(['is_featured' => $number <= 2]);
        }

        $response = $this->get('/');
        $featured = data_get($response->viewData('page'), 'props.featured');
        $this->assertCount(4, $featured);
        $this->assertCount(4, data_get($response->viewData('page'), 'props.demoProductImages'));

        $this->app->detectEnvironment(fn () => 'production');
        $productionResponse = $this->get('/');
        $this->assertSame([], data_get($productionResponse->viewData('page'), 'props.demoProductImages'));
    }

    public function test_product_grids_include_variants_for_the_add_to_cart_action(): void
    {
        [, $product, $variant] = $this->createStoreProduct();

        $homepage = $this->get('/');
        $featuredProduct = data_get($homepage->viewData('page'), 'props.featured')->first();
        $this->assertTrue($featuredProduct->relationLoaded('variants'));
        $this->assertSame($variant->id, $featuredProduct->variants->first()->id);

        $shop = $this->get('/shop');
        $shopProduct = data_get($shop->viewData('page'), 'props.products')->getCollection()->first();
        $this->assertSame($product->id, $shopProduct->id);
        $this->assertTrue($shopProduct->relationLoaded('variants'));
        $this->assertSame($variant->id, $shopProduct->variants->first()->id);
    }

    public function test_shop_search_price_stock_category_filters_and_product_detail(): void
    {
        [, $tote] = $this->createStoreProduct(price: 42000, stock: 5);
        [, $crossbody] = $this->createStoreProduct(price: 25000, stock: 0, name: 'Kigali Crossbody');

        $this->get('/shop')->assertOk()->assertSee($tote->name)->assertSee($crossbody->name);
        $this->get('/shop?search=Crossbody')->assertOk()->assertSee($crossbody->name)->assertDontSee($tote->name);
        $this->get('/shop?price_min=30000&price_max=50000')->assertOk()->assertSee($tote->name)->assertDontSee($crossbody->name);
        $this->get('/shop?in_stock=1')->assertOk()->assertSee($tote->name)->assertDontSee($crossbody->name);
        $this->get('/shop?category=totes&sort=price_low')->assertOk()->assertSee($tote->name)->assertSee($crossbody->name);
        $this->get('/products/'.$tote->slug)->assertOk()->assertSee($tote->name)->assertSee('Standard');
        $this->get('/products/not-a-product')->assertNotFound();
    }
}
