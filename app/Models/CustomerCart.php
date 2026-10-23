<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class CustomerCart extends Model
{
    protected $guarded = ['*'];

    protected static function booted(): void
    {
        self::saving(function (CustomerCart $link): void {
            if ($link->exists && $link->isDirty(['id', 'user_id', 'cart_id'])) {
                throw ValidationException::withMessages(['cart' => 'Active cart identity cannot be edited.']);
            }
            Validator::make($link->getAttributes(), [
                'user_id' => ['required', 'integer:strict', 'exists:users,id'],
                'cart_id' => ['required', 'integer:strict', Rule::exists('carts', 'id')->where('user_id', $link->user_id)->whereNull('credential_hash')->whereNull('merged_into_cart_id')],
            ])->validate();
        });
        self::deleting(fn () => throw ValidationException::withMessages(['cart' => 'Active cart history must be retained.']));
    }

    protected function casts(): array
    {
        return ['user_id' => 'integer', 'cart_id' => 'integer'];
    }
}
