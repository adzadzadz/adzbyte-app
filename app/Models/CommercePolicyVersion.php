<?php

namespace App\Models;

use App\Models\Concerns\ImmutableCommerceRecord;
use App\Services\Orders\CanonicalValue;
use App\Services\Orders\CommerceRules;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

final class CommercePolicyVersion extends Model
{
    use ImmutableCommerceRecord;

    public const UPDATED_AT = null;

    protected $guarded = ['*'];

    protected static function booted(): void
    {
        self::creating(function (self $version): void {
            $version->public_id ??= (string) Str::ulid();
            $content = CommerceRules::validate($version->content);
            $version->content_hash = CanonicalValue::hash($content);
            Validator::make($version->getAttributes(), ['version' => ['required', 'integer:strict', 'min:1'], 'operation_id' => ['required', 'uuid']])->validate();
        });
    }

    protected function casts(): array
    {
        return ['version' => 'integer', 'actor_id' => 'integer', 'content' => 'array'];
    }
}
