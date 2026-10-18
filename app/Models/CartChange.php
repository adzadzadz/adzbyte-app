<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class CartChange extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = ['*'];

    protected static function booted(): void
    {
        static::updating(fn () => throw ValidationException::withMessages(['history' => 'Cart history is immutable.']));
        static::deleting(fn () => throw ValidationException::withMessages(['history' => 'Cart history is immutable.']));
    }

    protected function casts(): array
    {
        return ['cart_id' => 'integer', 'actor_id' => 'integer', 'before' => 'array', 'after' => 'array', 'effects' => 'array'];
    }
}
