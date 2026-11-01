<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCartItemRequest;
use App\Models\ProductVariant;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class CartController extends Controller
{
    public function index(Request $request)
    {
        $cart = $request->session()->get('cart', []);
        $variants = ProductVariant::with(['product.images'])
            ->whereIn('id', array_keys($cart))
            ->where('is_active', true)
            ->whereHas('product', fn ($query) => $query->where('status', 'active'))
            ->get()
            ->keyBy('id');
        $cart = collect($cart)
            ->filter(fn (int $quantity, string $variantId) => $variants->has($variantId) && $quantity > 0)
            ->all();
        $request->session()->put('cart', $cart);

        $items = collect($cart)->map(function (int $quantity, string $variantId) use ($variants) {
            $variant = $variants->get($variantId);
            if (! $variant) {
                return null;
            }

            $price = (int) $variant->price_rwf;

            return [
                'key' => (string) $variant->id,
                'variant_id' => $variant->id,
                'name' => $variant->product->name,
                'slug' => $variant->product->slug,
                'variant_name' => $variant->name,
                'image' => $variant->product->images->first()?->url,
                'image_alt' => $variant->product->images->first()?->alt_text ?: $variant->product->name,
                'price' => $price,
                'quantity' => $quantity,
                'available' => $variant->availableStock(),
                'line_total' => $price * $quantity,
            ];
        })->filter()->values();

        return inertia('Cart/Index', [
            'items' => $items,
            'subtotal' => $items->sum('line_total'),
            'deliveryLabel' => Setting::get('delivery_fee_confirmed') === '1'
                ? 'Calculated at checkout'
                : 'To be confirmed by the store',
        ]);
    }

    public function add(StoreCartItemRequest $request)
    {
        $validated = $request->validated();
        $variant = ProductVariant::with('product')
            ->where('is_active', true)
            ->whereHas('product', fn ($query) => $query->where('status', 'active'))
            ->findOrFail($validated['variant_id']);
        $cart = $request->session()->get('cart', []);
        $quantity = (int) ($cart[$variant->id] ?? 0) + $validated['quantity'];

        if ($quantity > $variant->availableStock()) {
            throw ValidationException::withMessages(['quantity' => 'The requested quantity is not available.']);
        }

        $cart[$variant->id] = $quantity;
        $request->session()->put('cart', $cart);

        if ($request->expectsJson()) {
            $cartPreview = ProductVariant::with(['product:id,name,slug', 'product.images'])
                ->whereIn('id', array_keys($cart))
                ->where('is_active', true)
                ->whereHas('product', fn ($query) => $query->where('status', 'active'))
                ->get()
                ->map(fn (ProductVariant $cartVariant) => [
                    'name' => $cartVariant->product->name,
                    'slug' => $cartVariant->product->slug,
                    'variant_name' => $cartVariant->name,
                    'quantity' => (int) $cart[$cartVariant->id],
                    'price_rwf' => (int) $cartVariant->price_rwf,
                    'image' => $cartVariant->product->images->first()?->url,
                    'image_alt' => $cartVariant->product->images->first()?->alt_text ?: $cartVariant->product->name,
                ])
                ->values();

            return response()->json([
                'success' => $variant->product->name.' added to your bag.',
                'cartCount' => $cartPreview->sum('quantity'),
                'cartPreview' => $cartPreview,
            ], 201);
        }

        return back()->with('success', $variant->product->name.' added to your bag.');
    }

    public function update(Request $request, string $item)
    {
        $validated = $request->validate(['quantity' => ['required', 'integer', 'min:1', 'max:99']]);
        $cart = $request->session()->get('cart', []);
        if (! array_key_exists($item, $cart)) {
            return redirect()->route('cart.index')->with('error', 'That item is no longer in your bag.');
        }

        $variant = ProductVariant::with('product')->where('is_active', true)->find($item);
        if (! $variant || $variant->product?->status !== 'active') {
            unset($cart[$item]);
            $request->session()->put('cart', $cart);

            return redirect()->route('cart.index')->with('error', 'That item is no longer available.');
        }

        if ($validated['quantity'] > $variant->availableStock()) {
            throw ValidationException::withMessages(['quantity' => 'The requested quantity is not available.']);
        }

        $cart[$item] = $validated['quantity'];
        $request->session()->put('cart', $cart);

        return back()->with('success', 'Bag updated.');
    }

    public function remove(Request $request, string $item)
    {
        $cart = $request->session()->get('cart', []);
        unset($cart[$item]);
        $request->session()->put('cart', $cart);

        return back()->with('success', 'Item removed from your bag.');
    }
}
