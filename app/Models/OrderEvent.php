<?php

namespace App\Models;

use App\Models\Concerns\ImmutableCommerceRecord;
use Illuminate\Database\Eloquent\Model;

final class OrderEvent extends Model
{
    use ImmutableCommerceRecord;

    public const UPDATED_AT = null;

    protected $guarded = ['*'];

    protected function casts(): array
    {
        return ['order_id' => 'integer', 'actor_id' => 'integer', 'before' => 'array', 'after' => 'array'];
    }
}
