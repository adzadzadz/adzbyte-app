<?php

namespace App\Policies;

use App\Enums\PortalCapability;
use App\Models\SupportCase;
use App\Models\User;
use App\Support\PortalAccess;

final class SupportCasePolicy
{
    public function view(User $user, SupportCase $case): bool
    {
        return (PortalAccess::customer($user) && $user->id === $case->user_id)
            || PortalAccess::staff($user, PortalCapability::ViewCases);
    }

    public function update(User $user, SupportCase $case): bool
    {
        return PortalAccess::staff($user, PortalCapability::ViewCases) && PortalAccess::staff($user, PortalCapability::ManageCases);
    }

    public function reply(User $user, SupportCase $case): bool
    {
        return PortalAccess::staff($user, PortalCapability::ViewCases) && PortalAccess::staff($user, PortalCapability::ReplyCases);
    }

    public function note(User $user, SupportCase $case): bool
    {
        return PortalAccess::staff($user, PortalCapability::ViewCases) && PortalAccess::staff($user, PortalCapability::NoteCases);
    }

    public function delete(User $user, SupportCase $case): bool
    {
        return false;
    }
}
