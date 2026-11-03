<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function show(Product $product)
    {
        abort_unless($product->status === 'active', 404);
        $product->load(['category', 'images', 'variants' => fn ($variants) => $variants->where('is_active', true)]);

        return store_page('Products/Show', [
            'product' => $product,
            'related' => Product::with([
                'images',
                'variants' => fn ($variants) => $variants->where('is_active', true),
            ])->where('status', 'active')
                ->where('category_id', $product->category_id)
                ->where('id', '!=', $product->id)
                ->take(4)->get(),
        ]);
    }
}
