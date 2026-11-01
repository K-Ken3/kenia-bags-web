<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $fillable = ['category_id', 'name', 'slug', 'description', 'sku', 'base_price', 'price_rwf', 'stock_quantity', 'is_active', 'is_featured', 'status'];

    protected function casts(): array
    {
        return [
            'base_price' => 'decimal:2',
            'price_rwf' => 'integer',
            'stock_quantity' => 'integer',
            'is_active' => 'boolean',
            'is_featured' => 'boolean',
        ];
    }

    public function availableStock(): int
    {
        return max(0, (int) $this->variants()->where('is_active', true)->sum('stock_quantity')
            - (int) $this->variants()->where('is_active', true)->sum('reserved'));
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('position');
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }
}
