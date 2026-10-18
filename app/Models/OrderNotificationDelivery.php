<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class OrderNotificationDelivery extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = ['*'];

    protected function casts(): array
    {
        return ['event_id' => 'integer', 'user_id' => 'integer', 'queued_at' => 'immutable_datetime', 'sent_at' => 'immutable_datetime', 'failed_at' => 'immutable_datetime', 'last_failed_at' => 'immutable_datetime', 'failure_count' => 'integer', 'suppressed_at' => 'immutable_datetime'];
    }
}
