<?php

namespace App\Actions;

use App\Exceptions\PaymentProviderException;
use App\Models\Order;
use App\Models\Payment;
use App\Payments\XentriPayProvider;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class InitiateXentriPayCollection
{
    public function handle(Order $order, Payment $payment): void
    {
        try {
            app(XentriPayProvider::class)->initiate($payment);
        } catch (\Throwable $exception) {
            $message = $exception instanceof PaymentProviderException
                ? $exception->getMessage()
                : 'The mobile money request could not be started. Please try again.';

            if (! $exception instanceof PaymentProviderException) {
                Log::error('XentriPay collection initiation failed.', [
                    'order_id' => $order->id,
                    'payment_id' => $payment->id,
                    'exception' => $exception,
                ]);
            }

            DB::transaction(function () use ($order, $payment, $message) {
                $payment->update([
                    'status' => 'failed',
                    'raw_status' => 'xentripay.initiation_failed: '.$message,
                ]);
                if ($order->order_status === 'awaiting_payment') {
                    $order->update(['payment_status' => 'failed']);
                }
            });

            throw new PaymentProviderException($message, previous: $exception);
        }
    }
}
