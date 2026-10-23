<?php

namespace App\Policies;

use App\Enums\PortalCapability;
use App\Models\User;
use App\Models\WorkItem;
use App\Support\PortalAccess;

final class WorkItemPolicy
{
    public function view(User $user, WorkItem $work): bool
    {
        return (PortalAccess::customer($user) && $user->id === $work->user_id)
            || PortalAccess::staff($user, PortalCapability::ViewWork);
    }

    public function update(User $user, WorkItem $work): bool
    {
        return PortalAccess::staff($user, PortalCapability::ViewWork) && PortalAccess::staff($user, PortalCapability::ManageWork);
    }

    public function create(User $user): bool
    {
        return PortalAccess::staff($user, PortalCapability::ViewWork) && PortalAccess::staff($user, PortalCapability::ManageWork);
    }

    public function delete(User $user, WorkItem $work): bool
    {
        return false;
    }
}
