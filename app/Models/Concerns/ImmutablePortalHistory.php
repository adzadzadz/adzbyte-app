<?php

namespace App\Models\Concerns;

use Illuminate\Validation\ValidationException;

trait ImmutablePortalHistory
{
    public static function bootImmutablePortalHistory(): void
    {
        static::updating(fn () => throw ValidationException::withMessages(['history' => 'Portal history cannot be edited.']));
        static::deleting(fn () => throw ValidationException::withMessages(['history' => 'Portal history must be retained.']));
    }
}
