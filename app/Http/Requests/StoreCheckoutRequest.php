<?php

namespace App\Http\Requests;

use App\Payments\XentriPayClient;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;

class StoreCheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'customer_name' => ['required', 'string', 'max:120'],
            'customer_phone' => [
                'required',
                'string',
                'max:32',
                function (string $attribute, mixed $value, \Closure $fail) {
                    if ($this->input('payment_method') !== 'xentripay_momo') {
                        return;
                    }

                    try {
                        XentriPayClient::normalizePhone((string) $value);
                    } catch (ValidationException) {
                        $fail('Enter a valid Rwandan mobile money number, for example 0780 000 000.');
                    }
                },
            ],
            'customer_email' => ['nullable', 'email', 'max:255', 'required_if:payment_method,xentripay_momo'],
            'delivery_location' => ['required', 'string', 'max:500'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'payment_method' => ['required', 'in:manual_momo,sandbox,xentripay_momo'],
            'idempotency_key' => ['required', 'uuid'],
        ];
    }
}
