<?php

declare(strict_types=1);

namespace App\Actions\Carts;

use App\Enums\ProductStatus;
use App\Enums\UserRole;
use App\Models\Cart;
use App\Models\CartChange;
use App\Models\CartItem;
use App\Models\CustomerCart;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductPrice;
use App\Models\ProductTerms;
use App\Models\User;
use App\Services\Carts\CartPrincipal;
use App\Services\Carts\CartSnapshot;
use App\Support\Database\RetryingTransaction;
use App\Support\Records\RecordVisibility;
use Closure;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class CartActions
{
    public function create(CartPrincipal $principal, string $operationId): array
    {
        $principal->assertValid();
        $operationId = $this->operation($operationId);
        $attributes = ['user_id' => $principal->actor()?->id, 'credential_hash' => $principal->credentialHash()];
        $hash = $this->hash(['create', $attributes]);
        try {
            return RetryingTransaction::run(function () use ($principal, $operationId, $attributes, $hash): array {
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
        return RetryingTransaction::run(fn (): array => $this->snapshot($this->resolve($principal, $reference, 'view')));
    }

    public function resolveCreation(CartPrincipal $principal, string $operationId): array
    {
        $operationId = $this->operation($operationId);

        return RetryingTransaction::run(function () use ($principal, $operationId): array {
            $cart = $principal->scope(Cart::query())->where('creation_operation_id', $operationId)->lockForUpdate()->firstOrFail();
            Gate::forUser($principal->actor())->authorize('view', [$cart, $principal]);

            return $this->snapshot($cart);
        });
    }

    public function add(CartPrincipal $principal, string $reference, string $productReference, string $operationId): array
    {
        Validator::make(['product' => $productReference], ['product' => ['required', 'ulid']])->validate();

        return $this->mutate($principal, $reference, 'add', ['product' => $productReference], $operationId, function (Cart $cart) use ($productReference): array {
            $productId = Product::where('public_id', $productReference)->value('id');
            abort_if($productId === null, 404);
            $items = $this->lockedItems($cart);
            $products = $this->lockedProducts([...$items->pluck('product_id')->all(), $productId]);
            $product = $products->get($productId);
            if (! $this->available($product)) {
                throw ValidationException::withMessages(['product' => 'This service is no longer available.']);
            }
            $replaced = $product->category?->limit_one_per_cart
                ? $items->filter(fn (CartItem $item): bool => $item->product_id !== $product->id && $products->get($item->product_id)->category_id === $product->category_id)
                : collect();
            $effects = ['replaced_products' => $replaced->map(fn (CartItem $item): array => [
                'public_id' => $products->get($item->product_id)->public_id,
                'name' => $products->get($item->product_id)->status === ProductStatus::Published ? $products->get($item->product_id)->name : 'Unavailable service',
            ])->values()->all()];
            $cart->items()->whereIn('id', $replaced->pluck('id'))->delete();
            if (! $items->contains('product_id', $product->id)) {
                (new CartItem)->forceFill([
                    'cart_id' => $cart->id, 'product_id' => $product->id,
                    'quoted_price_id' => $product->currentPrice->id, 'quoted_terms_id' => $product->currentTerms->id,
                ])->save();
            } // Existing selections never silently accept new offer versions.

            return $effects;
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

        return RetryingTransaction::run(function () use ($principal, $reference, $action, $operationId, $hash, $command): array {
            $cart = $this->resolve($principal, $reference, 'update');
            $receipt = $cart->changes()->where('operation_id', $operationId)->lockForUpdate()->first();
            if ($receipt !== null) {
                $this->assertSameRequest($receipt->request_hash, $hash);

                return [...$this->snapshot($cart), 'effects' => $receipt->effects];
            }
            $before = $this->history($cart);
            $effects = $command($cart) ?? ['replaced_products' => []];
            $cart->revision++;
            $cart->save();
            $this->receipt($cart, $principal, $operationId, $hash, $action, $before, $effects);

            return [...$this->snapshot($cart), 'effects' => $effects];
        });
    }

    private function resolve(CartPrincipal $principal, string $reference, string $ability): Cart
    {
        $cart = $principal->scope(Cart::query())->where('public_id', $reference)->lockForUpdate()->firstOrFail();
        abort_if($cart->merged_into_cart_id !== null, 404);
        Gate::forUser($principal->actor())->authorize($ability, [$cart, $principal]);

        return $cart;
    }

    public function owned(User $actor): ?array
    {
        return RetryingTransaction::run(function () use ($actor): ?array {
            $actor = $this->verifiedCustomer($actor);
            $link = CustomerCart::where('user_id', $actor->id)->lockForUpdate()->first();

            if ($link === null) {
                return null;
            }
            $principal = CartPrincipal::customer($actor);
            $cart = $principal->scope(Cart::query())->whereKey($link->cart_id)->whereNull('merged_into_cart_id')->lockForUpdate()->firstOrFail();
            Gate::forUser($actor)->authorize('view', [$cart, $principal]);

            return $this->snapshot($cart);
        });
    }

    public function mergePreview(User $actor, CartPrincipal $guest, string $sourceReference): array
    {
        return RetryingTransaction::run(function () use ($actor, $guest, $sourceReference): array {
            $actor = $this->verifiedCustomer($actor);
            abort_unless($guest->actor() === null && $guest->credentialHash() !== null, 403);
            $source = $guest->scope(Cart::query())->where('public_id', $sourceReference)->firstOrFail();
            $link = CustomerCart::where('user_id', $actor->id)->lockForUpdate()->first();
            $carts = $this->mergeLocks($source->id, $link?->cart_id, $actor, $guest);
            $source = $carts->get($source->id);
            abort_if($source === null, 404);
            abort_unless($guest->allows($source), 404);
            Gate::forUser($actor)->authorize('attach', [$source, $guest]);
            $target = $link === null ? null : $carts->get($link->cart_id);
            abort_unless($link === null || $target !== null, 404);
            abort_unless($target === null || CartPrincipal::customer($actor)->allows($target), 404);

            return $this->mergeState($source, $target);
        });
    }

    /** Claim retries require the original trusted guest context and same verified actor. */
    public function attach(User $actor, CartPrincipal $guest, string $sourceReference, string $operationId, string $fingerprint, array $choices = []): array
    {
        $operationId = $this->operation($operationId);
        Validator::make(['fingerprint' => $fingerprint], ['fingerprint' => ['required', 'regex:/^[a-f0-9]{64}$/D']])->validate();
        foreach ($choices as $group => $product) {
            Validator::make(['group' => $group, 'product' => $product], ['group' => ['required', 'ulid'], 'product' => ['required', 'ulid']])->validate();
        }
        ksort($choices);
        abort_unless($guest->actor() === null && $guest->credentialHash() !== null, 403);
        $authority = hash('sha256', $guest->credentialHash());
        $hash = $this->hash(['attach', $actor->id, $sourceReference, $guest->credentialHash(), $fingerprint, $choices]);

        return RetryingTransaction::run(function () use ($actor, $guest, $sourceReference, $operationId, $fingerprint, $choices, $authority, $hash): array {
            $actor = $this->verifiedCustomer($actor);
            $source = Cart::where('public_id', $sourceReference)->where(function ($query) use ($guest, $actor, $operationId, $authority): void {
                $query->where(fn ($query) => $guest->scope($query)->whereNull('merged_into_cart_id'))
                    ->orWhereHas('changes', fn ($query) => $query->where('actor_id', $actor->id)->where('operation_id', $operationId)->whereIn('action', ['attach', 'merge'])->whereNull('effects->source')->where('effects->authority_fingerprint', $authority));
            })->firstOrFail();
            $link = CustomerCart::where('user_id', $actor->id)->lockForUpdate()->first();
            $carts = $this->mergeLocks($source->id, $link?->cart_id, $actor, $guest, $operationId, $authority);
            $source = $carts->get($source->id);
            abort_if($source === null, 404);
            $target = $link === null ? null : $carts->get($link->cart_id);
            abort_unless($link === null || $target !== null, 404);
            $receipt = $source->changes()->where('operation_id', $operationId)->lockForUpdate()->first();
            if ($receipt !== null) {
                abort_unless($receipt->actor_id === $actor->id && ($receipt->effects['authority_fingerprint'] ?? null) === $authority, 404);
                $this->assertSameRequest($receipt->request_hash, $hash);
                abort_unless($target !== null && $target->public_id === ($receipt->effects['target'] ?? null) && CartPrincipal::customer($actor)->allows($target), 404);

                return $this->snapshot($target);
            }
            abort_unless($guest->allows($source), 404);
            Gate::forUser($actor)->authorize('attach', [$source, $guest]);
            abort_unless($target === null || CartPrincipal::customer($actor)->allows($target), 404);
            if ($target !== null && $target->changes()->where('operation_id', $operationId)->exists()) {
                throw ValidationException::withMessages(['operation_id' => 'This operation ID was already used for another cart command.']);
            }
            $state = $this->mergeState($source, $target);
            abort_unless(hash_equals($state['fingerprint'], $fingerprint), 409, 'Review the changed carts again.');
            $expected = array_column($state['groups'], 'key');
            sort($expected);
            $provided = array_keys($choices);
            sort($provided);
            if ($expected !== $provided) {
                throw ValidationException::withMessages(['choices' => 'Choose one service from every conflicting category.']);
            }
            $kept = $state['union'];
            foreach ($state['groups'] as $group) {
                $selected = $choices[$group['key']];
                if (! $group['products']->contains('public_id', $selected)) {
                    throw ValidationException::withMessages(['choices' => 'Choose a service from the displayed category.']);
                }
                foreach ($group['products'] as $product) {
                    if ($product->public_id !== $selected) {
                        $kept->forget($product->id);
                    }
                }
            }
            $before = ['source' => $this->history($source), 'target' => $target === null ? null : $this->history($target)];
            $action = $target === null ? 'attach' : 'merge';
            if ($target === null) {
                // The only authority transition is this locked, audited action.
                DB::table('carts')->where('id', $source->id)->update(['user_id' => $actor->id, 'credential_hash' => null, 'revision' => $source->revision + 1, 'updated_at' => now()]);
                $target = $source->refresh();
                (new CustomerCart)->forceFill(['user_id' => $actor->id, 'cart_id' => $target->id])->save();
            }
            $target->items()->whereNotIn('product_id', $kept->keys())->delete();
            foreach ($kept as $item) {
                if (! $target->items()->where('product_id', $item->product_id)->exists()) {
                    (new CartItem)->forceFill(['cart_id' => $target->id, 'product_id' => $item->product_id, 'quoted_price_id' => $item->quoted_price_id, 'quoted_terms_id' => $item->quoted_terms_id])->save();
                }
            }
            if ($source->id !== $target->id) {
                $source->items()->delete();
                DB::table('carts')->where('id', $source->id)->update(['merged_into_cart_id' => $target->id, 'credential_hash' => bin2hex(random_bytes(32)), 'revision' => $source->revision + 1, 'updated_at' => now()]);
                $source->refresh();
                $target->revision++;
                $target->save();
            }
            $effects = ['replaced_products' => [], 'target' => $target->public_id, 'authority_fingerprint' => $authority];
            (new CartChange)->forceFill(['cart_id' => $source->id, 'actor_id' => $actor->id, 'operation_id' => $operationId, 'request_hash' => $hash, 'action' => $action, 'before' => $before, 'after' => ['source' => $this->history($source), 'target' => $this->history($target)], 'effects' => $effects])->save();
            if ($source->id !== $target->id) {
                (new CartChange)->forceFill(['cart_id' => $target->id, 'actor_id' => $actor->id, 'operation_id' => $operationId, 'request_hash' => $hash, 'action' => 'merge', 'before' => $before['target'], 'after' => $this->history($target), 'effects' => ['replaced_products' => [], 'target' => $target->public_id, 'source' => $source->public_id]])->save();
            }

            return $this->snapshot($target);
        });
    }

    /** Static acknowledgement only; never exposes an owner/target/future state. */
    public function retired(CartPrincipal $guest, string $creationOperation): bool
    {
        abort_unless($guest->actor() === null && $guest->credentialHash() !== null, 403);
        $creationOperation = $this->operation($creationOperation);
        $exists = Cart::where('creation_operation_id', $creationOperation)
            ->where(fn ($query) => $query->whereNotNull('user_id')->orWhereNotNull('merged_into_cart_id'))
            ->whereHas('changes', fn ($query) => $query->whereIn('action', ['attach', 'merge'])->whereNotNull('actor_id')->whereNull('effects->source')->where('effects->authority_fingerprint', hash('sha256', $guest->credentialHash())))->exists();
        abort_unless($exists, 404);

        return true;
    }

    private function verifiedCustomer(User $actor): User
    {
        $fresh = User::whereKey($actor->id)->lockForUpdate()->firstOrFail();
        abort_unless($fresh->hasVerifiedEmail() && $fresh->hasRole(UserRole::Customer->value), 403);

        return $fresh;
    }

    private function mergeLocks(int $source, ?int $target, User $actor, CartPrincipal $guest, ?string $operation = null, ?string $authority = null): Collection
    {
        $owner = CartPrincipal::customer($actor);

        return Cart::whereIn('id', array_filter([$source, $target]))->where(function ($query) use ($guest, $owner, $actor, $operation, $authority): void {
            $query->where(fn ($query) => $guest->scope($query)->whereNull('merged_into_cart_id'))
                ->orWhere(fn ($query) => $owner->scope($query)->whereNull('merged_into_cart_id'));
            if ($operation !== null && $authority !== null) {
                $query->orWhereHas('changes', fn ($query) => $query->where('actor_id', $actor->id)->where('operation_id', $operation)->whereIn('action', ['attach', 'merge'])->whereNull('effects->source')->where('effects->authority_fingerprint', $authority));
            }
        })->orderBy('id')->lockForUpdate()->get()->keyBy('id');
    }

    private function mergeState(Cart $source, ?Cart $target): array
    {
        $sourceItems = $this->lockedItems($source);
        $targetItems = $target === null ? collect() : $this->lockedItems($target);
        $products = $this->lockedProducts([...$sourceItems->pluck('product_id')->all(), ...$targetItems->pluck('product_id')->all()]);
        $union = $targetItems->keyBy('product_id');
        foreach ($sourceItems as $item) {
            if (! $union->has($item->product_id)) {
                $union->put($item->product_id, $item);
            }
        }
        $groups = [];
        foreach ($products->groupBy('category_id') as $categoryProducts) {
            if ($categoryProducts->count() > 1 && $categoryProducts->first()->category?->limit_one_per_cart) {
                $group = $categoryProducts->sortBy('public_id')->values();
                $groups[] = ['key' => $group->first()->public_id, 'products' => $group];
            }
        }
        $fingerprint = hash_hmac('sha256', json_encode([
            $source->public_id, $this->history($source), $target?->public_id, $target === null ? null : $this->history($target),
            $products->map(fn (Product $product): array => [$product->getAttributes(), $product->category?->getAttributes(), $product->currentPrice?->id, $product->currentTerms?->id])->values()->all(),
        ], JSON_THROW_ON_ERROR), (string) config('app.key'));

        return ['source' => $this->snapshot($source), 'target' => $target === null ? null : $this->snapshot($target), 'groups' => $groups, 'union' => $union, 'fingerprint' => $fingerprint];
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
        return ! RecordVisibility::isDeleted($product) && $product->status === ProductStatus::Published && $product->is_available
            && $product->currentPrice !== null && $product->currentPrice->amount_minor > 0 && $product->currentPrice->currency === 'PHP'
            && $product->currentTerms !== null && $product->currentTerms->content !== [];
    }

    private function history(Cart $cart): array
    {
        return ['revision' => $cart->revision, 'lines' => $this->lockedItems($cart)->map(fn (CartItem $item): array => $item->only(['product_id', 'quoted_price_id', 'quoted_terms_id']))->all()];
    }

    private function receipt(Cart $cart, CartPrincipal $principal, string $operationId, string $hash, string $action, ?array $before, array $effects = ['replaced_products' => []]): void
    {
        (new CartChange)->forceFill([
            'cart_id' => $cart->id, 'actor_id' => $principal->actor()?->id, 'operation_id' => $operationId,
            'request_hash' => $hash, 'action' => $action, 'before' => $before, 'after' => $this->history($cart),
            'effects' => $effects,
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
}
