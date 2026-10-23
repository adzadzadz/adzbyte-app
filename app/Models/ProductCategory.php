<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

#[Fillable(['name', 'limit_one_per_cart'])]
class ProductCategory extends Model
{
    protected $attributes = ['limit_one_per_cart' => false];

    protected static function booted(): void
    {
        static::saving(function (ProductCategory $category): void {
            if (! $category->exists && ! $category->public_id) {
                $category->public_id = (string) Str::ulid();
            }
            if ($category->exists && $category->isDirty(['id', 'public_id'])) {
                throw ValidationException::withMessages(['public_id' => 'Category identity cannot be changed.']);
            }
            Validator::make($category->getAttributes(), [
                'public_id' => ['required', 'ulid'], 'name' => ['required', 'string', 'max:255', 'regex:/\S/u'],
                'limit_one_per_cart' => ['required', 'boolean'],
            ])->validate();
        });
        static::deleting(fn () => throw ValidationException::withMessages(['category' => 'Retain categories and their history instead of deleting them.']));
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'category_id');
    }

    public function changes(): HasMany
    {
        return $this->hasMany(ProductCategoryChange::class);
    }

    protected function casts(): array
    {
        return ['limit_one_per_cart' => 'boolean'];
    }
}
