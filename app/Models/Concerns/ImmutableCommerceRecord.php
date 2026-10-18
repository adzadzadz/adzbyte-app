<?php

namespace App\Models\Concerns;

use Illuminate\Validation\ValidationException;

trait ImmutableCommerceRecord
{
    public static function bootImmutableCommerceRecord(): void
    {
        static::updating(fn () => throw ValidationException::withMessages(['history' => 'Accepted commerce records are immutable.']));
        static::deleting(fn () => throw ValidationException::withMessages(['history' => 'Accepted commerce records must be retained.']));
    }
}
