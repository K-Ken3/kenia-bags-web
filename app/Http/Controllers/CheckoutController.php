<?php

namespace App\Http\Controllers;

use App\Actions\CreateStoreOrder;
use App\Actions\InitiateXentriPayCollection;
use App\Exceptions\PaymentProviderException;
use App\Http\Requests\StoreCheckoutRequest;
use App\Models\ProductVariant;
use App\Models\Setting;
use App\Payments\XentriPayClient;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CheckoutController extends Controller
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
        $items = collect($cart)->map(function (int $quantity, string $variantId) use ($variants) {
            $variant = $variants->get($variantId);
            if (! $variant) {
                return null;
            }

            $price = (int) $variant->price_rwf;
            return [
                'variant_id' => $variant->id,
                'name' => $variant->product->name,
                'variant_name' => $variant->name,
                'available' => $variant->availableStock(),
                'price' => $price,
                'quantity' => $quantity,
                'line_total' => $price * $quantity,
            ];
        })->filter()->values();

        $methods = [];
        $payCode = Setting::get('momo_pay_code');
        if (Setting::get('manual_momo_enabled') === '1' && $payCode && ! str_starts_with($payCode, '[')) {
            $methods[] = ['value' => 'manual_momo', 'label' => 'MoMo pay code'];
        }
        if (app()->environment(['local', 'testing']) && config('payments.sandbox_enabled')) {
            $methods[] = ['value' => 'sandbox', 'label' => 'SANDBOX — NOT REAL MONEY'];
        }
        if ($this->xentriPayEnabled()) {
            $methods[] = ['value' => 'xentripay_momo', 'label' => $this->xentriPayLabel()];
        }

        return store_page('Checkout/Index', [
            'items' => $items,
            'subtotal' => $items->sum('line_total'),
            'deliveryFee' => Setting::integer('delivery_fee_rwf'),
            'deliveryLabel' => Setting::get('delivery_fee_confirmed') === '1' ? null : 'Delivery fee not yet confirmed',
            'canPlaceOrder' => $items->isNotEmpty()
                && $methods !== []
                && Setting::get('delivery_fee_confirmed') === '1',
            'paymentMethods' => $methods,
            'idempotencyKey' => $request->session()->get('checkout_idempotency_key')
                ?? tap((string) Str::uuid(), fn (string $key) => $request->session()->put('checkout_idempotency_key', $key)),
        ]);
    }

    public function store(StoreCheckoutRequest $request, CreateStoreOrder $createOrder, InitiateXentriPayCollection $initiate)
    {
        $validated = $request->validated();
        $enabledMethods = collect($this->availablePaymentMethods())->pluck('value');
        if (! $enabledMethods->contains($validated['payment_method'])) {
            throw ValidationException::withMessages(['payment_method' => 'That payment method is not currently available.']);
        }
        if (Setting::get('delivery_fee_confirmed') !== '1') {
            throw ValidationException::withMessages(['delivery_fee' => 'Checkout is unavailable until the store confirms its delivery fee.']);
        }

        $order = $createOrder->execute(
            $request->session()->get('cart', []),
            $validated,
            $validated['idempotency_key'],
        );

        if ($request->session()->get('cart', []) !== []) {
            $request->session()->forget('cart');
        }
        $request->session()->put('verified_order_reference', $order->public_reference);
        $request->session()->forget('checkout_idempotency_key');

        $message = 'Your order has been received.';

        if ($validated['payment_method'] === 'xentripay_momo') {
            $payment = $order->payments()->where('provider', 'xentripay_momo')->latest('id')->first();
            if ($payment && $payment->status === 'pending' && ! $payment->reference) {
                try {
                    $initiate->handle($order, $payment);
                    $message = 'Your order has been received. Approve the mobile money prompt on your phone.';
                } catch (PaymentProviderException $exception) {
                    return redirect()->route('order.show', $order)
                        ->with('error', 'Your order was created, but the mobile money request could not be started: '.$exception->getMessage());
                }
            }
        }

        return redirect()->route('order.show', $order)->with('success', $message);
    }

    private function availablePaymentMethods(): array
    {
        $methods = [];
        $payCode = Setting::get('momo_pay_code');
        if (Setting::get('manual_momo_enabled') === '1' && $payCode && ! str_starts_with($payCode, '[')) {
            $methods[] = ['value' => 'manual_momo'];
        }
        if (app()->environment(['local', 'testing']) && config('payments.sandbox_enabled')) {
            $methods[] = ['value' => 'sandbox'];
        }
        if ($this->xentriPayEnabled()) {
            $methods[] = ['value' => 'xentripay_momo'];
        }

        return $methods;
    }

    private function xentriPayEnabled(): bool
    {
        return (bool) config('payments.xentripay.enabled') && filled(config('payments.xentripay.api_key'));
    }

    private function xentriPayLabel(): string
    {
        return app(XentriPayClient::class)->sandbox()
            ? 'Mobile Money — XentriPay (sandbox, no real money)'
            : 'Mobile Money — XentriPay';
    }
}
