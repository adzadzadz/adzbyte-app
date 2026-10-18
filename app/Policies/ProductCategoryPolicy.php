<?php

namespace App\Policies;

use App\Enums\ProductCategoryCapability;
use App\Enums\UserRole;
use App\Models\ProductCategory;
use App\Models\User;

final class ProductCategoryPolicy
{
    public function viewAny(User $actor): bool
    {
        return $this->allows($actor, ProductCategoryCapability::ViewAny);
    }

    public function view(User $actor, ProductCategory $category): bool
    {
        return $this->allows($actor, ProductCategoryCapability::View);
    }

    public function create(User $actor): bool
    {
        return $this->allows($actor, ProductCategoryCapability::Create);
    }

    public function update(User $actor, ProductCategory $category): bool
    {
        return $this->allows($actor, ProductCategoryCapability::Update);
    }

    public function delete(User $actor, ProductCategory $category): bool
    {
        return false;
    }

    public function deleteAny(User $actor): bool
    {
        return false;
    }

    public function forceDelete(User $actor, ProductCategory $category): bool
    {
        return false;
    }

    public function forceDeleteAny(User $actor): bool
    {
        return false;
    }

    public function replicate(User $actor, ProductCategory $category): bool
    {
        return false;
    }

    private function allows(User $actor, ProductCategoryCapability $capability): bool
    {
        return $actor->hasAnyRole([UserRole::Administrator->value, UserRole::SuperAdmin->value]) && $actor->can($capability->value);
    }
}
