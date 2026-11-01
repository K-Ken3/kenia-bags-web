<?php

namespace App\Payments;

use App\Contracts\PaymentProvider;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class PaymentManager
{
    public function provider(string $name): PaymentProvider
    {
        return match ($name) {
            'manual_momo' => app(ManualMomoProvider::class),
            'sandbox' => app(SandboxProvider::class),
            default => $this->unavailable($name),
        };
    }

    private function unavailable(string $name): never
    {
        Log::warning('Requested payment provider is not configured.', ['provider' => $name]);
        throw ValidationException::withMessages(['payment_method' => 'This payment provider is not configured.']);
    }
}
