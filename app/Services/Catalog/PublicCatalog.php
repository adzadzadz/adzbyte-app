<?php

namespace App\Services\Catalog;

use App\Models\Product;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

final class PublicCatalog
{
    public function paginate(int $perPage, int $page, ?bool $available): LengthAwarePaginator
    {
        $query = $this->query();
        if ($available !== null) {
            $query->where(function (Builder $query) use ($available): void {
                if ($available) {
                    $this->available($query);
                } else {
                    $query->whereNot(fn (Builder $query) => $this->available($query));
                }
            });
        }

        return $query->orderBy('id')->paginate($perPage, ['*'], 'page', $page);
    }

    public function find(string $reference): Product
    {
        return $this->query()->where('public_id', $reference)->firstOrFail();
    }

    private function query(): Builder
    {
        return Product::query()->published()->with(['currentPrice', 'currentTerms']);
    }

    private function available(Builder $query): void
    {
        $query->where('is_available', true)
            ->whereHas('currentPrice', fn (Builder $price) => $price->where('currency', 'PHP')->where('amount_minor', '>', 0))
            // Model writes encode empty documents as []; JSON array length is
            // not portable for nonempty objects on SQLite and MySQL.
            ->whereHas('currentTerms', fn (Builder $terms) => $terms->where('content', '!=', '[]')->where('content', '!=', '{}'));
    }
}
