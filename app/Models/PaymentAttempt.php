<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class PaymentAttempt extends Model
{
    protected $guarded = ['*'];

    protected $hidden = ['provider_payload', 'checkout_url'];

    protected static function booted(): void
    {
        self::saving(function (self $attempt): void {
            $attempt->public_id ??= (string) Str::ulid();
            $transitions = ['creating' => ['active', 'unknown', 'rejected'], 'unknown' => ['active', 'expired'], 'active' => ['expired'], 'expired' => [], 'rejected' => []];
            if ((! $attempt->exists && $attempt->state !== 'creating')
                || ! isset($transitions[$attempt->state])
                || ($attempt->exists && $attempt->isDirty('state') && ! in_array($attempt->state, $transitions[$attempt->getRawOriginal('state')] ?? [], true))
                || ($attempt->exists && $attempt->isDirty(['public_id', 'order_id', 'order_revision_id', 'actor_id', 'amount_minor', 'currency', 'livemode', 'provider_payload', 'created_at']))
                || ($attempt->exists && $attempt->getRawOriginal('provider_session_id') !== null && $attempt->isDirty('provider_session_id'))) {
                throw ValidationException::withMessages(['payment' => 'Payment identity, captured request and history are protected.']);
            }
        });
        self::deleting(fn () => throw ValidationException::withMessages(['payment' => 'Retain payment history.']));
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    protected function casts(): array
    {
        return ['order_id' => 'integer', 'order_revision_id' => 'integer', 'actor_id' => 'integer', 'livemode' => 'boolean',
            'provider_payload' => 'encrypted:array', 'checkout_url' => 'encrypted'];
    }
}
