<?php

namespace App\Models;

use App\Models\Concerns\ImmutableCommerceRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

final class OrderMessage extends Model
{
    use ImmutableCommerceRecord;

    public const UPDATED_AT = null;

    protected $guarded = ['*'];

    protected static function booted(): void
    {
        self::creating(function (self $message): void {
            $message->public_id ??= (string) Str::ulid();
            Validator::make($message->getAttributes(), [
                'audience' => ['required', Rule::in(['customer', 'internal'])],
                'sender' => ['required', Rule::in(['customer', 'admin'])],
                'body' => ['required', 'string', 'max:10000', 'regex:/\S/u'],
            ])->validate();
            abort_if($message->sender === 'customer' && $message->audience !== 'customer', 422);
            abort_unless(OrderEvent::whereKey($message->event_id)->where('order_id', $message->order_id)->where('actor_id', $message->actor_id)->lockForUpdate()->exists(), 422);
        });
    }

    protected function casts(): array
    {
        return ['order_id' => 'integer', 'event_id' => 'integer', 'actor_id' => 'integer', 'created_at' => 'immutable_datetime'];
    }
}
