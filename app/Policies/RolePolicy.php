<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Role;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class RolePolicy
{
    use HandlesAuthorization;

    public function viewAny(User $authUser): bool
    {
        return $this->isSuperAdministrator($authUser);
    }

    public function view(User $authUser, Role $role): bool
    {
        return $this->isSuperAdministrator($authUser);
    }

    public function create(User $authUser): bool
    {
        return $this->isSuperAdministrator($authUser);
    }

    public function update(User $authUser, Role $role): bool
    {
        return $this->isSuperAdministrator($authUser);
    }

    public function delete(User $authUser, Role $role): bool
    {
        return $this->isSuperAdministrator($authUser);
    }

    public function deleteAny(User $authUser): bool
    {
        return $this->isSuperAdministrator($authUser);
    }

    public function restore(User $authUser, Role $role): bool
    {
        return $this->isSuperAdministrator($authUser);
    }

    public function forceDelete(User $authUser, Role $role): bool
    {
        return $this->isSuperAdministrator($authUser);
    }

    public function forceDeleteAny(User $authUser): bool
    {
        return $this->isSuperAdministrator($authUser);
    }

    public function restoreAny(User $authUser): bool
    {
        return $this->isSuperAdministrator($authUser);
    }

    public function replicate(User $authUser, Role $role): bool
    {
        return $this->isSuperAdministrator($authUser);
    }

    public function reorder(User $authUser): bool
    {
        return $this->isSuperAdministrator($authUser);
    }

    private function isSuperAdministrator(User $authUser): bool
    {
        return $authUser->hasRole(UserRole::SuperAdmin->value);
    }
}
