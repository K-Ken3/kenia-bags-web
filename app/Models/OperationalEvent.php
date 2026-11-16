<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class OperationalEvent extends Model
{
    public const SEVERITIES = ['info', 'warning', 'critical'];

    protected $fillable = ['type', 'severity', 'message', 'context', 'occurred_at'];

    protected function casts(): array
    {
        return ['context' => 'array', 'occurred_at' => 'datetime'];
    }

    public function scopeSince(Builder $query, \DateTimeInterface $since): Builder
    {
        return $query->where('occurred_at', '>=', $since);
    }
}
