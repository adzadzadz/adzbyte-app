<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

class ProductChange extends Model
{
    public const UPDATED_AT = null;

    // Receipts are written only by the shared catalog actions.
    protected $guarded = ['*'];

    protected static function booted(): void
    {
        $reject = static function (): never {
            throw ValidationException::withMessages(['audit' => 'Catalog history cannot be changed or deleted.']);
        };
        static::updating($reject);
        static::deleting($reject);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    protected function casts(): array
    {
        return ['before' => 'array', 'after' => 'array', 'result' => 'array'];
    }
}
