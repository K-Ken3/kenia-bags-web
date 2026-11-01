<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Payments\SandboxProvider;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class SandboxPaymentController extends Controller
{
    public function simulate(Request $request, Order $order)
    {
        abort_unless(app()->environment(['local', 'testing']) && config('payments.sandbox_enabled'), 404);
        $validated = $request->validate(['status' => ['required', 'in:paid,failed,pending,expired']]);
        $payment = $order->payments()->where('provider', 'sandbox')->first();
        if (! $payment) {
            throw ValidationException::withMessages(['payment' => 'This order does not have a sandbox payment.']);
        }

        app(SandboxProvider::class)->simulate($payment, $validated['status']);

        return back()->with('success', 'Sandbox payment status updated. SANDBOX — NOT REAL MONEY.');
    }
}
