<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class ProductImage extends Model
{
    protected $fillable = ['product_id', 'path', 'alt_text', 'position'];

    protected $appends = ['url'];

    public function getUrlAttribute(): string
    {
        return Storage::disk(config('store.catalog_disk', 'public'))->url($this->path);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
