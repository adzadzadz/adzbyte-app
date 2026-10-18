<?php

namespace App\Services\Carts;

use App\Actions\Carts\CartActions;
use App\Enums\UserRole;
use App\Models\Cart;
use App\Models\CustomerCart;
use App\Models\User;
use Illuminate\Contracts\Session\Session;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

final class CartSession
{
    private const KEY = 'commerce.cart';

    public function open(Session $session, CartGrant $grant): void
    {
        $session->regenerate(true);
        $session->forget(['commerce.cart_claim', 'commerce.cart_merge_pending']);
        $session->put(self::KEY, ['version' => 1, 'public_id' => $grant->reference, 'authority_hash' => $grant->authorityHash()]);
    }

    public function principal(Session $session): CartPrincipal
    {
        $context = $this->context($session);
        if ($context['version'] === 1) {
            return CartPrincipal::fromTrustedSessionHash($context['authority_hash']);
        }
        $user = Auth::guard('web')->user()?->fresh();
        abort_unless($user instanceof User && $user->id === $context['user_id'] && $user->hasVerifiedEmail(), 403);
        $principal = CartPrincipal::customer($user);
        $cart = $principal->scope(Cart::query())->where('public_id', $context['public_id'])->whereNull('merged_into_cart_id')->firstOrFail();
        abort_unless(CustomerCart::where('user_id', $user->id)->where('cart_id', $cart->id)->exists(), 404);

        return $principal;
    }

    public function reference(Session $session): string
    {
        return $this->context($session)['public_id'];
    }

    public function hasContext(Session $session): bool
    {
        return $session->has(self::KEY);
    }

    public function hasGuest(Session $session): bool
    {
        $context = $session->get(self::KEY);

        return is_array($context) && ($context['version'] ?? null) === 1;
    }

    /** Only trusted session state supplies the guest authority/reference. */
    public function claimContext(Session $session, ?string $operation = null): array
    {
        if ($this->hasGuest($session)) {
            $context = $this->context($session);
        } else {
            $claim = $session->get('commerce.cart_claim');
            abort_unless(is_array($claim) && $operation !== null && ($claim['operation'] ?? null) === strtolower($operation)
                && ($claim['user_id'] ?? null) === Auth::guard('web')->id(), 404);
            $context = $claim['guest'];
            abort_unless(is_array($context) && ($context['version'] ?? null) === 1
                && is_string($context['public_id'] ?? null) && Str::isUlid($context['public_id'])
                && is_string($context['authority_hash'] ?? null), 404);
        }

        return ['reference' => $context['public_id'], 'principal' => CartPrincipal::fromTrustedSessionHash($context['authority_hash']), 'context' => $context];
    }

    public function saveOwned(Session $session, User $user, string $reference, ?array $guest = null, ?string $operation = null): void
    {
        $session->regenerate(true);
        $session->put(self::KEY, ['version' => 2, 'user_id' => $user->id, 'public_id' => $reference]);
        $session->forget('commerce.cart_merge_pending');
        if ($guest !== null && $operation !== null) {
            $session->put('commerce.cart_claim', ['guest' => $guest, 'operation' => strtolower($operation), 'user_id' => $user->id]);
        } else {
            $session->forget('commerce.cart_claim');
        }
    }

    public function afterAuthentication(Session $session, User $actor, CartActions $actions): void
    {
        $fresh = $actor->fresh();
        if ($fresh === null || ! $fresh->hasVerifiedEmail() || ! $fresh->hasRole(UserRole::Customer->value)) {
            if (! $this->hasGuest($session)) {
                $session->forget([self::KEY, 'commerce.cart_claim']);
            }

            return;
        }
        try {
            if ($this->hasGuest($session)) {
                $guest = $this->claimContext($session);
                $preview = $actions->mergePreview($fresh, $guest['principal'], $guest['reference']);
                if ($preview['groups'] !== []) {
                    $session->put('commerce.cart_merge_pending', true);

                    return;
                }
                $operation = (string) Str::uuid();
                $owned = $actions->attach($fresh, $guest['principal'], $guest['reference'], $operation, $preview['fingerprint']);
                $this->saveOwned($session, $fresh, $owned['public_id'], $guest['context'], $operation);
            } else {
                $this->restoreOwned($session, $fresh, $actions);
            }
        } catch (Throwable $exception) {
            $missing = $exception instanceof ModelNotFoundException
                || ($exception instanceof HttpException && $exception->getStatusCode() === 404);
            if ($missing && $this->hasGuest($session) && (! isset($guest) || ! $guest['principal']->scope(Cart::query())->where('public_id', $guest['reference'])->whereNull('merged_into_cart_id')->exists())) {
                try {
                    // A different tab may already have claimed this guest cart.
                    // Recover solely by this verified customer's active link.
                    $this->restoreOwned($session, $fresh, $actions);

                    return;
                } catch (Throwable $recovery) {
                    $exception = $recovery;
                }
            }
            // Authentication remains valid; retain guest context for explicit
            // review/retry rather than losing it or guessing a merge choice.
            $session->put('commerce.cart_merge_pending', true);
            Log::warning('Customer cart needs review after authentication.', ['user_id' => $fresh->id, 'exception' => $exception::class]);
        }
    }

    private function restoreOwned(Session $session, User $user, CartActions $actions): void
    {
        $owned = $actions->owned($user);
        if ($owned === null) {
            $session->forget([self::KEY, 'commerce.cart_claim', 'commerce.cart_merge_pending']);
        } else {
            $this->saveOwned($session, $user, $owned['public_id']);
        }
    }

    private function context(Session $session): array
    {
        $context = $session->get(self::KEY);
        abort_unless(is_array($context) && is_string($context['public_id'] ?? null) && Str::isUlid($context['public_id']), 404);
        abort_unless((($context['version'] ?? null) === 1 && is_string($context['authority_hash'] ?? null) && preg_match('/^[a-f0-9]{64}$/D', $context['authority_hash']))
            || (($context['version'] ?? null) === 2 && is_int($context['user_id'] ?? null)), 404);

        return $context;
    }
}
