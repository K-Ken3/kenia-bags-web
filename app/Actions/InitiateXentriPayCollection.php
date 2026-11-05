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
            $rawMessage = $exception instanceof PaymentProviderException
                ? $exception->getMessage()
                : 'The mobile money request could not be started. Please try again.';

            if ($exception instanceof PaymentProviderException) {
                Log::warning('XentriPay collection initiation rejected.', [
                    'order_id' => $order->id,
                    'payment_id' => $payment->id,
                    'message' => $rawMessage,
                ]);
            } else {
                Log::error('XentriPay collection initiation failed.', [
                    'order_id' => $order->id,
                    'payment_id' => $payment->id,
                    'exception' => $exception,
                ]);
            }

            $publicMessage = $rawMessage;
            if (str_contains(strtolower($rawMessage), 'no approved business charge setting')) {
                $publicMessage = 'XentriPay has not approved a charge setting for this merchant account. Ask XentriPay to enable collections, then retry.';
            }
            if (! app()->environment(['local', 'testing'])) {
                $publicMessage = 'Mobile money is temporarily unavailable. Please try again or choose another payment method.';
            }

            DB::transaction(function () use ($order, $payment, $rawMessage) {
                $payment->update([
                    'status' => 'failed',
                    'raw_status' => 'xentripay.initiation_failed: '.$rawMessage,
                ]);
                if ($order->order_status === 'awaiting_payment') {
                    $order->update(['payment_status' => 'failed']);
                }
            });

            throw new PaymentProviderException($publicMessage, previous: $exception);
        }
    }
}
