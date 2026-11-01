<?php

namespace App\Console\Commands;

use App\Actions\OrderInventory;
use App\Models\Order;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

#[Signature('app:expire-orders')]
#[Description('Expire unpaid orders and release reserved stock')]
class ExpireOrders extends Command
{
    public function handle(OrderInventory $inventory): int
    {
        $orders = Order::whereIn('payment_status', ['pending', 'under_review'])
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->whereNotIn('order_status', ['cancelled', 'delivered'])
            ->get();

        foreach ($orders as $candidate) {
            DB::transaction(function () use ($candidate, $inventory) {
                $order = Order::with('items')->lockForUpdate()->find($candidate->id);
                if (! $order || ! in_array($order->payment_status, ['pending', 'under_review'], true)
                    || $order->expires_at?->isFuture()) {
                    return;
                }

                $inventory->release($order);
                $order->update([
                    'order_status' => 'cancelled',
                    'status' => 'cancelled',
                    'payment_status' => 'expired',
                ]);
                $order->payments()->whereIn('status', ['pending', 'under_review'])->update(['status' => 'expired']);
                $order->payments()->with('proofs')->get()->each(
                    fn ($payment) => $payment->proofs()->where('status', 'pending')->update(['status' => 'expired']),
                );
            });
        }

        $this->info($orders->count().' unpaid order(s) expired.');
        return self::SUCCESS;
    }
}
