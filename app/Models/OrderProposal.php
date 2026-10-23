<?php

namespace App\Models;

use App\Models\Concerns\ImmutableCommerceRecord;
use App\Services\Orders\CanonicalValue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

final class OrderProposal extends Model
{
    use ImmutableCommerceRecord;

    public const UPDATED_AT = null;

    protected $guarded = ['*'];

    protected static function booted(): void
    {
        self::creating(function (self $proposal): void {
            $proposal->public_id ??= (string) Str::ulid();
            $proposal->content_hash = CanonicalValue::hash($proposal->content);
        });
    }

    protected function casts(): array
    {
        return ['order_id' => 'integer', 'policy_version_id' => 'integer', 'base_revision' => 'integer', 'content' => 'array', 'created_at' => 'immutable_datetime'];
    }
}
