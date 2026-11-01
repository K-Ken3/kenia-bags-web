<?php

namespace App\Actions;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\ProductVariant;
use App\Models\Setting;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CreateStoreOrder
{
    public function execute(array $cart, array $customer, string $idempotencyKey): Order
    {
        if ($existing = Order::where('idempotency_key', $idempotencyKey)->first()) {
            return $existing;
        }

        if ($cart === []) {
            throw ValidationException::withMessages(['cart' => 'Your cart is empty.']);
        }

        $deliveryFee = Setting::integer('delivery_fee_rwf');
        $expiryMinutes = max(5, Setting::integer('unpaid_order_expiry_minutes', 60));

        try {
            return DB::transaction(function () use ($cart, $customer, $idempotencyKey, $deliveryFee, $expiryMinutes) {
                if ($existing = Order::where('idempotency_key', $idempotencyKey)->first()) {
                    return $existing;
                }

                $lines = [];
                $subtotal = 0;
                foreach ($cart as $variantId => $quantity) {
                    $variant = ProductVariant::with('product')->lockForUpdate()->find($variantId);
                    if (! $variant || ! $variant->is_active || $variant->product?->status !== 'active') {
                        throw ValidationException::withMessages(['cart' => 'A bag in your cart is no longer available.']);
                    }

                    $quantity = filter_var($quantity, FILTER_VALIDATE_INT);
                    if ($quantity === false || $quantity < 1 || $quantity > $variant->availableStock()) {
                        throw ValidationException::withMessages(['cart' => $variant->product->name.' does not have enough stock.']);
                    }

                    $price = (int) $variant->price_rwf;
                    if ($price < 0 || $price > intdiv(PHP_INT_MAX - $subtotal, $quantity)) {
                        throw ValidationException::withMessages(['cart' => 'The cart total is invalid.']);
                    }
                    $subtotal += $price * $quantity;
                    $lines[] = [$variant, $quantity, $price];
                }

                $reference = 'KB-'.Str::upper(Str::random(24));
                if ($deliveryFee > PHP_INT_MAX - $subtotal) {
                    throw ValidationException::withMessages(['cart' => 'The order total is invalid.']);
                }

                $order = Order::create([
                    'public_reference' => $reference,
                    'idempotency_key' => $idempotencyKey,
                    'user_id' => auth()->id(),
                    'customer_name' => $customer['customer_name'],
                    'customer_phone' => $customer['customer_phone'],
                    'customer_email' => $customer['customer_email'] ?? null,
                    'delivery_location' => $customer['delivery_location'],
                    'shipping_address' => $customer['delivery_location'],
                    'notes' => $customer['notes'] ?? null,
                    'subtotal_rwf' => $subtotal,
                    'delivery_fee_rwf' => $deliveryFee,
                    'total_rwf' => $subtotal + $deliveryFee,
                    'subtotal' => $subtotal,
                    'shipping_fee' => $deliveryFee,
                    'total' => $subtotal + $deliveryFee,
                    'order_status' => 'awaiting_payment',
                    'status' => 'awaiting_payment',
                    'payment_status' => 'pending',
                    'delivery_status' => 'not_dispatched',
                    'expires_at' => now()->addMinutes($expiryMinutes),
                ]);

                foreach ($lines as [$variant, $quantity, $price]) {
                    OrderItem::create([
                        'order_id' => $order->id,
                        'product_id' => $variant->product_id,
                        'product_variant_id' => $variant->id,
                        'variant_id' => $variant->id,
                        'product_name' => $variant->product->name,
                        'variant_name' => $variant->name,
                        'unit_price' => $price,
                        'unit_price_rwf' => $price,
                        'quantity' => $quantity,
                        'line_total' => $price * $quantity,
                    ]);
                    $variant->increment('reserved', $quantity);
                }

                Payment::create([
                    'order_id' => $order->id,
                    'method' => $customer['payment_method'],
                    'provider' => $customer['payment_method'],
                    'amount' => $order->total_rwf,
                    'amount_rwf' => $order->total_rwf,
                    'currency' => 'RWF',
                    'status' => 'pending',
                    'raw_status' => 'pending',
                ]);

                return $order;
            }, attempts: 3);
        } catch (UniqueConstraintViolationException $exception) {
            $existing = Order::where('idempotency_key', $idempotencyKey)->first();
            if ($existing) {
                return $existing;
            }

            throw $exception;
        }
    }
}
