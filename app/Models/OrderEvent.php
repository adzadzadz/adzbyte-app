<?php

namespace App\Models;

use App\Models\Concerns\ImmutableCommerceRecord;
use App\Services\Orders\OrderNotifications;
use Illuminate\Database\Eloquent\Model;

final class OrderEvent extends Model
{
    use ImmutableCommerceRecord;

    public const UPDATED_AT = null;

    protected $guarded = ['*'];

    protected static function booted(): void
    {
        self::created(fn (self $event) => OrderNotifications::record($event));
    }

    protected function casts(): array
    {
        return ['order_id' => 'integer', 'actor_id' => 'integer', 'before' => 'array', 'after' => 'array'];
    }
}
