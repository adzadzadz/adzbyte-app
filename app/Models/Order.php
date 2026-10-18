<?php

namespace App\Models;

use App\Services\Orders\OrderLifecycle;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class Order extends Model
{
    protected $guarded = ['*'];

    protected $attributes = [...OrderLifecycle::INITIAL, 'revision' => 1];

    protected static function booted(): void
    {
        self::saving(function (self $order): void {
            $order->public_id ??= (string) Str::ulid();
            if ($order->exists && $order->isDirty(['id', 'public_id', 'user_id', 'cart_id', 'cart_revision', 'creation_operation_id', 'creation_request_hash', 'created_at'])) {
                throw ValidationException::withMessages(['order' => 'Order identity and origin are immutable.']);
            }
            if ((! $order->exists && $order->revision !== 1)
                || ($order->exists && $order->revision !== (int) $order->getRawOriginal('revision') && $order->revision !== (int) $order->getRawOriginal('revision') + 1)
                || ($order->exists && $order->isDirty(['status', 'approval_status', 'payment_status', 'fulfillment_status', 'approval_expires_at', 'pending_proposal_id']) && $order->revision !== (int) $order->getRawOriginal('revision') + 1)) {
                throw ValidationException::withMessages(['revision' => 'Order transitions require the next monotonic revision.']);
            }
            $state = $order->only(array_keys(OrderLifecycle::INITIAL));
            OrderLifecycle::validate($state, $order->exists ? array_intersect_key($order->getRawOriginal(), OrderLifecycle::INITIAL) : null);
            if (($order->approval_status === 'approved') !== ($order->approval_expires_at !== null)) {
                throw ValidationException::withMessages(['approval' => 'Approved orders require a captured approval validity.']);
            }
            if ($order->pending_proposal_id !== null && ($order->approval_status !== 'clarification'
                || ! OrderProposal::whereKey($order->pending_proposal_id)->where('order_id', $order->id)->lockForUpdate()->exists())) {
                throw ValidationException::withMessages(['proposal' => 'A pending proposal must belong to this request and require clarification.']);
            }
        });
        self::deleting(fn () => throw ValidationException::withMessages(['order' => 'Retain commerce history.']));
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(OrderRevision::class);
    }

    public function currentRevision(): HasOne
    {
        return $this->hasOne(OrderRevision::class)->ofMany('version', 'max');
    }

    public function events(): HasMany
    {
        return $this->hasMany(OrderEvent::class);
    }

    protected function casts(): array
    {
        return ['user_id' => 'integer', 'cart_id' => 'integer', 'cart_revision' => 'integer', 'revision' => 'integer', 'pending_proposal_id' => 'integer', 'approval_expires_at' => 'immutable_datetime'];
    }
}
