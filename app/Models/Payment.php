<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    protected $fillable = ['order_id', 'method', 'amount', 'status', 'reference', 'provider', 'provider_reference', 'amount_rwf', 'currency', 'raw_status', 'verified_at', 'verified_by'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'amount_rwf' => 'integer', 'verified_at' => 'datetime'];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function proofs(): HasMany
    {
        return $this->hasMany(PaymentProof::class);
    }
}
