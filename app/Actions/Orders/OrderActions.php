<?php

namespace App\Actions\Orders;

use App\Actions\Carts\CartActions;
use App\Enums\UserRole;
use App\Models\Cart;
use App\Models\CommercePolicyVersion;
use App\Models\CustomerCart;
use App\Models\Order;
use App\Models\OrderEvent;
use App\Models\OrderLine;
use App\Models\OrderRevision;
use App\Models\User;
use App\Services\Carts\CartPrincipal;
use App\Services\Orders\CanonicalValue;
use App\Services\Orders\CommerceRules;
use App\Services\Orders\OrderAggregate;
use App\Services\Orders\OrderLifecycle;
use App\Services\Orders\OrderTotals;
use App\Services\Orders\OrderTransaction;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

final class OrderActions
{
    /** Internal review; adapters must explicitly serialize safe fields. */
    public function review(User $actor): array
    {
        return OrderTransaction::run(function () use ($actor): array {
            $customer = $this->customer($actor);
            $policy = app(CommercePolicyActions::class)->current();
            $cart = $this->cart($customer);

            return $this->reviewLocked($customer, $cart, $policy);
        });
    }

    public function submit(User $actor, array $input, string $operationId): Order
    {
        Validator::make(['operation_id' => $operationId], ['operation_id' => ['required', 'uuid']])->validate();
        $operationId = strtolower($operationId);

        return OrderTransaction::run(function () use ($actor, $input, $operationId): Order {
            $customer = $this->customer($actor);
            Gate::forUser($customer)->authorize('create', Order::class);
            // Retry checks precede live catalog validation: immutable accepted
            // input stays recoverable after later catalog/settings changes.
            $hash = CanonicalValue::hash(['actor' => $customer->id, 'input' => $input]);
            $receipt = OrderEvent::where('actor_id', $customer->id)->where('operation_id', $operationId)->first();
            if ($receipt !== null) {
                abort_unless(hash_equals($receipt->request_hash, $hash), 409);

                return $this->hydrate(Order::where('user_id', $customer->id)->whereKey($receipt->order_id)->lockForUpdate()->firstOrFail());
            }
            abort_if(Order::where('creation_operation_id', $operationId)->exists(), 409);
            $policy = app(CommercePolicyActions::class)->current();
            CommerceRules::submission($input, $policy->content);
            $cart = $this->cart($customer);
            $existing = Order::where('user_id', $customer->id)->where('cart_id', $cart->id)->where('cart_revision', $cart->revision)->lockForUpdate()->first();
            if ($existing !== null) {
                abort_unless(hash_equals($existing->creation_request_hash, $hash), 409);
                (new OrderEvent)->forceFill(['order_id' => $existing->id, 'actor_id' => $customer->id, 'operation_id' => $operationId,
                    'request_hash' => $hash, 'action' => 'submission_replayed', 'before' => null, 'after' => ['revision' => $existing->revision],
                ])->save();

                return $this->hydrate($existing);
            }
            $review = $this->reviewLocked($customer, $cart, $policy);
            abort_unless(hash_equals($review['fingerprint'], $input['fingerprint']) && $review['cart']['selection_valid'], 409);
            $order = (new Order)->forceFill(['user_id' => $customer->id, 'cart_id' => $cart->id, 'cart_revision' => $cart->revision,
                'creation_operation_id' => $operationId, 'creation_request_hash' => $hash]);
            $order->save();
            $revision = (new OrderRevision)->forceFill([
                'order_id' => $order->id, 'version' => 1, 'policy_version_id' => $policy->id, 'policy_snapshot' => $policy->content,
                'brief' => $input['brief'], 'billing' => $input['billing'], ...$review['totals'], 'accepted_at' => now(),
            ]);
            $revision->save();
            foreach ($review['cart']['lines'] as $line) {
                $product = $line['product'];
                (new OrderLine)->forceFill(['order_revision_id' => $revision->id, 'product_id' => $product->id,
                    'price_id' => $product->currentPrice->id, 'terms_id' => $product->currentTerms->id,
                    'snapshot' => ['public_id' => $product->public_id, 'name' => $product->name, 'quantity' => 1,
                        'amount_minor' => (string) $product->currentPrice->amount_minor, 'currency' => 'PHP',
                        'price_version' => $product->currentPrice->version, 'terms_version' => $product->currentTerms->version,
                        'terms' => $product->currentTerms->content],
                ])->save();
            }
            (new OrderEvent)->forceFill(['order_id' => $order->id, 'actor_id' => $customer->id, 'operation_id' => $operationId,
                'request_hash' => $hash, 'action' => 'submitted', 'before' => null, 'after' => [...OrderLifecycle::INITIAL, 'revision' => 1, 'accepted_version' => 1],
            ])->save();

            return $this->hydrate($order);
        });
    }

    public function read(User $actor, string $reference): Order
    {
        return OrderTransaction::run(function () use ($actor, $reference): Order {
            $customer = $this->customer($actor);
            $order = Order::where('user_id', $customer->id)->where('public_id', $reference)->lockForUpdate()->firstOrFail();
            Gate::forUser($customer)->authorize('view', $order);

            return $this->hydrate($order);
        });
    }

    public function listing(User $actor, int $perPage = 20, int $page = 1): LengthAwarePaginator
    {
        abort_unless($perPage >= 1 && $perPage <= 50 && $page >= 1 && $page <= 10000, 422);

        return OrderTransaction::run(function () use ($actor, $perPage, $page) {
            $customer = $this->customer($actor);

            return Order::where('user_id', $customer->id)->with('currentRevision')->orderByDesc('id')->paginate($perPage, ['*'], 'page', $page);
        });
    }

    private function customer(User $actor): User
    {
        $fresh = User::whereKey($actor->id)->lockForUpdate()->firstOrFail();
        abort_unless($fresh->hasVerifiedEmail() && $fresh->hasRole(UserRole::Customer->value), 403);

        return $fresh;
    }

    private function cart(User $customer): Cart
    {
        $link = CustomerCart::where('user_id', $customer->id)->firstOrFail();

        return Cart::whereKey($link->cart_id)->where('user_id', $customer->id)->whereNull('credential_hash')->whereNull('merged_into_cart_id')->lockForUpdate()->firstOrFail();
    }

    private function reviewLocked(User $customer, Cart $cart, CommercePolicyVersion $policy): array
    {
        $snapshot = app(CartActions::class)->read(CartPrincipal::customer($customer), $cart->public_id);
        $totals = OrderTotals::calculate($snapshot['subtotal_minor'], $policy->content['tax_mode'], $policy->content['tax_basis_points']);
        $identity = ['cart' => $cart->public_id, 'revision' => $cart->revision, 'policy' => $policy->public_id, 'hash' => $policy->content_hash,
            'lines' => array_map(fn ($line): array => ['product' => $line['product']->getAttributes(), 'price' => $line['product']->currentPrice?->id,
                'terms' => $line['product']->currentTerms?->id, 'category' => $line['product']->category?->getAttributes()], $snapshot['lines'])];

        return ['cart' => $snapshot, 'policy' => $policy, 'totals' => $totals, 'fingerprint' => CanonicalValue::hash($identity),
            'existing_order' => Order::where('user_id', $customer->id)->where('cart_id', $cart->id)->where('cart_revision', $cart->revision)->lockForUpdate()->value('public_id')];
    }

    private function hydrate(Order $order): Order
    {
        return OrderAggregate::hydrate($order);
    }
}
