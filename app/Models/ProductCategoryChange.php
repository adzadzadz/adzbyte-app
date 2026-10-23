<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

class ProductCategoryChange extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = ['*'];

    protected static function booted(): void
    {
        $reject = fn () => throw ValidationException::withMessages(['audit' => 'Category history cannot be changed or deleted.']);
        static::updating($reject);
        static::deleting($reject);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    protected function casts(): array
    {
        return ['before' => 'array', 'after' => 'array'];
    }
}
