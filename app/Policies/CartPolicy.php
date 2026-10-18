<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Cart;
use App\Models\User;
use App\Services\Carts\CartPrincipal;

class CartPolicy
{
    public function view(?User $actor, Cart $cart, CartPrincipal $principal): bool
    {
        return $actor?->id === $principal->actor()?->id && $principal->allows($cart);
    }

    public function update(?User $actor, Cart $cart, CartPrincipal $principal): bool
    {
        return $this->view($actor, $cart, $principal);
    }

    public function attach(User $actor, Cart $cart, CartPrincipal $guest): bool
    {
        return $actor->hasVerifiedEmail() && $actor->hasRole(UserRole::Customer->value)
            && $guest->actor() === null && $guest->allows($cart);
    }
}
