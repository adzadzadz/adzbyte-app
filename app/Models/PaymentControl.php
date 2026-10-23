<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

final class PaymentControl extends Model
{
    protected $guarded = ['*'];

    protected $hidden = ['proof', 'request_hash'];

    public function target(): BelongsTo
    {
        return $this->belongsTo(self::class, 'target_id');
    }

    protected static function booted(): void
    {
        self::saving(function (self $command): void {
            if ((! $command->exists && $command->state !== 'pending')
                || ! in_array($command->action, ['cancel', 'expire', 'recover', 'verify'], true)
                || ! in_array($command->state, ['pending', 'unknown', 'blocked', 'confirmed'], true)
                || ($command->state === 'confirmed' && $command->payment_attempt_id !== null && ! is_array($command->proof))
                || ($command->exists && $command->getRawOriginal('state') === 'unknown' && $command->state === 'pending')
                || ($command->exists && $command->isDirty(['id', 'public_id', 'order_id', 'payment_attempt_id', 'target_id', 'actor_id', 'operation_id', 'request_hash', 'action', 'provider_session_id', 'reserved_revision', 'note', 'created_at']))
                || ($command->exists && in_array($command->getRawOriginal('state'), ['blocked', 'confirmed'], true) && $command->isDirty())) {
                throw ValidationException::withMessages(['payment' => 'Payment control identity and completed history are protected.']);
            }
        });
        self::deleting(fn () => throw ValidationException::withMessages(['payment' => 'Retain payment control history.']));
    }

    protected function casts(): array
    {
        return ['order_id' => 'integer', 'payment_attempt_id' => 'integer', 'target_id' => 'integer', 'actor_id' => 'integer', 'reserved_revision' => 'integer', 'proof' => 'encrypted:array'];
    }
}
