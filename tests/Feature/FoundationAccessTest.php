<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class FoundationAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_both_panels_boot_and_require_authentication(): void
    {
        $this->get('/admin')
            ->assertRedirect('/admin/login');

        $this->get('/account')
            ->assertRedirect('/account/login');

        $this->get('/admin/login')
            ->assertOk();

        $this->get('/account/login')
            ->assertOk();
    }

    public function test_customers_can_only_enter_the_account_panel(): void
    {
        $customer = $this->userWithRoles(UserRole::Customer);

        $this->actingAs($customer)
            ->get('/account')
            ->assertOk();

        $this->actingAs($customer)
            ->get('/admin')
            ->assertForbidden();
    }

    public function test_administrators_can_only_enter_the_admin_panel(): void
    {
        $administrator = $this->userWithRoles(UserRole::Administrator);

        $this->actingAs($administrator)
            ->get('/admin')
            ->assertOk();

        $this->actingAs($administrator)
            ->get('/account')
            ->assertForbidden();
    }

    public function test_super_administrators_can_only_enter_the_admin_panel(): void
    {
        $superAdministrator = $this->userWithRoles(UserRole::SuperAdmin);

        $this->actingAs($superAdministrator)
            ->get('/admin')
            ->assertOk();

        $this->actingAs($superAdministrator)
            ->get('/admin/shield/roles')
            ->assertOk();

        $this->actingAs($superAdministrator)
            ->get('/account')
            ->assertForbidden();
    }

    public function test_administrators_without_role_permissions_cannot_manage_roles(): void
    {
        $administrator = $this->userWithRoles(UserRole::Administrator);

        $this->actingAs($administrator)
            ->get('/admin/shield/roles')
            ->assertForbidden();
    }

    public function test_dual_role_users_can_enter_both_panels(): void
    {
        $user = $this->userWithRoles(
            UserRole::Customer,
            UserRole::Administrator,
        );

        $this->actingAs($user)
            ->get('/account')
            ->assertOk();

        $this->actingAs($user)
            ->get('/admin')
            ->assertOk();
    }

    public function test_users_without_roles_cannot_enter_either_panel(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/account')
            ->assertForbidden();

        $this->actingAs($user)
            ->get('/admin')
            ->assertForbidden();
    }

    public function test_role_seeding_is_repeatable(): void
    {
        $this->seed(RoleSeeder::class);
        $this->seed(RoleSeeder::class);

        $this->assertSame(
            ['administrator', 'customer', 'super_admin'],
            Role::query()->orderBy('name')->pluck('name')->all(),
        );
    }

    public function test_users_can_issue_sanctum_tokens(): void
    {
        $user = User::factory()->create();

        $token = $user->createToken('foundation-test', ['orders:view']);

        $this->assertNotEmpty($token->plainTextToken);
        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_id' => $user->getKey(),
            'name' => 'foundation-test',
            'abilities' => '["orders:view"]',
        ]);
    }

    private function userWithRoles(UserRole ...$roles): User
    {
        $this->seed(RoleSeeder::class);

        $user = User::factory()->create();
        $user->assignRole(array_map(
            static fn (UserRole $role): string => $role->value,
            $roles,
        ));

        return $user;
    }
}
