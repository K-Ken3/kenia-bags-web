<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;

class ProductVariant extends Model
{
    protected $fillable = ['product_id', 'name', 'sku', 'price', 'colour', 'price_rwf', 'stock_quantity', 'reserved', 'is_active'];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'price_rwf' => 'integer',
            'stock_quantity' => 'integer',
            'reserved' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function availableStock(): int
    {
        return max(0, $this->stock_quantity - $this->reserved);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
