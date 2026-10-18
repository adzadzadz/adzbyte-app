<?php

namespace App\Models;

use App\Models\Concerns\ImmutableCommerceRecord;
use Illuminate\Database\Eloquent\Model;

final class PaymentResolution extends Model
{
    use ImmutableCommerceRecord;

    public const UPDATED_AT = null;

    protected $guarded = ['*'];

    protected $hidden = ['verification', 'request_hash'];

    protected function casts(): array
    {
        return ['payment_event_id' => 'integer', 'payment_attempt_id' => 'integer', 'actor_id' => 'integer', 'verification' => 'encrypted:array'];
    }
}
