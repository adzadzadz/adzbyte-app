<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

#[Hidden(['credential_hash', 'creation_request_hash', 'creation_operation_id'])]
class Cart extends Model
{
    protected $guarded = ['*'];

    protected $attributes = ['revision' => 0];

    protected static function booted(): void
    {
        static::saving(function (Cart $cart): void {
            if (! $cart->exists && ! $cart->public_id) {
                $cart->public_id = (string) Str::ulid();
            }
            if ($cart->exists && $cart->isDirty(['id', 'public_id', 'user_id', 'credential_hash', 'creation_operation_id', 'creation_request_hash', 'merged_into_cart_id'])) {
                throw ValidationException::withMessages(['cart' => 'Cart identity and authority cannot be edited.']);
            }
            Validator::make($cart->getAttributes(), [
                'public_id' => ['required', 'ulid'], 'user_id' => ['nullable', 'integer:strict', 'exists:users,id'],
                'credential_hash' => ['nullable', 'regex:/^[a-f0-9]{64}$/D'],
                'creation_operation_id' => ['required', 'uuid'], 'creation_request_hash' => ['required', 'size:64'],
                'revision' => ['required', 'integer:strict', 'min:0'],
            ])->validate();
            if (($cart->user_id === null) === ($cart->credential_hash === null)) {
                throw ValidationException::withMessages(['cart' => 'A cart requires exactly one authority.']);
            }
        });
        static::deleting(fn () => throw ValidationException::withMessages(['cart' => 'Cart history must be retained.']));
    }

    public function items(): HasMany
    {
        return $this->hasMany(CartItem::class);
    }

    public function changes(): HasMany
    {
        return $this->hasMany(CartChange::class);
    }

    protected function casts(): array
    {
        return ['user_id' => 'integer', 'revision' => 'integer', 'merged_into_cart_id' => 'integer'];
    }
}
