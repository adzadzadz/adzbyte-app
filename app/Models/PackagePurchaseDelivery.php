<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use LogicException;

class PackagePurchaseDelivery extends Model
{
    use HasUlids;

    protected $guarded = [];

    protected $visible = ['id', 'package_checkout_id', 'phase', 'status', 'attempts', 'last_error_code', 'next_attempt_at', 'delivered_at'];

    protected function casts(): array
    {
        return ['payload' => 'encrypted', 'attempts' => 'integer', 'next_attempt_at' => 'immutable_datetime',
            'claimed_at' => 'immutable_datetime', 'delivered_at' => 'immutable_datetime'];
    }

    protected static function booted(): void
    {
        static::updating(function (self $delivery): void {
            if ($delivery->isDirty(['package_checkout_id', 'phase', 'payload', 'payload_hash'])) {
                throw new LogicException('The purchase delivery snapshot is immutable.');
            }
            if ($delivery->getOriginal('status') === 'delivered' && $delivery->isDirty(['status', 'delivered_at'])) {
                throw new LogicException('A completed purchase delivery is immutable.');
            }
        });
        static::deleting(fn () => throw new LogicException('Purchase delivery records are retained.'));
    }
}
