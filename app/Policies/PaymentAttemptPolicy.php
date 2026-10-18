<?php

namespace App\Policies;

use App\Enums\PaymentCapability;
use App\Enums\UserRole;
use App\Models\Order;
use App\Models\PaymentAttempt;
use App\Models\User;

final class PaymentAttemptPolicy
{
    public function viewAny(User $actor): bool
    {
        return $this->allows($actor, PaymentCapability::ViewAny);
    }

    public function inspect(User $actor, PaymentAttempt $attempt): bool
    {
        return $this->allows($actor, PaymentCapability::View);
    }

    public function reconcile(User $actor, PaymentAttempt $attempt): bool
    {
        return $this->inspect($actor, $attempt) && $this->allows($actor, PaymentCapability::Reconcile);
    }

    private function allows(User $actor, PaymentCapability $capability): bool
    {
        return $actor->hasVerifiedEmail() && $actor->hasAnyRole([UserRole::Administrator->value, UserRole::SuperAdmin->value])
            && $actor->checkPermissionTo($capability->value);
    }

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
