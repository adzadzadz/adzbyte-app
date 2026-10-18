<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Order;
use App\Models\User;

final class OrderPolicy
{
    public function view(User $actor, Order $order): bool
    {
        return $actor->hasVerifiedEmail() && $actor->hasRole(UserRole::Customer->value) && $actor->id === $order->user_id;
    }

    public function create(User $actor): bool
    {
        return $actor->hasVerifiedEmail() && $actor->hasRole(UserRole::Customer->value);
    }

    public function delete(User $actor, Order $order): bool
    {
        return false;
    }
}
