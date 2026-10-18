<?php

namespace App\Services\Carts;

use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Str;

final class CartSession
{
    private const KEY = 'commerce.cart';

    public function open(Session $session, CartGrant $grant): void
    {
        $session->regenerate(true);
        $session->put(self::KEY, ['version' => 1, 'public_id' => $grant->reference, 'authority_hash' => $grant->authorityHash()]);
    }

    public function principal(Session $session): CartPrincipal
    {
        return CartPrincipal::fromTrustedSessionHash($this->context($session)['authority_hash']);
    }

    public function reference(Session $session): string
    {
        return $this->context($session)['public_id'];
    }

    private function context(Session $session): array
    {
        $context = $session->get(self::KEY);
        abort_unless(is_array($context) && ($context['version'] ?? null) === 1
            && is_string($context['public_id'] ?? null) && Str::isUlid($context['public_id'])
            && is_string($context['authority_hash'] ?? null) && preg_match('/^[a-f0-9]{64}$/D', $context['authority_hash']), 404);

        return $context;
    }
}
