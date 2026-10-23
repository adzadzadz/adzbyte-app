<?php

namespace App\Services\Catalog;

use App\Models\Product;
use App\Support\Records\RecordVisibility;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

final class PublicCatalog
{
    public function paginate(int $perPage, int $page, ?bool $available, ?string $category = null): LengthAwarePaginator
    {
        $query = $this->query();
        if ($category !== null) {
            $query->whereHas('category', fn (Builder $query) => $query->where('public_id', $category));
        }
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
        return RecordVisibility::scope(Product::query())->published()->with(['currentPrice', 'currentTerms', 'category']);
    }

    private function available(Builder $query): void
    {
        $query->where('is_available', true)
            ->whereHas('currentPrice', fn (Builder $price) => $price->where('currency', 'PHP')->where('amount_minor', '>', 0))
            ->whereHas('currentTerms', function (Builder $terms): void {
                // SQLite's array-length function returns zero for objects;
                // MySQL compares JSON values differently from SQL strings.
                // Count entries natively on each supported database engine.
                if ($terms->getConnection()->getDriverName() === 'sqlite') {
                    $terms->whereRaw('exists (select 1 from json_each(product_terms.content))');
                } else {
                    $terms->whereJsonLength('content', '>', 0);
                }
            });
    }
}
