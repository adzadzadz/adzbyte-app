<?php

namespace Database\Seeders;

use App\Enums\ProductCapability;
use App\Enums\ProductCategoryCapability;
use App\Enums\UserRole;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        foreach (UserRole::cases() as $role) {
            Role::findOrCreate($role->value, 'web');
        }

        foreach ([...ProductCapability::cases(), ...ProductCategoryCapability::cases()] as $capability) {
            Permission::findOrCreate($capability->value, 'web');
        }
    }
}
