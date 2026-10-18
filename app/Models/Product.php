<?php

namespace App\Models;

use App\Enums\ProductStatus;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

#[Fillable(['name', 'slug', 'summary', 'description', 'media', 'category_id'])]
class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory;

    protected $attributes = [
        'status' => 'draft',
        'is_available' => false,
    ];

    protected static function booted(): void
    {
        static::saving(function (Product $product): void {
            if (! $product->exists && ! $product->public_id) {
                $product->public_id = (string) Str::ulid();
            }

            if ($product->exists && $product->isDirty(['id', 'public_id'])) {
                throw ValidationException::withMessages(['public_id' => 'Product identity cannot be changed.']);
            }

            Validator::make([
                ...$product->getAttributes(),
                'media' => $product->media,
            ], [
                'public_id' => ['required', 'ulid'],
                'name' => ['nullable', 'string', 'max:255'],
                'slug' => ['nullable', 'string', 'max:255', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
                'summary' => ['nullable', 'string'],
                'description' => ['nullable', 'string'],
                'media' => ['nullable', 'array'],
                'category_id' => ['nullable', 'integer:strict', 'min:1', 'exists:product_categories,id'],
                'status' => ['required', Rule::enum(ProductStatus::class)],
                'is_available' => ['required', 'boolean'],
            ])->validate();

            $status = $product->status;
            $original = $product->exists
                ? ProductStatus::from($product->getRawOriginal('status'))
                : ProductStatus::Draft;

            if ($original === ProductStatus::Archived && $status === ProductStatus::Published) {
                throw ValidationException::withMessages(['status' => 'Restore an archived product to draft before publishing.']);
            }

            if ($status === ProductStatus::Published && trim((string) $product->name) === '') {
                throw ValidationException::withMessages(['name' => 'A published product requires a name.']);
            }

            if ($product->is_available && ! $product->hasConfiguredOffer()) {
                throw ValidationException::withMessages([
                    'is_available' => 'Availability requires a published product, positive PHP price, and nonempty terms.',
                ]);
            }
        });

        static::deleting(function (): never {
            throw ValidationException::withMessages(['status' => 'Archive products instead of deleting them.']);
        });
    }

    public function prices(): HasMany
    {
        return $this->hasMany(ProductPrice::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'category_id');
    }

    public function termsVersions(): HasMany
    {
        return $this->hasMany(ProductTerms::class);
    }

    public function changes(): HasMany
    {
        return $this->hasMany(ProductChange::class);
    }

    public function currentPrice(): HasOne
    {
        return $this->prices()->one()->ofMany('version', 'max');
    }

    public function currentTerms(): HasOne
    {
        return $this->termsVersions()->one()->ofMany('version', 'max');
    }

    /** Catalog configuration only; never payment or customer authorization. */
    public function hasConfiguredOffer(): bool
    {
        if (! $this->exists || $this->status !== ProductStatus::Published || ! $this->is_available) {
            return false;
        }

        // Query current versions rather than trusting previously loaded relations.
        $price = $this->currentPrice()->first();
        $terms = $this->currentTerms()->first();

        return $price !== null && $price->currency === 'PHP' && $price->amount_minor > 0
            && $terms !== null && $terms->content !== [];
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', ProductStatus::Published->value);
    }

    protected function casts(): array
    {
        return [
            'status' => ProductStatus::class,
            'is_available' => 'boolean',
            'media' => 'array',
        ];
    }
}
