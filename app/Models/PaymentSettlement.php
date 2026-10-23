<?php

namespace App\Models;

use App\Models\Concerns\ImmutableCommerceRecord;
use Illuminate\Database\Eloquent\Model;

final class PaymentSettlement extends Model
{
    use ImmutableCommerceRecord;

    public const UPDATED_AT = null;

    protected $guarded = ['*'];

    protected function casts(): array
    {
        return ['order_id' => 'integer', 'payment_attempt_id' => 'integer', 'payment_event_id' => 'integer', 'livemode' => 'boolean', 'paid_at' => 'immutable_datetime'];
    }
}
