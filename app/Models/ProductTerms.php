<?php

namespace App\Models;

use App\Models\Concerns\HasImmutableCatalogVersion;
use Database\Factories\ProductTermsFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Validator;

#[Fillable(['version', 'content'])]
class ProductTerms extends Model
{
    /** @use HasFactory<ProductTermsFactory> */
    use HasFactory, HasImmutableCatalogVersion;

    protected static function booted(): void
    {
        static::creating(function (ProductTerms $terms): void {
            Validator::make([
                ...$terms->getAttributes(),
                'content' => $terms->content,
            ], [
                'product_id' => ['required', 'integer:strict', 'min:1'],
                'version' => ['required', 'integer:strict', 'min:1', 'max:4294967295'],
                'content' => ['present', 'array'],
            ])->validate();
        });
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    protected function casts(): array
    {
        return ['version' => 'integer', 'content' => 'array'];
    }
}
