<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class SupportCase extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['customer_read_at' => 'datetime', 'staff_read_at' => 'datetime', 'last_message_at' => 'datetime', 'public_updated_at' => 'datetime', 'revision' => 'integer'];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function workItem(): BelongsTo
    {
        return $this->belongsTo(WorkItem::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(SupportCategory::class, 'support_category_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(SupportMessage::class);
    }

    public function notes(): HasMany
    {
        return $this->hasMany(SupportNote::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(PortalEvent::class);
    }
}
