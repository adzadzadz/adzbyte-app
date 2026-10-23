<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CartItem extends Model
{
    protected $guarded = ['*'];

    protected static function booted(): void
    {
        static::saving(function (CartItem $item): void {
            if ($item->exists && $item->isDirty(['id', 'cart_id', 'product_id'])) {
                throw ValidationException::withMessages(['item' => 'Cart line identity cannot change.']);
            }
            Validator::make($item->getAttributes(), [
                'cart_id' => ['required', 'integer:strict', 'exists:carts,id'],
                'product_id' => ['required', 'integer:strict', 'exists:products,id'],
                'quoted_price_id' => ['required', 'integer:strict', Rule::exists('product_prices', 'id')->where('product_id', $item->product_id)],
                'quoted_terms_id' => ['required', 'integer:strict', Rule::exists('product_terms', 'id')->where('product_id', $item->product_id)],
            ])->validate();
        });
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function quotedPrice(): BelongsTo
    {
        return $this->belongsTo(ProductPrice::class, 'quoted_price_id');
    }

    public function quotedTerms(): BelongsTo
    {
        return $this->belongsTo(ProductTerms::class, 'quoted_terms_id');
    }
}
