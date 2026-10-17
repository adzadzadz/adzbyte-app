<?php

declare(strict_types=1);

namespace App\Actions\Catalog;

use App\Enums\ProductStatus;
use App\Models\Product;
use App\Models\ProductChange;
use App\Models\ProductPrice;
use App\Models\ProductTerms;
use App\Models\User;
use Closure;
use Illuminate\Database\ConcurrencyErrorDetector;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Throwable;

final class CatalogActions
{
    public function create(User $actor, array $attributes, string $operationId): Product
    {
        return $this->mutate($actor, null, 'create', $attributes, $operationId, function () use ($attributes): Product {
            $this->validateContentKeys($attributes);

            return Product::create($attributes);
        });
    }

    public function update(User $actor, Product $product, array $attributes, string $operationId): Product
    {
        return $this->mutate($actor, $product, 'update', $attributes, $operationId, function (Product $locked) use ($attributes): Product {
            $this->validateContentKeys($attributes);
            $locked->fill($attributes)->save();

            return $locked;
        });
    }

    public function publish(User $actor, Product $product, string $operationId): Product
    {
        return $this->transition($actor, $product, 'publish', ProductStatus::Published, $operationId);
    }

    public function unpublish(User $actor, Product $product, string $operationId): Product
    {
        return $this->transition($actor, $product, 'unpublish', ProductStatus::Draft, $operationId);
    }

    public function archive(User $actor, Product $product, string $operationId): Product
    {
        return $this->transition($actor, $product, 'archive', ProductStatus::Archived, $operationId);
    }

    public function restore(User $actor, Product $product, string $operationId): Product
    {
        return $this->transition($actor, $product, 'restore', ProductStatus::Draft, $operationId);
    }

    public function setAvailability(User $actor, Product $product, bool $available, string $operationId): Product
    {
        return $this->mutate($actor, $product, 'setAvailability', ['is_available' => $available], $operationId, function (Product $locked) use ($available): Product {
            $locked->is_available = $available;
            $locked->save();

            return $locked;
        });
    }

    public function revisePrice(User $actor, Product $product, array $attributes, string $operationId): ProductPrice
    {
        return $this->mutate($actor, $product, 'revisePrice', $attributes, $operationId, function (Product $locked) use ($attributes): ProductPrice {
            Validator::make(['attributes' => $attributes], ['attributes' => ['array:amount_minor,currency']])->validate();
            $price = $locked->prices()->create([
                ...$attributes,
                'version' => ((int) $locked->prices()->max('version')) + 1,
            ]);
            $this->disableIncompleteOffer($locked);

            return $price;
        });
    }

    public function reviseTerms(User $actor, Product $product, array $content, string $operationId): ProductTerms
    {
        return $this->mutate($actor, $product, 'reviseTerms', $content, $operationId, function (Product $locked) use ($content): ProductTerms {
            $terms = $locked->termsVersions()->create([
                'content' => $content,
                'version' => ((int) $locked->termsVersions()->max('version')) + 1,
            ]);
            $this->disableIncompleteOffer($locked);

            return $terms;
        });
    }

    private function transition(User $actor, Product $product, string $action, ProductStatus $status, string $operationId): Product
    {
        return $this->mutate($actor, $product, $action, [], $operationId, function (Product $locked) use ($action, $status): Product {
            if (($locked->status === ProductStatus::Archived && in_array($action, ['publish', 'unpublish'], true))
                || ($action === 'restore' && $locked->status !== ProductStatus::Archived)) {
                throw ValidationException::withMessages(['status' => 'Restore only archived products; publish and unpublish require a non-archived product.']);
            }

            $locked->status = $status;
            if ($status !== ProductStatus::Published) {
                $locked->is_available = false;
            }
            $locked->save();

            return $locked;
        });
    }

    private function disableIncompleteOffer(Product $product): void
    {
        if ($product->is_available && ! $product->hasConfiguredOffer()) {
            $product->is_available = false;
            $product->save();
        }
    }

    private function validateContentKeys(array $attributes): void
    {
        Validator::make(['attributes' => $attributes], [
            'attributes' => ['array:name,slug,summary,description,media'],
        ])->validate();
    }

    private function mutate(User $actor, ?Product $product, string $action, array $payload, string $operationId, Closure $write): Model
    {
        Gate::forUser($actor)->authorize($action, $product ?? Product::class);
        Validator::make(['operation_id' => $operationId], ['operation_id' => ['required', 'uuid']])->validate();
        $operationId = strtolower($operationId);
        $hash = hash('sha256', json_encode($this->canonical([
            'action' => $action, 'product_id' => $product?->getKey(), 'payload' => $payload,
        ]), JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION));

        try {
            return $this->transaction(function () use ($actor, $product, $action, $operationId, $hash, $write): Model {
                $locked = $product === null ? null : Product::query()->lockForUpdate()->findOrFail($product->getKey());
                Gate::forUser($actor)->authorize($action, $locked ?? Product::class);
                // A locking read sees the latest receipt even on MySQL repeatable-read.
                $receipt = $this->receipt($actor, $operationId, true);
                if ($receipt !== null) {
                    return $this->replay($receipt, $hash);
                }

                $before = $locked?->attributesToArray();
                $result = $write($locked);
                $changed = $locked ?? $result;
                (new ProductChange)->forceFill([
                    'product_id' => $changed->getKey(),
                    'actor_id' => $actor->getKey(),
                    'operation_id' => $operationId,
                    'request_hash' => $hash,
                    'action' => $action,
                    'result_id' => $result->getKey(),
                    'before' => $before,
                    'after' => $changed->attributesToArray(),
                    'result' => $result->attributesToArray(),
                ])->save();

                return $result;
            });
        } catch (UniqueConstraintViolationException $exception) {
            // A concurrent create has no parent row to lock. Its losing draft
            // was rolled back with the duplicate receipt before reaching here.
            $receipt = $this->receipt($actor, $operationId);
            if ($receipt === null) {
                throw $exception;
            }
            Gate::forUser($actor)->authorize($action, $product ?? Product::class);

            return $this->replay($receipt, $hash);
        }
    }

    private function transaction(Closure $write): Model
    {
        for ($attempt = 0; ; $attempt++) {
            try {
                return DB::transaction($write);
            } catch (Throwable $exception) {
                // Retry only after rollback. Brief backoff lets SQLite's other
                // writer finish rather than exhausting immediate lock retries.
                // Nested transactions must leave recovery to their outer owner.
                if ($attempt >= 4 || DB::transactionLevel() > 0
                    || ! app(ConcurrencyErrorDetector::class)->causedByConcurrencyError($exception)) {
                    throw $exception;
                }
                usleep(10000 * (2 ** $attempt));
            }
        }
    }

    private function receipt(User $actor, string $operationId, bool $lock = false): ?ProductChange
    {
        return ProductChange::query()->where('actor_id', $actor->getKey())
            ->where('operation_id', $operationId)->when($lock, fn ($query) => $query->lockForUpdate())->first();
    }

    private function replay(ProductChange $receipt, string $hash): Model
    {
        if (! hash_equals($receipt->request_hash, $hash)) {
            throw ValidationException::withMessages(['operation_id' => 'This operation ID was already used for a different catalog command.']);
        }

        $model = match ($receipt->action) {
            'revisePrice' => ProductPrice::class,
            'reviseTerms' => ProductTerms::class,
            default => Product::class,
        };

        return $model::query()->findOrFail($receipt->result_id);
    }

    private function canonical(array $value): array
    {
        if (! array_is_list($value)) {
            ksort($value);
        }

        return array_map(fn (mixed $item): mixed => is_array($item) ? $this->canonical($item) : $item, $value);
    }
}
