<?php

namespace App\Actions;

use App\Models\Order;
use App\Models\ProductVariant;

class OrderInventory
{
    public function release(Order $order): void
    {
        foreach ($order->items as $item) {
            $variant = ProductVariant::lockForUpdate()->find($item->variant_id);
            if ($variant) {
                $variant->update(['reserved' => max(0, $variant->reserved - $item->quantity)]);
            }
        }
    }

    public function consume(Order $order): void
    {
        foreach ($order->items as $item) {
            $variant = ProductVariant::lockForUpdate()->findOrFail($item->variant_id);
            if ($variant->reserved < $item->quantity || $variant->stock_quantity < $item->quantity) {
                throw new \DomainException('Reserved stock is no longer available.');
            }

            $variant->decrement('stock_quantity', $item->quantity);
            $variant->decrement('reserved', $item->quantity);
        }
    }
}
