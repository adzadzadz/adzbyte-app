<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Order;
use App\Models\PaymentAttempt;
use App\Models\User;

final class PaymentAttemptPolicy
{
    public function create(User $actor, Order $order): bool
    {
        return $actor->hasVerifiedEmail() && $actor->hasRole(UserRole::Customer->value) && $actor->id === $order->user_id;
    }

    public function view(User $actor, PaymentAttempt $attempt): bool
    {
        return $actor->hasVerifiedEmail() && $actor->hasRole(UserRole::Customer->value)
            && Order::whereKey($attempt->order_id)->where('user_id', $actor->id)->exists();
    }
}
