<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use LogicException;

class PackageCheckout extends Model
{
    use HasUlids;

    protected $guarded = [];

    // Accidental model serialization must never become a customer/provider API.
    protected $visible = ['id', 'status', 'paid_at', 'created_at', 'updated_at'];

    protected function casts(): array
    {
        return [
            'amount_minor' => 'integer', 'quantity' => 'integer',
            'business_name' => 'encrypted', 'email' => 'encrypted',
            'expires_at' => 'immutable_datetime', 'paid_at' => 'immutable_datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (self $checkout): void {
            foreach (['operation_id', 'request_hash', 'proof_hash', 'package_code', 'package_name', 'amount_minor',
                'currency', 'quantity', 'offer_version', 'business_name', 'email', 'provider_mode'] as $field) {
                if ($checkout->isDirty($field)) {
                    throw new LogicException('The package checkout snapshot is immutable.');
                }
            }
            if ($checkout->getOriginal('status') === 'paid' && $checkout->isDirty(['status', 'paid_at', 'provider_payment_id'])) {
                throw new LogicException('A settled package checkout is immutable.');
            }
        });
        static::deleting(fn () => throw new LogicException('Package checkout records are retained.'));
    }
}
