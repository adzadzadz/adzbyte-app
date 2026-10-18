<?php

declare(strict_types=1);

namespace App\Actions\Carts;

use App\Enums\ProductStatus;
use App\Models\Cart;
use App\Models\CartChange;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductPrice;
use App\Models\ProductTerms;
use App\Services\Carts\CartPrincipal;
use App\Services\Carts\CartSnapshot;
use Closure;
use Illuminate\Database\ConcurrencyErrorDetector;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Throwable;

final class CartActions
{
    public function create(CartPrincipal $principal, string $operationId): array
    {
        $principal->assertValid();
        $operationId = $this->operation($operationId);
        $attributes = ['user_id' => $principal->actor()?->id, 'credential_hash' => $principal->credentialHash()];
        $hash = $this->hash(['create', $attributes]);
        try {
            return $this->transaction(function () use ($principal, $operationId, $attributes, $hash): array {
                $principal->assertValid();
                $cart = Cart::where('creation_operation_id', $operationId)->lockForUpdate()->first();
                if ($cart !== null) {
                    abort_unless($principal->allows($cart), 404);
                    $this->assertSameRequest($cart->creation_request_hash, $hash);

                    return $this->snapshot($cart);
                }
                $cart = (new Cart)->forceFill([...$attributes, 'creation_operation_id' => $operationId, 'creation_request_hash' => $hash]);
                $cart->save();
                $this->receipt($cart, $principal, $operationId, $hash, 'create', null);

                return $this->snapshot($cart);
            });
        } catch (UniqueConstraintViolationException $exception) {
            $cart = $principal->scope(Cart::query())->where('creation_operation_id', $operationId)->first();
            if ($cart === null) {
                throw $exception;
            }
            $this->assertSameRequest($cart->creation_request_hash, $hash);

            return $this->read($principal, $cart->public_id);
        }
    }

    public function read(CartPrincipal $principal, string $reference): array
    {
        return $this->transaction(fn (): array => $this->snapshot($this->resolve($principal, $reference, 'view')));
    }

    public function add(CartPrincipal $principal, string $reference, string $productReference, string $operationId): array
    {
        Validator::make(['product' => $productReference], ['product' => ['required', 'ulid']])->validate();

        return $this->mutate($principal, $reference, 'add', ['product' => $productReference], $operationId, function (Cart $cart) use ($productReference): void {
            $productId = Product::where('public_id', $productReference)->value('id');
            abort_if($productId === null, 404);
            $items = $this->lockedItems($cart);
            $products = $this->lockedProducts([...$items->pluck('product_id')->all(), $productId]);
            $product = $products->get($productId);
            if (! $this->available($product)) {
                throw ValidationException::withMessages(['product' => 'This service is no longer available.']);
            }
            if ($items->contains('product_id', $product->id)) {
                return; // Quantity stays one; never silently accept new offer versions.
            }
            if ($product->category?->limit_one_per_cart && $items->contains(fn (CartItem $item): bool => $products->get($item->product_id)->category_id === $product->category_id)) {
                throw ValidationException::withMessages(['product' => 'Remove the existing service from this category before adding another.']);
            }
            (new CartItem)->forceFill([
                'cart_id' => $cart->id, 'product_id' => $product->id,
                'quoted_price_id' => $product->currentPrice->id, 'quoted_terms_id' => $product->currentTerms->id,
            ])->save();
        });
    }

    public function remove(CartPrincipal $principal, string $reference, string $productReference, string $operationId): array
    {
        Validator::make(['product' => $productReference], ['product' => ['required', 'ulid']])->validate();

        return $this->mutate($principal, $reference, 'remove', ['product' => $productReference], $operationId, function (Cart $cart) use ($productReference): void {
            $cart->items()->whereHas('product', fn ($query) => $query->where('public_id', $productReference))->delete();
        });
    }

    /** Expected versions are what the customer actually reviewed, never amounts. */
    public function confirm(CartPrincipal $principal, string $reference, array $versions, string $operationId): array
    {
        foreach ($versions as $product => $offer) {
            Validator::make(['product' => $product, 'offer' => $offer], [
                'product' => ['required', 'ulid'], 'offer' => ['required', 'array:price,terms'],
                'offer.price' => ['required', 'integer:strict', 'min:1'], 'offer.terms' => ['required', 'integer:strict', 'min:1'],
            ])->validate();
            ksort($versions[$product]);
        }
        ksort($versions);

        return $this->mutate($principal, $reference, 'confirm', $versions, $operationId, function (Cart $cart) use ($versions): void {
            $snapshot = $this->snapshot($cart);
            $references = array_map(fn (array $line): string => $line['product']->public_id, $snapshot['lines']);
            $expected = array_keys($versions);
            sort($references);
            sort($expected);
            if ($references === [] || $references !== $expected || $snapshot['category_conflicts'] !== []) {
                throw ValidationException::withMessages(['versions' => 'Review the current cart and resolve category conflicts first.']);
            }
            foreach ($snapshot['lines'] as $line) {
                $product = $line['product'];
                if (! $line['available'] || $versions[$product->public_id] !== ['price' => $product->currentPrice->version, 'terms' => $product->currentTerms->version]) {
                    throw ValidationException::withMessages(['versions' => 'The offer changed or became unavailable. Review the current cart again.']);
                }
                $item = $cart->items()->where('product_id', $product->id)->firstOrFail();
                $item->forceFill(['quoted_price_id' => $product->currentPrice->id, 'quoted_terms_id' => $product->currentTerms->id])->save();
            }
        });
    }

    private function mutate(CartPrincipal $principal, string $reference, string $action, array $input, string $operationId, Closure $command): array
    {
        $operationId = $this->operation($operationId);
        $hash = $this->hash([$action, $input]);

        return $this->transaction(function () use ($principal, $reference, $action, $operationId, $hash, $command): array {
            $cart = $this->resolve($principal, $reference, 'update');
            $receipt = $cart->changes()->where('operation_id', $operationId)->lockForUpdate()->first();
            if ($receipt !== null) {
                $this->assertSameRequest($receipt->request_hash, $hash);

                return $this->snapshot($cart);
            }
            $before = $this->history($cart);
            $command($cart);
            $cart->revision++;
            $cart->save();
            $this->receipt($cart, $principal, $operationId, $hash, $action, $before);

            return $this->snapshot($cart);
        });
    }

    private function resolve(CartPrincipal $principal, string $reference, string $ability): Cart
    {
        $cart = $principal->scope(Cart::query())->where('public_id', $reference)->lockForUpdate()->firstOrFail();
        Gate::forUser($principal->actor())->authorize($ability, [$cart, $principal]);

        return $cart;
    }

    private function lockedItems(Cart $cart): Collection
    {
        return $cart->items()->orderBy('product_id')->lockForUpdate()->get();
    }

    private function lockedProducts(array $ids): Collection
    {
        $products = Product::whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
        $categories = ProductCategory::whereIn('id', $products->pluck('category_id')->filter()->unique())->orderBy('id')->lockForUpdate()->get()->keyBy('id');
        foreach ($products as $product) {
            // Current locking reads avoid an older MySQL repeatable-read snapshot
            // after waiting for a catalog or cart writer to commit.
            $product->setRelation('category', $categories->get($product->category_id));
            $product->setRelation('currentPrice', ProductPrice::where('product_id', $product->id)->orderByDesc('version')->lockForUpdate()->first());
            $product->setRelation('currentTerms', ProductTerms::where('product_id', $product->id)->orderByDesc('version')->lockForUpdate()->first());
        }

        return $products;
    }

    private function snapshot(Cart $cart): array
    {
        $items = $this->lockedItems($cart);
        $products = $this->lockedProducts($items->pluck('product_id')->all());
        foreach ($items as $item) {
            $item->setRelation('product', $products->get($item->product_id));
            $item->setRelation('quotedPrice', ProductPrice::whereKey($item->quoted_price_id)->lockForUpdate()->firstOrFail());
            $item->setRelation('quotedTerms', ProductTerms::whereKey($item->quoted_terms_id)->lockForUpdate()->firstOrFail());
        }

        return CartSnapshot::build($cart, $items);
    }

    private function available(Product $product): bool
    {
        return $product->status === ProductStatus::Published && $product->is_available
            && $product->currentPrice !== null && $product->currentPrice->amount_minor > 0 && $product->currentPrice->currency === 'PHP'
            && $product->currentTerms !== null && $product->currentTerms->content !== [];
    }

    private function history(Cart $cart): array
    {
        return ['revision' => $cart->revision, 'lines' => $this->lockedItems($cart)->map(fn (CartItem $item): array => $item->only(['product_id', 'quoted_price_id', 'quoted_terms_id']))->all()];
    }

    private function receipt(Cart $cart, CartPrincipal $principal, string $operationId, string $hash, string $action, ?array $before): void
    {
        (new CartChange)->forceFill([
            'cart_id' => $cart->id, 'actor_id' => $principal->actor()?->id, 'operation_id' => $operationId,
            'request_hash' => $hash, 'action' => $action, 'before' => $before, 'after' => $this->history($cart),
        ])->save();
    }

    private function operation(string $operation): string
    {
        Validator::make(['operation_id' => $operation], ['operation_id' => ['required', 'uuid']])->validate();

        return strtolower($operation);
    }

    private function hash(array $input): string
    {
        return hash('sha256', json_encode($input, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION));
    }

    private function assertSameRequest(string $original, string $incoming): void
    {
        if (! hash_equals($original, $incoming)) {
            throw ValidationException::withMessages(['operation_id' => 'This operation ID was already used for another cart command.']);
        }
    }

    private function transaction(Closure $callback): mixed
    {
        for ($attempt = 0; ; $attempt++) {
            try {
                return DB::transaction($callback);
            } catch (Throwable $exception) {
                if ($attempt >= 4 || DB::transactionLevel() > 0 || ! app(ConcurrencyErrorDetector::class)->causedByConcurrencyError($exception)) {
                    throw $exception;
                }
                usleep(10000 * (2 ** $attempt));
            }
        }
    }
}
