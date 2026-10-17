<?php

namespace App\Models\Concerns;

use Illuminate\Validation\ValidationException;

trait HasImmutableCatalogVersion
{
    protected static function bootHasImmutableCatalogVersion(): void
    {
        $reject = static function (): never {
            throw ValidationException::withMessages([
                'version' => 'Catalog versions cannot be changed or deleted. Create a new version instead.',
            ]);
        };

        static::updating($reject);
        static::deleting($reject);
    }
}
