<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\User;

final class UserPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->can('ViewAny:User');
    }

    public function view(User $actor, User $user): bool
    {
        return $actor->can('View:User');
    }

    public function create(User $actor): bool
    {
        return false;
    }

    public function update(User $actor, User $user): bool
    {
        if ($user->hasRole(UserRole::SuperAdmin->value)
            && ! $actor->hasRole(UserRole::SuperAdmin->value)) {
            return false;
        }

        return $actor->can('Update:User');
    }

    public function assignRoles(User $actor, User $user): bool
    {
        return $actor->hasRole(UserRole::SuperAdmin->value);
    }

    public function delete(User $actor, User $user): bool
    {
        return false;
    }

    public function deleteAny(User $actor): bool
    {
        return false;
    }

    public function forceDelete(User $actor, User $user): bool
    {
        return false;
    }

    public function forceDeleteAny(User $actor): bool
    {
        return false;
    }

    public function restore(User $actor, User $user): bool
    {
        return false;
    }

    public function restoreAny(User $actor): bool
    {
        return false;
    }

    public function replicate(User $actor, User $user): bool
    {
        return false;
    }
}
