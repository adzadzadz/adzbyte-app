<?php

declare(strict_types=1);

namespace App\Actions\Carts;

use App\Models\Cart;
use App\Models\CartChange;
use App\Models\CartHandoff;
use App\Models\User;
use App\Services\Carts\CartGrant;
use App\Services\Carts\CartPrincipal;
use App\Services\Carts\CommerceOrigins;
use App\Services\Carts\StorefrontCredential;
use App\Support\Database\RetryingTransaction;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;

final class CartHandoffActions
{
    public function mint(CartPrincipal $principal, string $reference, PersonalAccessToken $issuer, string $operationId): array
    {
        Validator::make(['operation_id' => $operationId], ['operation_id' => ['required', 'uuid']])->validate();
        $operationId = strtolower($operationId);
        abort_unless($principal->actor() === null, 403);

        return RetryingTransaction::run(function () use ($principal, $reference, $issuer, $operationId): array {
            $cart = $principal->scope(Cart::query())->where('public_id', $reference)->lockForUpdate()->firstOrFail();
            Gate::forUser(null)->authorize('view', [$cart, $principal]);
            $token = $this->issuer($issuer->id, 403);
            $hash = hash('sha256', json_encode(['handoff', $token->id], JSON_THROW_ON_ERROR));
            $receipt = $cart->changes()->where('operation_id', $operationId)->lockForUpdate()->first();
            if ($receipt !== null) {
                if (! hash_equals($receipt->request_hash, $hash)) {
                    throw ValidationException::withMessages(['operation_id' => 'This operation ID belongs to a different cart command.']);
                }
                $handoff = CartHandoff::where('public_id', $receipt->effects['handoff']['public_id'])->lockForUpdate()->first();
                abort_unless($handoff !== null && $handoff->consumed_at === null && $handoff->expires_at->isFuture()
                    && $handoff->cart_id === $cart->id && hash_equals($handoff->authority_hash, $cart->credential_hash)
                    && hash_equals($handoff->token_hash, hash('sha256', $this->token($handoff))), 409);

                return $this->response($handoff);
            }
            $handoff = (new CartHandoff)->forceFill([
                'public_id' => (string) Str::ulid(), 'cart_id' => $cart->id, 'issuer_token_id' => $token->id,
                'operation_id' => $operationId, 'authority_hash' => $cart->credential_hash,
                'expires_at' => now()->startOfSecond()->addSeconds(60),
            ]);
            $handoff->token_hash = hash('sha256', $this->token($handoff));
            $handoff->save();
            $metadata = ['public_id' => $handoff->public_id, 'expires_at' => $handoff->expires_at->utc()->toIso8601String(), 'issuer_token_id' => $token->id];
            (new CartChange)->forceFill([
                'cart_id' => $cart->id, 'actor_id' => null, 'operation_id' => $operationId, 'request_hash' => $hash,
                'action' => 'handoff', 'before' => null, 'after' => ['revision' => $cart->revision, 'handoff' => $metadata],
                'effects' => ['replaced_products' => [], 'handoff' => $metadata],
            ])->save();

            return $this->response($handoff);
        });
    }

    public function consume(#[\SensitiveParameter] string $secret): CartGrant
    {
        abort_unless(preg_match('/^[a-f0-9]{64}$/D', $secret), 410);

        return RetryingTransaction::run(function () use ($secret): CartGrant {
            $handoff = CartHandoff::where('token_hash', hash('sha256', $secret))->lockForUpdate()->first();
            abort_unless($handoff !== null && $handoff->consumed_at === null && $handoff->expires_at->isFuture()
                && hash_equals($secret, $this->token($handoff)), 410);
            $cart = Cart::whereKey($handoff->cart_id)->lockForUpdate()->firstOrFail();
            abort_unless($cart->user_id === null && is_string($cart->credential_hash)
                && hash_equals($cart->credential_hash, $handoff->authority_hash), 410);
            $this->issuer($handoff->issuer_token_id, 410);
            abort_unless($handoff->expires_at->isFuture(), 410);
            $handoff->consumed_at = now();
            $handoff->save();

            return new CartGrant($cart->public_id, $cart->credential_hash);
        });
    }

    private function issuer(?int $id, int $status): PersonalAccessToken
    {
        $token = $id === null ? null : PersonalAccessToken::whereKey($id)->lockForUpdate()->first();
        $user = $token === null ? null : User::whereKey($token->tokenable_id)->lockForUpdate()->first();
        abort_unless($user !== null && StorefrontCredential::allowed($user, $token) && $token->can('cart:handoff'), $status);

        return $token;
    }

    private function response(CartHandoff $handoff): array
    {
        return [
            'url' => CommerceOrigins::app().'/cart/handoff#'.$this->token($handoff),
            'expires_at' => $handoff->expires_at->utc()->toIso8601String(),
        ];
    }

    private function token(CartHandoff $handoff): string
    {
        $key = config('app.key');
        if (is_string($key) && str_starts_with($key, 'base64:')) {
            $key = base64_decode(substr($key, 7), true);
        }
        if (! is_string($key) || strlen($key) < 32) {
            throw new \RuntimeException('A strong application key is required for cart transfers.');
        }

        return hash_hmac('sha256', implode('|', ['cart-handoff:v1', $handoff->public_id, $handoff->cart_id, $handoff->issuer_token_id, $handoff->operation_id]), $key);
    }
}
