<?php

namespace App\Models;

use App\Models\Concerns\ImmutablePortalHistory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class WorkUpdate extends Model
{
    use ImmutablePortalHistory;

    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
