<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $everyday = Category::where('slug', 'everyday-bags')->firstOrFail();
        $work = Category::where('slug', 'work-travel')->firstOrFail();
        $small = Category::where('slug', 'small-bags')->firstOrFail();

        foreach ([
            [$everyday, 'Sample Tote 01', 'sample-tote-01', 'Development sample. Replace this product and price before launch.', 42000, true],
            [$everyday, 'Sample Crossbody 02', 'sample-crossbody-02', 'Development sample. Replace this product and price before launch.', 35000, true],
            [$work, 'Sample Weekender 03', 'sample-weekender-03', 'Development sample. Replace this product and price before launch.', 78000, true],
            [$work, 'Sample Work Bag 04', 'sample-work-bag-04', 'Development sample. Replace this product and price before launch.', 65000, false],
            [$small, 'Sample Mini Bag 05', 'sample-mini-bag-05', 'Development sample. Replace this product and price before launch.', 28000, false],
            [$small, 'Sample Belt Bag 06', 'sample-belt-bag-06', 'Development sample. Replace this product and price before launch.', 32000, false],
        ] as [$category, $name, $slug, $description, $price, $featured]) {
            Product::firstOrCreate(
                ['slug' => $slug],
                [
                    'category_id' => $category->id,
                    'name' => $name,
                    'description' => $description,
                    'base_price' => $price,
                    'price_rwf' => $price,
                    'stock_quantity' => 20,
                    'is_active' => true,
                    'is_featured' => $featured,
                    'status' => 'active',
                ],
            );
            ProductVariant::firstOrCreate(
                ['product_id' => Product::where('slug', $slug)->value('id'), 'name' => 'Standard'],
                ['price' => $price, 'price_rwf' => $price, 'stock_quantity' => 20, 'reserved' => 0, 'is_active' => true],
            );
        }
    }
}
