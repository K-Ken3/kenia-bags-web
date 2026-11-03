<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class HomeController extends Controller
{
    public function index(Request $request)
    {
        return store_page('Home', [
            'featured' => Product::with([
                'images',
                'category',
                'variants' => fn ($variants) => $variants->where('is_active', true),
            ])
                ->where('status', 'active')
                ->where('is_active', true)
                ->orderByDesc('is_featured')
                ->latest()
                ->take(4)
                ->get(),
            'categories' => Category::where('is_active', true)
                ->orderBy('position')
                ->get()
                ->map(function (Category $category) {
                    $category->image_url = $category->image_path
                        ? Storage::disk(config('store.catalog_disk', 'public'))->url($category->image_path)
                        : null;

                    return $category;
                }),
        ]);
    }
}
