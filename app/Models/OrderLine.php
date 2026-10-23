<?php

namespace App\Models;

use App\Models\Concerns\ImmutableCommerceRecord;
use Illuminate\Database\Eloquent\Model;

final class OrderLine extends Model
{
    use ImmutableCommerceRecord;

    public const UPDATED_AT = null;

    protected $guarded = ['*'];

    protected function casts(): array
    {
        return ['order_revision_id' => 'integer', 'product_id' => 'integer', 'price_id' => 'integer', 'terms_id' => 'integer', 'snapshot' => 'array'];
    }
}
