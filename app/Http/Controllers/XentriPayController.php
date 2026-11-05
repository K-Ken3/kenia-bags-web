<?php

namespace App\Http\Controllers;

use App\Actions\InitiateXentriPayCollection;
use App\Exceptions\PaymentProviderException;
use App\Models\Order;
use App\Models\Payment;
use App\Payments\XentriPayClient;
use App\Payments\XentriPayProvider;
use Illuminate\Http\Request;

class XentriPayController extends Controller
{
    public function check(Request $request, Order $order)
    {
        abort_unless($request->session()->get('verified_order_reference') === $order->public_reference, 403);

        $payment = $this->latestPayment($order);
        if (! $payment) {
            return back()->with('error', 'This order has no mobile money payment.');
        }
        if ($payment->status === 'paid') {
            return back()->with('success', 'This payment is already confirmed.');
        }
        if (! $payment->reference) {
            return back()->with('error', 'No payment request was sent to your phone. Use Retry payment.');
        }

        try {
            $result = app(XentriPayProvider::class)->verify($payment);
        } catch (PaymentProviderException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', match ($result['status']) {
            'paid' => 'Payment confirmed. Thank you!',
            'failed' => 'The mobile money request failed. You can retry the payment.',
            default => 'Payment is still pending. Approve the prompt on your phone and check again.',
        });
    }

    public function retry(Request $request, Order $order, InitiateXentriPayCollection $initiate)
    {
        abort_unless($request->session()->get('verified_order_reference') === $order->public_reference, 403);

        if (! app(XentriPayClient::class)->enabled()) {
            return back()->with('error', 'Mobile money payment is not currently available.');
        }
        if ($order->payment_status === 'paid' || $order->order_status !== 'awaiting_payment') {
            return back()->with('error', 'This order can no longer be paid online.');
        }
        if ($order->expires_at?->isPast()) {
            return back()->with('error', 'This order has expired. Please place a new order.');
        }

        $latest = $this->latestPayment($order);
        if ($latest && ! in_array($latest->status, ['failed', 'expired'], true)) {
            return back()->with('error', 'A mobile money request is still awaiting confirmation. Check its status first.');
        }

        $payment = Payment::create([
            'order_id' => $order->id,
            'method' => 'xentripay_momo',
            'provider' => 'xentripay_momo',
            'amount' => $order->total_rwf,
            'amount_rwf' => $order->total_rwf,
            'currency' => 'RWF',
            'status' => 'pending',
            'raw_status' => 'pending',
        ]);
        $order->update(['payment_status' => 'pending']);

        try {
            $initiate->handle($order, $payment);
        } catch (PaymentProviderException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'A new payment request was sent to your phone.');
    }

    private function latestPayment(Order $order): ?Payment
    {
        return $order->payments()->where('provider', 'xentripay_momo')->latest('id')->first();
    }
}
