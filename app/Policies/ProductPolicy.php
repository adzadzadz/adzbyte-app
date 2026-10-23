<?php

namespace App\Policies;

use App\Enums\ProductCapability;
use App\Enums\UserRole;
use App\Models\Product;
use App\Models\User;

final class ProductPolicy
{
    public function viewAny(User $actor): bool
    {
        return $this->allows($actor, ProductCapability::ViewAny);
    }

    public function view(User $actor, Product $product): bool
    {
        return $this->allows($actor, ProductCapability::View);
    }

    public function create(User $actor): bool
    {
        return $this->allows($actor, ProductCapability::Create);
    }

    public function update(User $actor, Product $product): bool
    {
        return $this->allows($actor, ProductCapability::Update);
    }

    public function publish(User $actor, Product $product): bool
    {
        return $this->allows($actor, ProductCapability::Publish);
    }

    public function unpublish(User $actor, Product $product): bool
    {
        return $this->allows($actor, ProductCapability::Unpublish);
    }

    public function setAvailability(User $actor, Product $product): bool
    {
        return $this->allows($actor, ProductCapability::SetAvailability);
    }

    public function archive(User $actor, Product $product): bool
    {
        return $this->allows($actor, ProductCapability::Archive);
    }

    public function restore(User $actor, Product $product): bool
    {
        return $this->allows($actor, ProductCapability::Restore);
    }

    public function revisePrice(User $actor, Product $product): bool
    {
        return $this->allows($actor, ProductCapability::RevisePrice);
    }

    public function reviseTerms(User $actor, Product $product): bool
    {
        return $this->allows($actor, ProductCapability::ReviseTerms);
    }

    public function delete(User $actor, Product $product): bool
    {
        return false;
    }

    public function deleteAny(User $actor): bool
    {
        return false;
    }

    public function forceDelete(User $actor, Product $product): bool
    {
        return false;
    }

    public function forceDeleteAny(User $actor): bool
    {
        return false;
    }

    public function replicate(User $actor, Product $product): bool
    {
        return false;
    }

    private function allows(User $actor, ProductCapability $capability): bool
    {
        return $actor->hasAnyRole([UserRole::Administrator->value, UserRole::SuperAdmin->value])
            && $actor->can($capability->value);
    }
}
