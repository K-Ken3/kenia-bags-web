<?php

namespace App\Contracts;

use App\Models\Payment;
use Illuminate\Http\Request;

interface PaymentProvider
{
    public function initiate(Payment $payment): array;

    public function verify(Payment $payment): array;

    public function handleWebhook(Request $request): array;
}
