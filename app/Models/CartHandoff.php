<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

#[Hidden(['token_hash', 'authority_hash'])]
class CartHandoff extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = ['*'];

    protected static function booted(): void
    {
        static::updating(function (CartHandoff $handoff): void {
            if (array_diff(array_keys($handoff->getDirty()), ['consumed_at']) !== [] || $handoff->getRawOriginal('consumed_at') !== null || $handoff->consumed_at === null) {
                throw ValidationException::withMessages(['handoff' => 'A transfer may only be consumed once.']);
            }
        });
        static::deleting(fn () => throw ValidationException::withMessages(['handoff' => 'Use the expired-transfer cleanup operation.']));
    }

    protected function casts(): array
    {
        return ['expires_at' => 'immutable_datetime', 'consumed_at' => 'immutable_datetime'];
    }
}
