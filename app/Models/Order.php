<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $fillable = [
        'user_id', 'order_number', 'customer_name', 'customer_email', 'customer_phone',
        'shipping_address', 'notes', 'subtotal', 'shipping_fee', 'total', 'status', 'payment_status',
        'public_reference', 'idempotency_key', 'delivery_location', 'subtotal_rwf',
        'delivery_fee_rwf', 'total_rwf', 'order_status', 'delivery_status', 'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'shipping_fee' => 'decimal:2',
            'total' => 'decimal:2',
            'subtotal_rwf' => 'integer',
            'delivery_fee_rwf' => 'integer',
            'total_rwf' => 'integer',
            'expires_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'public_reference';
    }

    public static function allowedTransitions(): array
    {
        return [
            'awaiting_payment' => ['payment_review', 'cancelled'],
            'payment_review' => ['awaiting_payment', 'cancelled'],
            'confirmed' => ['packing', 'cancelled'],
            'packing' => ['dispatched', 'cancelled'],
            'dispatched' => ['delivered'],
            'delivered' => [],
            'cancelled' => [],
        ];
    }

    public function transitionTo(string $status): void
    {
        if (! in_array($status, self::allowedTransitions()[$this->order_status] ?? [], true)) {
            throw new \DomainException("Order status cannot transition from {$this->order_status} to {$status}.");
        }
        if ($status === 'confirmed' && $this->payment_status !== 'paid') {
            throw new \DomainException('An unpaid order cannot be confirmed.');
        }

        $this->update(['order_status' => $status, 'status' => $status]);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function getStatusLabelAttribute(): string
    {
        return str($this->order_status)->replace('_', ' ')->title()->toString();
    }
}
