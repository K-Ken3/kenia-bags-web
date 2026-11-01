<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\Request;

class ShopController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate([
            'category' => ['nullable', 'string', 'max:100'],
            'search' => ['nullable', 'string', 'max:100'],
            'price_min' => ['nullable', 'integer', 'min:0'],
            'price_max' => ['nullable', 'integer', 'min:0'],
            'in_stock' => ['nullable', 'boolean'],
            'sort' => ['nullable', 'in:featured,newest,price_low,price_high'],
        ]);
        $query = Product::with(['category', 'images', 'variants' => fn ($variants) => $variants->where('is_active', true)])
            ->where('status', 'active');

        if (! empty($filters['category'])) {
            $query->whereHas('category', fn ($q) => $q->where('slug', $filters['category']));
        }

        if (! empty($filters['search'])) {
            $search = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $filters['search']);
            $query->where(fn ($products) => $products
                ->where('name', 'like', '%'.$search.'%')
                ->orWhere('description', 'like', '%'.$search.'%'));
        }

        if (isset($filters['price_min'])) {
            $query->where('price_rwf', '>=', $filters['price_min']);
        }
        if (isset($filters['price_max'])) {
            $query->where('price_rwf', '<=', $filters['price_max']);
        }
        if (! empty($filters['in_stock'])) {
            $query->whereHas('variants', fn ($variants) => $variants->where('is_active', true)
                ->whereColumn('stock_quantity', '>', 'reserved'));
        }

        match ($filters['sort'] ?? 'featured') {
            'newest' => $query->latest(),
            'price_low' => $query->orderBy('price_rwf')->orderBy('id'),
            'price_high' => $query->orderByDesc('price_rwf')->orderBy('id'),
            default => $query->orderByDesc('is_featured')->latest(),
        };
        $products = $query->paginate(12)->withQueryString();

        return inertia('Shop/Index', [
            'products' => $products,
            'categories' => Category::where('is_active', true)->orderBy('position')->get(),
            'filters' => $filters,
        ]);
    }
}
