<?php

namespace App\Models;

use Illuminate\Support\Facades\Cache;
use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $fillable = ['key', 'value'];

    protected static function booted(): void
    {
        static::saved(fn (Setting $setting) => Cache::forget('store-setting:'.$setting->key));
        static::deleted(fn (Setting $setting) => Cache::forget('store-setting:'.$setting->key));
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return Cache::remember(
            'store-setting:'.$key,
            now()->addMinutes(5),
            fn () => static::query()->where('key', $key)->value('value') ?? $default,
        );
    }

    public static function integer(string $key, int $default = 0): int
    {
        $value = static::get($key);
        return is_numeric($value) ? (int) $value : $default;
    }
}
