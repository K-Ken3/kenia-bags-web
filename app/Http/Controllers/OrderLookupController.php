<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\Request;

class OrderLookupController extends Controller
{
    public function index()
    {
        return store_page('Order/Lookup');
    }

    public function lookup(Request $request)
    {
        $validated = $request->validate([
            'public_reference' => ['required', 'string', 'regex:/^KB-[A-Z0-9]{20,30}$/'],
            'phone' => 'required|string',
        ]);

        $order = Order::where('public_reference', $validated['public_reference'])
            ->where('customer_phone', $validated['phone'])
            ->first();

        if (! $order) {
            return back()->with('error', 'Order not found');
        }

        $request->session()->put('verified_order_reference', $order->public_reference);

        return redirect()->route('order.show', $order);
    }

    public function show(Order $order)
    {
        if (request()->session()->get('verified_order_reference') !== $order->public_reference) {
            return redirect()->route('order.lookup')
                ->with('error', 'Verify your order number and phone number to view order details.');
        }

        return store_page('Order/Show', [
            'order' => $order->load(['items', 'payments.proofs']),
            'momoPayCode' => $order->payments->first()?->provider === 'manual_momo'
                ? \App\Models\Setting::get('momo_pay_code')
                : null,
            'whatsappNumber' => \App\Models\Setting::get('whatsapp_number'),
        ]);
    }
}
