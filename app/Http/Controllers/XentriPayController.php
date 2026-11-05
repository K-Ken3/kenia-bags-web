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
            return back()->with('error', 'This order has no online payment.');
        }
        if ($payment->status === 'paid') {
            return back()->with('success', 'This payment is already confirmed.');
        }
        if (! $payment->reference) {
            return back()->with('error', 'No payment request was started. Use Retry payment.');
        }

        try {
            $result = app(XentriPayProvider::class)->verify($payment);
        } catch (PaymentProviderException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        $label = XentriPayProvider::label(XentriPayProvider::methodFor($payment));

        return back()->with('success', match ($result['status']) {
            'paid' => 'Payment confirmed. Thank you!',
            'failed' => 'The '.$label.' payment failed. You can retry the payment.',
            default => $payment->provider === 'xentripay_card'
                ? 'Payment is still pending. Complete the card payment page, then check again.'
                : 'Payment is still pending. Approve the prompt on your phone and check again.',
        });
    }

    public function returnFromGateway(Order $order)
    {
        $payment = $this->latestPayment($order);

        if (! $payment) {
            return redirect()->route('order.show', $order);
        }
        if ($payment->status === 'paid') {
            return redirect()->route('order.show', $order)->with('success', 'Payment confirmed. Thank you!');
        }
        if (! $payment->reference) {
            return redirect()->route('order.show', $order)
                ->with('error', 'No payment request was started. Use Retry payment.');
        }

        try {
            $result = app(XentriPayProvider::class)->verify($payment);
        } catch (PaymentProviderException $exception) {
            return redirect()->route('order.show', $order)
                ->with('error', 'We could not confirm the card payment yet: '.$exception->getMessage());
        }

        return redirect()->route('order.show', $order)->with(
            $result['status'] === 'failed' ? 'error' : 'success',
            match ($result['status']) {
                'paid' => 'Payment confirmed. Thank you!',
                'failed' => 'The card payment failed. You can retry the payment.',
                default => 'Your bank is still processing the card payment. Check the status again in a moment.',
            },
        );
    }

    public function retry(Request $request, Order $order, InitiateXentriPayCollection $initiate)
    {
        abort_unless($request->session()->get('verified_order_reference') === $order->public_reference, 403);

        if (! app(XentriPayClient::class)->enabled()) {
            return back()->with('error', 'Online payment is not currently available.');
        }
        if ($order->payment_status === 'paid' || $order->order_status !== 'awaiting_payment') {
            return back()->with('error', 'This order can no longer be paid online.');
        }
        if ($order->expires_at?->isPast()) {
            return back()->with('error', 'This order has expired. Please place a new order.');
        }

        $latest = $this->latestPayment($order);
        if ($latest && ! in_array($latest->status, ['failed', 'expired'], true)) {
            return back()->with('error', 'A payment request is still awaiting confirmation. Check its status first.');
        }

        $method = $latest ? XentriPayProvider::methodFor($latest) : 'xentripay_momo';
        $payment = Payment::create([
            'order_id' => $order->id,
            'method' => $method,
            'provider' => $method,
            'amount' => $order->total_rwf,
            'amount_rwf' => $order->total_rwf,
            'currency' => 'RWF',
            'status' => 'pending',
            'raw_status' => 'pending',
        ]);
        $order->update(['payment_status' => 'pending']);

        try {
            $result = $initiate->handle($order, $payment);
        } catch (PaymentProviderException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        if ($result['checkout_url'] ?? null) {
            return redirect()->away($result['checkout_url']);
        }

        return back()->with('success', 'A new '.XentriPayProvider::label($method).' request was sent to your phone.');
    }

    private function latestPayment(Order $order): ?Payment
    {
        return $order->payments()->whereIn('provider', XentriPayProvider::METHODS)->latest('id')->first();
    }
}
