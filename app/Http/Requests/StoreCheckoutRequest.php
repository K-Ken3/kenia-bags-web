<?php

namespace App\Http\Requests;

use App\Payments\XentriPayClient;
use App\Payments\XentriPayProvider;
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
                    $method = (string) $this->input('payment_method');
                    if (! in_array($method, ['xentripay_momo', 'xentripay_airtel'], true)) {
                        return;
                    }

                    try {
                        XentriPayClient::normalizePhone((string) $value, $method);
                    } catch (ValidationException) {
                        $fail(XentriPayClient::phoneErrorMessage($method));
                    }
                },
            ],
            'customer_email' => ['nullable', 'email', 'max:255', 'required_if:payment_method,xentripay_momo,xentripay_airtel,xentripay_card'],
            'delivery_location' => ['required', 'string', 'max:500'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'payment_method' => ['required', 'in:manual_momo,sandbox,'.implode(',', XentriPayProvider::METHODS)],
            'idempotency_key' => ['required', 'uuid'],
        ];
    }
}
