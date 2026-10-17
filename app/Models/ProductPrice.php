<?php

namespace App\Models;

use App\Models\Concerns\HasImmutableCatalogVersion;
use Database\Factories\ProductPriceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

#[Fillable(['version', 'amount_minor', 'currency'])]
class ProductPrice extends Model
{
    /** @use HasFactory<ProductPriceFactory> */
    use HasFactory, HasImmutableCatalogVersion;

    protected static function booted(): void
    {
        static::creating(function (ProductPrice $price): void {
            // Validate stored input before the integer casts can truncate it.
            Validator::make($price->getAttributes(), [
                'product_id' => ['required', 'integer:strict', 'min:1'],
                'version' => ['required', 'integer:strict', 'min:1', 'max:4294967295'],
                'amount_minor' => ['required', 'integer:strict', 'min:0', 'max:'.PHP_INT_MAX],
                'currency' => ['required', Rule::in(['PHP'])],
            ])->validate();
        });
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    protected function casts(): array
    {
        return ['version' => 'integer', 'amount_minor' => 'integer'];
    }
}
