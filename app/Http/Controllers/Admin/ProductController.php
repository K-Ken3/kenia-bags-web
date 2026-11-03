<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ProductUpsertRequest;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ProductController extends Controller
{
    public function index()
    {
        return store_page('Admin/Products/Index', [
            'products' => Product::with('category')->latest()->paginate(20)->withQueryString(),
        ]);
    }

    public function create()
    {
        return store_page('Admin/Products/Form', ['categories' => Category::orderBy('name')->get(), 'product' => null]);
    }

    public function store(ProductUpsertRequest $request)
    {
        $data = $request->validated();
        $product = DB::transaction(function () use ($request, $data) {
            $product = Product::create([
                'category_id' => $data['category_id'] ?? null,
                'name' => $data['name'],
                'slug' => $data['slug'],
                'description' => $data['description'] ?? null,
                'price_rwf' => $data['price_rwf'],
                'base_price' => $data['price_rwf'],
                'status' => $data['status'],
                'is_active' => $data['status'] === 'active',
                'is_featured' => $data['is_featured'] ?? false,
                'stock_quantity' => collect($data['variants'])->sum('stock_quantity'),
            ]);
            $this->syncVariants($product, $data['variants']);
            $this->saveImage($request, $product, $data['image_alt'] ?? null);
            return $product;
        });

        return redirect()->route('admin.products.edit', $product)->with('success', 'Product created.');
    }

    public function show(Product $product)
    {
        return redirect()->route('admin.products.edit', $product);
    }

    public function edit(Product $product)
    {
        return store_page('Admin/Products/Form', [
            'categories' => Category::orderBy('name')->get(),
            'product' => $product->load(['variants', 'images']),
        ]);
    }

    public function update(ProductUpsertRequest $request, Product $product)
    {
        $data = $request->validated();
        DB::transaction(function () use ($request, $data, $product) {
            $product->update([
                'category_id' => $data['category_id'] ?? null,
                'name' => $data['name'],
                'slug' => $data['slug'],
                'description' => $data['description'] ?? null,
                'price_rwf' => $data['price_rwf'],
                'base_price' => $data['price_rwf'],
                'status' => $data['status'],
                'is_active' => $data['status'] === 'active',
                'is_featured' => $data['is_featured'] ?? false,
                'stock_quantity' => collect($data['variants'])->sum('stock_quantity'),
            ]);
            $this->syncVariants($product, $data['variants']);
            $this->saveImage($request, $product, $data['image_alt'] ?? null);
        });

        return back()->with('success', 'Product updated.');
    }

    public function destroy(Product $product)
    {
        if ($product->orderItems()->exists()) {
            return back()->withErrors(['product' => 'Products with order history must be archived, not deleted.']);
        }
        $product->update(['status' => 'archived', 'is_active' => false]);

        return redirect()->route('admin.products.index')->with('success', 'Product archived.');
    }

    private function syncVariants(Product $product, array $variants): void
    {
        $submittedIds = collect($variants)->pluck('id')->filter()->map(fn ($id) => (int) $id)->all();
        if ($product->variants()->whereNotIn('id', $submittedIds)->where('reserved', '>', 0)->exists()) {
            throw ValidationException::withMessages(['variants' => 'Keep any options that have stock reserved for an open order.']);
        }

        foreach ($variants as $index => $variant) {
            $existing = ! empty($variant['id'])
                ? $product->variants()->lockForUpdate()->find($variant['id'])
                : null;
            if (! empty($variant['id']) && ! $existing) {
                throw ValidationException::withMessages(["variants.{$index}.id" => 'This option does not belong to the selected product.']);
            }
            if ($existing && $variant['stock_quantity'] < $existing->reserved) {
                throw ValidationException::withMessages(["variants.{$index}.stock_quantity" => 'Stock cannot be lower than the quantity reserved for open orders.']);
            }

            $attributes = [
                'name' => $variant['name'],
                'colour' => $variant['colour'] ?? null,
                'sku' => $variant['sku'] ?? null,
                'price_rwf' => $variant['price_rwf'] ?? $product->price_rwf,
                'price' => $variant['price_rwf'] ?? $product->price_rwf,
                'stock_quantity' => $variant['stock_quantity'],
                'is_active' => true,
            ];
            if ($existing) {
                $existing->update($attributes);
            } else {
                $product->variants()->create($attributes);
            }
        }

        $product->variants()->whereNotIn('id', $submittedIds)->where('reserved', 0)->update(['is_active' => false]);
    }

    private function saveImage(ProductUpsertRequest $request, Product $product, ?string $alt): void
    {
        if (! $request->hasFile('image')) {
            return;
        }

        $file = $request->file('image');
        $dimensions = getimagesize($file->getPathname());
        if (! $dimensions || $dimensions[0] > 4000 || $dimensions[1] > 4000) {
            abort(422, 'Choose an image no larger than 4000 by 4000 pixels.');
        }
        $source = imagecreatefromstring($file->getContent());
        if (! $source) {
            abort(422, 'The image could not be decoded.');
        }
        $width = imagesx($source);
        $height = imagesy($source);
        $scale = min(1, 2000 / max($width, $height));
        $image = imagecreatetruecolor((int) ($width * $scale), (int) ($height * $scale));
        imagecopyresampled($image, $source, 0, 0, 0, 0, imagesx($image), imagesy($image), $width, $height);
        ob_start();
        $encoded = imagewebp($image, null, 82);
        $webp = ob_get_clean();
        imagedestroy($source);
        imagedestroy($image);
        if (! $encoded || ! is_string($webp) || $webp === '') {
            abort(422, 'The image could not be converted to WebP.');
        }
        $path = 'product-images/'.Str::uuid().'.webp';
        if (! Storage::disk(config('store.catalog_disk', 'public'))->put($path, $webp, ['visibility' => 'public'])) {
            abort(500, 'The product image could not be saved.');
        }

        $product->images()->create(['path' => $path, 'alt_text' => $alt ?: $product->name, 'position' => $product->images()->count()]);
    }
}
