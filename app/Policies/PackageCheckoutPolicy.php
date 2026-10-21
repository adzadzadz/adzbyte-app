<?php

namespace App\Policies;

use App\Models\PackageCheckout;
use App\Models\User;

class PackageCheckoutPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['administrator', 'super_admin']);
    }

    public function view(User $user, PackageCheckout $checkout): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, PackageCheckout $checkout): bool
    {
        return false;
    }

    public function delete(User $user, PackageCheckout $checkout): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
