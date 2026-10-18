<?php

namespace App\Policies;

use App\Enums\OrderCapability;
use App\Enums\UserRole;
use App\Models\Order;
use App\Models\User;

final class OrderPolicy
{
    public function view(User $actor, Order $order): bool
    {
        return ($actor->hasVerifiedEmail() && $actor->hasRole(UserRole::Customer->value) && $actor->id === $order->user_id)
            || $this->allows($actor, OrderCapability::View);
    }

    public function viewAny(User $actor): bool
    {
        return $this->allows($actor, OrderCapability::ViewAny);
    }

    public function approve(User $actor, Order $order): bool
    {
        return $this->allows($actor, OrderCapability::Approve);
    }

    public function reject(User $actor, Order $order): bool
    {
        return $this->allows($actor, OrderCapability::Reject);
    }

    public function clarify(User $actor, Order $order): bool
    {
        return $this->allows($actor, OrderCapability::Clarify);
    }

    public function message(User $actor, Order $order): bool
    {
        return $this->allows($actor, OrderCapability::Message);
    }

    public function note(User $actor, Order $order): bool
    {
        return $this->allows($actor, OrderCapability::Note);
    }

    public function propose(User $actor, Order $order): bool
    {
        return $this->allows($actor, OrderCapability::Propose);
    }

    private function allows(User $actor, OrderCapability $capability): bool
    {
        return $actor->hasVerifiedEmail() && $actor->hasAnyRole([UserRole::Administrator->value, UserRole::SuperAdmin->value]) && $actor->hasPermissionTo($capability->value);
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
