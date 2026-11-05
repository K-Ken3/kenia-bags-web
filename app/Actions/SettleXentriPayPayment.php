<?php

namespace App\Actions;

use App\Exceptions\PaymentProviderException;
use App\Models\Order;
use App\Models\Payment;
use App\Models\ProductVariant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SettleXentriPayPayment
{
    public function handle(Payment $payment, string $status, array $payload = [], string $source = 'xentripay'): array
    {
        if (! in_array($status, ['paid', 'failed', 'pending'], true)) {
            $status = 'pending';
        }

        return DB::transaction(function () use ($payment, $status, $payload, $source) {
            $payment = Payment::lockForUpdate()->findOrFail($payment->id);
            $order = Order::with('items')->lockForUpdate()->findOrFail($payment->order_id);

            if ($payment->provider !== 'xentripay_momo') {
                throw new PaymentProviderException('This is not a XentriPay payment.');
            }
            if ($payment->currency !== 'RWF' || (int) $payment->amount_rwf !== (int) $order->total_rwf) {
                throw new PaymentProviderException('The XentriPay payment does not match the order total.');
            }

            $rawStatus = 'xentripay.'.$status.' ('.$source.')';
            $context = ['payment_id' => $payment->id, 'order_id' => $order->id, 'source' => $source, 'payload' => $payload];

            if ($status === 'paid') {
                if ($order->payment_status === 'paid') {
                    $payment->update(['raw_status' => $rawStatus]);

                    return ['status' => 'paid', 'applied' => false, 'reason' => 'already_paid'];
                }

                if (! in_array($order->order_status, ['awaiting_payment', 'payment_review'], true)
                    || ! in_array($order->payment_status, ['pending', 'under_review', 'failed'], true)) {
                    // Money was collected after the order was closed. Record it for the admin
                    // instead of silently consuming inventory that may already be released.
                    $payment->update(['status' => 'paid', 'verified_at' => now(), 'raw_status' => 'xentripay.paid_after_close']);
                    Log::warning('XentriPay payment received for a closed order.', $context);

                    return ['status' => 'paid', 'applied' => false, 'reason' => 'order_closed'];
                }

                if (! $this->canFulfil($order)) {
                    // Payment arrived after the expiry job released the hold. Keep the order
                    // open for an admin decision and never confirm unavailable stock.
                    $payment->update(['status' => 'paid', 'verified_at' => now(), 'raw_status' => 'xentripay.paid_without_stock']);
                    $order->update(['payment_status' => 'paid', 'order_status' => 'payment_review', 'status' => 'payment_review']);
                    Log::warning('XentriPay payment received without reserved stock.', $context);

                    return ['status' => 'paid', 'applied' => false, 'reason' => 'stock_unavailable'];
                }

                app(OrderInventory::class)->consume($order);
                $payment->update(['status' => 'paid', 'verified_at' => now(), 'raw_status' => $rawStatus]);
                $order->update(['payment_status' => 'paid', 'order_status' => 'confirmed', 'status' => 'confirmed']);

                return ['status' => 'paid', 'applied' => true];
            }

            if ($status === 'failed') {
                if (in_array($order->payment_status, ['pending', 'under_review', 'failed'], true)
                    && $order->order_status === 'awaiting_payment') {
                    $payment->update(['status' => 'failed', 'raw_status' => $rawStatus]);
                    $order->update(['payment_status' => 'failed']);

                    return ['status' => 'failed', 'applied' => true];
                }

                $payment->update(['raw_status' => $rawStatus]);

                return ['status' => 'failed', 'applied' => false];
            }

            $payment->update(['raw_status' => $rawStatus]);

            return ['status' => 'pending', 'applied' => false];
        });
    }

    private function canFulfil(Order $order): bool
    {
        foreach ($order->items as $item) {
            $variant = ProductVariant::lockForUpdate()->find($item->variant_id);
            if (! $variant || $variant->reserved < $item->quantity || $variant->stock_quantity < $item->quantity) {
                return false;
            }
        }

        return true;
    }
}
