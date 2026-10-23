<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class WorkItem extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['target_date' => 'date', 'customer_read_at' => 'datetime', 'revision' => 'integer'];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function milestones(): HasMany
    {
        return $this->hasMany(WorkMilestone::class)->orderBy('id');
    }

    public function updates(): HasMany
    {
        return $this->hasMany(WorkUpdate::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(PortalEvent::class);
    }
}
