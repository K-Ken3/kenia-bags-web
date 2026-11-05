<?php

use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\OrderController;
use App\Http\Controllers\Admin\PolicyPageController;
use App\Http\Controllers\Admin\ProofController;
use App\Http\Controllers\Admin\SandboxPaymentController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\AdminLoginController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\OrderLookupController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\PolicyController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ShopController;
use App\Http\Controllers\WebhookController;
use App\Http\Controllers\XentriPayController;
use App\Http\Controllers\XentriPayWebhookController;
use App\Models\Category;
use App\Models\PolicyPage;
use App\Models\ProductVariant;
use App\Models\Setting;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

// inertiajs/inertia-laravel also registers a global inertia() helper, so this one is named distinctly.
if (! function_exists('store_page')) {
    function store_page(string $component, array $props = [])
    {
        $cart = request()->session()->get('cart', []);
        $cartPreview = ProductVariant::with(['product:id,name,slug', 'product.images'])
            ->whereIn('id', array_keys($cart))
            ->where('is_active', true)
            ->whereHas('product', fn ($query) => $query->where('status', 'active'))
            ->get()
            ->map(fn (ProductVariant $variant) => [
                'name' => $variant->product->name,
                'slug' => $variant->product->slug,
                'variant_name' => $variant->name,
                'quantity' => (int) ($cart[$variant->id] ?? 0),
                'price_rwf' => (int) $variant->price_rwf,
                'image' => $variant->product->images->first()?->url,
                'image_alt' => $variant->product->images->first()?->alt_text ?: $variant->product->name,
            ])
            ->filter(fn (array $item) => $item['quantity'] > 0)
            ->values();
        $page = [
            'component' => $component,
            'props' => (object) array_merge($props, [
                'flash' => [
                    'success' => request()->session()->get('success'),
                    'error' => request()->session()->get('error'),
                ],
                'errors' => request()->session()->get('errors')?->getBag('default')->getMessages() ?? [],
                'brandName' => Setting::get('brand_name', 'Isimbi Mart'),
                'brandLogo' => Setting::get('brand_logo')
                    ? Storage::disk(config('store.catalog_disk', 'public'))->url(Setting::get('brand_logo'))
                    : asset('images/isimbi-mart-logo.png'),
                'storeCategories' => Category::where('is_active', true)
                    ->orderBy('position')
                    ->get(['id', 'name', 'slug']),
                'storePolicies' => PolicyPage::where('is_published', true)
                    ->whereIn('slug', ['privacy', 'terms', 'delivery', 'returns', 'contact'])
                    ->orderBy('title')
                    ->get(['slug', 'title']),
                'contactEmail' => Setting::get('contact_email', '[CONTACT_EMAIL]'),
                'contactPhone' => Setting::get('contact_phone', '[CONTACT_PHONE]'),
                'whatsappNumber' => Setting::get('whatsapp_number', '[WHATSAPP_NUMBER]'),
                'deliveryInformation' => Setting::get('delivery_information', '[DELIVERY INFORMATION — OWNER TO CONFIRM]'),
                'returnsInformation' => Setting::get('returns_information', '[RETURNS POLICY — OWNER TO REVIEW]'),
                'demoProductImages' => app()->environment(['local', 'testing'])
                    ? array_map(
                        fn (string $image) => asset('images/demo-bags/'.$image),
                        ['handbag-one.jpg', 'handbag-two.jpg', 'handbag-three.jpg', 'backpack.jpg'],
                    )
                    : [],
                'cartCount' => $cartPreview->sum('quantity'),
                'cartPreview' => $cartPreview,
            ]),
            'url' => request()->getPathInfo() ?: '/',
            'version' => null,
        ];

        return response()->view('app', ['page' => $page]);
    }
}

// Public routes
Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('/admin/login', [AdminLoginController::class, 'create'])->name('login');
Route::post('/admin/login', [AdminLoginController::class, 'store'])->middleware('throttle:5,1')->name('admin.login');
Route::post('/admin/logout', [AdminLoginController::class, 'destroy'])->middleware('auth')->name('admin.logout');
Route::get('/shop', [ShopController::class, 'index'])->name('shop.index');
Route::get('/products/{product:slug}', [ProductController::class, 'show'])->name('products.show');

// Cart
Route::get('/cart', [CartController::class, 'index'])->name('cart.index');
Route::post('/cart/add', [CartController::class, 'add'])->name('cart.add');
Route::patch('/cart/{item}', [CartController::class, 'update'])->name('cart.update');
Route::delete('/cart/{item}', [CartController::class, 'remove'])->name('cart.remove');

// Checkout (guest allowed)
Route::get('/checkout', [CheckoutController::class, 'index'])->name('checkout.index');
Route::post('/checkout', [CheckoutController::class, 'store'])->middleware('throttle:8,1')->name('checkout.store');

// Order lookup
Route::get('/order/lookup', [OrderLookupController::class, 'index'])->name('order.lookup');
Route::post('/order/lookup', [OrderLookupController::class, 'lookup'])->middleware('throttle:5,1')->name('order.lookup.post');
Route::get('/order/{order:public_reference}', [OrderLookupController::class, 'show'])->name('order.show');

// Payments
Route::get('/order/{order:public_reference}/proof', [PaymentController::class, 'show'])->name('payment.show');
Route::post('/order/{order:public_reference}/proof', [PaymentController::class, 'uploadProof'])->middleware('throttle:5,1')->name('payment.proof');
Route::post('/order/{order:public_reference}/xentripay/check', [XentriPayController::class, 'check'])->middleware('throttle:10,1')->name('xentripay.check');
Route::post('/order/{order:public_reference}/xentripay/retry', [XentriPayController::class, 'retry'])->middleware('throttle:5,1')->name('xentripay.retry');
Route::get('/order/{order:public_reference}/xentripay/return', [XentriPayController::class, 'returnFromGateway'])->middleware('throttle:20,1')->name('xentripay.return');

// Policies
Route::get('/policies/{slug}', [PolicyController::class, 'show'])->name('policies.show');

// Webhook
Route::post('/webhooks/payment', [WebhookController::class, 'handle'])->middleware('throttle:10,1')->name('webhooks.payment');
Route::post('/webhooks/xentripay', [XentriPayWebhookController::class, 'handle'])->middleware('throttle:30,1')->name('webhooks.xentripay');

// Admin routes
Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    Route::resource('categories', CategoryController::class);
    Route::resource('products', App\Http\Controllers\Admin\ProductController::class);
    Route::resource('orders', OrderController::class)->only(['index', 'show', 'update']);
    Route::post('orders/{order}/sandbox', [SandboxPaymentController::class, 'simulate'])->name('orders.sandbox');
    Route::get('proofs', [ProofController::class, 'index'])->name('proofs.index');
    Route::get('proofs/{proof}/file', [ProofController::class, 'show'])->name('proofs.show');
    Route::post('proofs/{proof}/approve', [ProofController::class, 'approve'])->name('proofs.approve');
    Route::post('proofs/{proof}/reject', [ProofController::class, 'reject'])->name('proofs.reject');
    Route::get('policies', [PolicyPageController::class, 'index'])->name('policies.index');
    Route::put('policies/{policyPage}', [PolicyPageController::class, 'update'])->name('policies.update');
    Route::get('settings', [SettingController::class, 'index'])->name('settings.index');
    Route::put('settings', [SettingController::class, 'update'])->name('settings.update');
});
