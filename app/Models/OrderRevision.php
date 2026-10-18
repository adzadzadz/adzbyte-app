<?php

namespace App\Models;

use App\Models\Concerns\ImmutableCommerceRecord;
use App\Services\Orders\OrderTotals;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;

final class OrderRevision extends Model
{
    use ImmutableCommerceRecord;

    public const UPDATED_AT = null;

    protected $guarded = ['*'];

    protected static function booted(): void
    {
        self::creating(function (self $revision): void {
            $policy = CommercePolicyVersion::whereKey($revision->policy_version_id)->lockForUpdate()->firstOrFail();
            if ($revision->policy_snapshot != $policy->content || $revision->currency !== 'PHP'
                || $revision->tax_minor !== OrderTotals::calculate($revision->subtotal_minor, $policy->content['tax_mode'], $policy->content['tax_basis_points'])['tax_minor']
                || $revision->total_minor !== OrderTotals::calculate($revision->subtotal_minor, $policy->content['tax_mode'], $policy->content['tax_basis_points'])['total_minor']) {
                throw ValidationException::withMessages(['order' => 'Accepted totals must match the captured commerce policy.']);
            }
        });
    }

    public function lines(): HasMany
    {
        return $this->hasMany(OrderLine::class);
    }

    protected function casts(): array
    {
        return ['order_id' => 'integer', 'version' => 'integer', 'policy_version_id' => 'integer', 'policy_snapshot' => 'array', 'brief' => 'array', 'billing' => 'array', 'accepted_at' => 'immutable_datetime'];
    }
}
