<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PolicyPage extends Model
{
    protected $fillable = ['slug', 'title', 'body', 'is_published'];

    protected function casts(): array
    {
        return ['is_published' => 'boolean'];
    }
}
