<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() === true;
    }

    public function rules(): array
    {
        return [
            'brand_name' => ['required', 'string', 'max:120'],
            'brand_logo' => ['nullable', 'image', 'mimes:jpeg,png,webp', 'max:5120'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:40'],
            'whatsapp_number' => ['nullable', 'string', 'max:40'],
            'delivery_fee_rwf' => ['required', 'integer', 'min:0'],
            'delivery_fee_confirmed' => ['sometimes', 'boolean'],
            'delivery_information' => ['nullable', 'string', 'max:500'],
            'manual_momo_enabled' => ['sometimes', 'boolean'],
            'momo_pay_code' => ['nullable', 'string', 'max:120'],
            'unpaid_order_expiry_minutes' => ['required', 'integer', 'min:5', 'max:10080'],
        ];
    }
}
