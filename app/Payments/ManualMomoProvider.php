<?php

namespace App\Payments;

use App\Contracts\PaymentProvider;
use App\Models\Payment;
use App\Models\Setting;
use Illuminate\Http\Request;

class ManualMomoProvider implements PaymentProvider
{
    public function initiate(Payment $payment): array
    {
        return [
            'method' => 'manual_momo',
            'pay_code' => Setting::get('momo_pay_code'),
            'amount_rwf' => $payment->amount_rwf,
            'order_reference' => $payment->order->public_reference,
            'automatically_verified' => false,
        ];
    }

    public function verify(Payment $payment): array
    {
        return ['status' => $payment->status, 'verified' => false];
    }

    public function handleWebhook(Request $request): array
    {
        abort(503, 'Manual MoMo payments do not provide an automatic webhook.');
    }
}
