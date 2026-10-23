<?php

namespace App\Support;

use App\Enums\PortalCapability;
use App\Enums\UserRole;
use App\Models\User;
use App\Support\Records\RecordVisibility;

final class PortalAccess
{
    public static function staff(User $user, PortalCapability $capability): bool
    {
        return ! RecordVisibility::isDeleted($user) && $user->hasVerifiedEmail()
            && $user->hasAnyRole([UserRole::Administrator->value, UserRole::SuperAdmin->value])
            && ($user->hasRole(UserRole::SuperAdmin->value) || $user->checkPermissionTo($capability->value));
    }

    public static function customer(User $user): bool
    {
        return ! RecordVisibility::isDeleted($user) && $user->hasVerifiedEmail() && $user->hasRole(UserRole::Customer->value);
    }
}
