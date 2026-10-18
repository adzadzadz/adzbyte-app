<?php

declare(strict_types=1);

namespace App\Actions\Catalog;

use App\Models\ProductCategory;
use App\Models\ProductCategoryChange;
use App\Models\User;
use Illuminate\Database\ConcurrencyErrorDetector;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Throwable;

final class CategoryActions
{
    public function create(User $actor, array $attributes, string $operationId): ProductCategory
    {
        return $this->mutate($actor, null, $attributes, $operationId);
    }

    public function update(User $actor, ProductCategory $category, array $attributes, string $operationId): ProductCategory
    {
        return $this->mutate($actor, $category, $attributes, $operationId);
    }

    private function mutate(User $actor, ?ProductCategory $category, array $attributes, string $operationId): ProductCategory
    {
        $action = $category === null ? 'create' : 'update';
        Gate::forUser($actor)->authorize($action, $category ?? ProductCategory::class);
        Validator::make(['attributes' => $attributes, 'operation_id' => $operationId], [
            'attributes' => ['array:name,limit_one_per_cart'], 'operation_id' => ['required', 'uuid'],
        ])->validate();
        ksort($attributes);
        $hash = hash('sha256', json_encode([$action, $category?->getKey(), $attributes], JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION));
        $operationId = strtolower($operationId);
        for ($attempt = 0; ; $attempt++) {
            try {
                return DB::transaction(function () use ($actor, $category, $attributes, $action, $hash, $operationId): ProductCategory {
                    $locked = $category === null ? null : ProductCategory::query()->lockForUpdate()->findOrFail($category->getKey());
                    Gate::forUser($actor)->authorize($action, $locked ?? ProductCategory::class);
                    $receipt = ProductCategoryChange::query()->where('actor_id', $actor->id)->where('operation_id', $operationId)->lockForUpdate()->first();
                    if ($receipt !== null) {
                        return $this->replay($receipt, $hash);
                    }
                    $before = $locked?->attributesToArray();
                    $record = $locked ?? new ProductCategory;
                    $record->fill($attributes)->save();
                    (new ProductCategoryChange)->forceFill([
                        'product_category_id' => $record->id, 'actor_id' => $actor->id, 'operation_id' => $operationId,
                        'request_hash' => $hash, 'action' => $action, 'before' => $before, 'after' => $record->attributesToArray(),
                    ])->save();

                    return $record;
                });
            } catch (UniqueConstraintViolationException $exception) {
                $receipt = ProductCategoryChange::where('actor_id', $actor->id)->where('operation_id', $operationId)->first();
                if ($receipt === null) {
                    throw $exception;
                }
                Gate::forUser($actor)->authorize($action, $category ?? ProductCategory::class);

                return $this->replay($receipt, $hash);
            } catch (Throwable $exception) {
                if ($attempt >= 4 || DB::transactionLevel() > 0 || ! app(ConcurrencyErrorDetector::class)->causedByConcurrencyError($exception)) {
                    throw $exception;
                }
                usleep(10000 * (2 ** $attempt));
            }
        }
    }

    private function replay(ProductCategoryChange $receipt, string $hash): ProductCategory
    {
        if (! hash_equals($receipt->request_hash, $hash)) {
            throw ValidationException::withMessages(['operation_id' => 'This operation ID was already used for another category command.']);
        }

        return ProductCategory::findOrFail($receipt->product_category_id);
    }
}
