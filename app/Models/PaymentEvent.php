<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

final class PaymentEvent extends Model
{
    protected $guarded = ['*'];

    protected $hidden = ['evidence', 'payload_hash'];

    protected static function booted(): void
    {
        self::saving(function (self $event): void {
            if ((! $event->exists && $event->state !== 'received')
                || ($event->exists && ! in_array($event->getRawOriginal('state'), ['received', 'needs_review'], true) && $event->isDirty())
                || ($event->exists && $event->isDirty(['provider_event_id', 'type', 'livemode', 'provider_created_at', 'payload_hash', 'evidence', 'created_at']))
                || ($event->exists && $event->getRawOriginal('payment_attempt_id') !== null && $event->isDirty('payment_attempt_id'))
                || ($event->exists && $event->isDirty('state') && (! in_array($event->getRawOriginal('state'), ['received', 'needs_review'], true) || ! in_array($event->state, ['applied', 'needs_review', 'rejected', 'ignored'], true)))) {
                throw ValidationException::withMessages(['payment' => 'Provider event identity and processed history are protected.']);
            }
        });
        self::deleting(fn () => throw ValidationException::withMessages(['payment' => 'Retain provider event history.']));
    }

    protected function casts(): array
    {
        return ['payment_attempt_id' => 'integer', 'provider_created_at' => 'integer', 'livemode' => 'boolean', 'evidence' => 'encrypted:array', 'processed_at' => 'immutable_datetime'];
    }
}
