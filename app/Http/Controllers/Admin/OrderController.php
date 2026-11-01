<?php

namespace App\Http\Controllers\Admin;

use App\Actions\OrderInventory;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateOrderStatusRequest;
use App\Models\AuditLog;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'order_status' => ['nullable', 'string', 'max:40'],
            'payment_status' => ['nullable', 'string', 'max:40'],
        ]);
        $orders = Order::query()->when($filters['search'] ?? null, function ($query, $search) {
            $query->where(fn ($orders) => $orders
                ->where('public_reference', 'like', '%'.$search.'%')
                ->orWhere('customer_name', 'like', '%'.$search.'%')
                ->orWhere('customer_phone', 'like', '%'.$search.'%'));
        })->when($filters['order_status'] ?? null, fn ($query, $status) => $query->where('order_status', $status))
            ->when($filters['payment_status'] ?? null, fn ($query, $status) => $query->where('payment_status', $status))
            ->latest()->paginate(25)->withQueryString();

        return inertia('Admin/Orders/Index', ['orders' => $orders, 'filters' => $filters]);
    }

    public function show(Order $order)
    {
        return inertia('Admin/Orders/Show', [
            'order' => $order->load(['items', 'payments.proofs']),
            'sandboxEnabled' => app()->environment(['local', 'testing']) && config('payments.sandbox_enabled'),
        ]);
    }

    public function update(UpdateOrderStatusRequest $request, Order $order, OrderInventory $inventory)
    {
        $data = $request->validated();
        $target = $data['order_status'];

        DB::transaction(function () use ($order, $target, $inventory) {
            $order = Order::with('items')->lockForUpdate()->findOrFail($order->id);
            if ($target !== $order->order_status
                && ! in_array($target, Order::allowedTransitions()[$order->order_status] ?? [], true)) {
                throw ValidationException::withMessages(['order_status' => 'That order status transition is not allowed.']);
            }
            if (in_array($target, ['packing', 'dispatched', 'delivered'], true) && $order->payment_status !== 'paid') {
                throw ValidationException::withMessages(['order_status' => 'Only paid orders can enter fulfilment.']);
            }

            if ($target === 'cancelled' && $order->order_status !== 'cancelled') {
                $inventory->release($order);
                if ($order->payment_status !== 'paid') {
                    $order->update(['payment_status' => 'cancelled']);
                    $order->payments()->whereIn('status', ['pending', 'under_review'])->update(['status' => 'cancelled']);
                    $order->payments()->with('proofs')->get()->each(
                        fn ($payment) => $payment->proofs()->where('status', 'pending')->update(['status' => 'cancelled']),
                    );
                }
            }
            if ($target !== $order->order_status) {
                $order->transitionTo($target);
            }
            if ($target === 'dispatched' || $target === 'delivered') {
                $order->update(['delivery_status' => $target]);
            }
            AuditLog::create([
                'user_id' => auth()->id(),
                'action' => 'order.status_changed',
                'subject_type' => Order::class,
                'subject_id' => $order->id,
                'metadata' => ['order_status' => $target],
            ]);
        });

        return back()->with('success', 'Order status updated.');
    }
}
