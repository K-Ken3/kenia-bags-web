<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProductUpsertRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() === true;
    }

    public function rules(): array
    {
        return [
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'name' => ['required', 'string', 'max:160'],
            'slug' => ['required', 'alpha_dash', 'max:180', Rule::unique('products', 'slug')->ignore($this->route('product'))],
            'description' => ['nullable', 'string', 'max:10000'],
            'price_rwf' => ['required', 'integer', 'min:0'],
            'status' => ['required', Rule::in(['draft', 'active', 'archived'])],
            'is_featured' => ['sometimes', 'boolean'],
            'variants' => ['required', 'array', 'min:1', 'max:30'],
            'variants.*.id' => ['nullable', 'integer', 'exists:product_variants,id'],
            'variants.*.name' => ['required', 'string', 'max:120'],
            'variants.*.colour' => ['nullable', 'string', 'max:40'],
            'variants.*.sku' => ['nullable', 'string', 'max:100'],
            'variants.*.price_rwf' => ['nullable', 'integer', 'min:0'],
            'variants.*.stock_quantity' => ['required', 'integer', 'min:0'],
            'image' => ['nullable', 'image', 'mimes:jpeg,png,webp', 'max:5120'],
            'image_alt' => ['nullable', 'string', 'max:255'],
        ];
    }
}
