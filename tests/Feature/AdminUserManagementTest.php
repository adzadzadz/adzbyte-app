<?php

namespace Tests\Feature;

use App\Actions\Users\UpdateManagedUser;
use App\Enums\UserRole;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Filament\Resources\Users\UserResource;
use App\Models\Role;
use App\Models\User;
use BezhanSalleh\FilamentShield\FilamentShield;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class AdminUserManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_a_super_administrator_can_list_and_update_users_without_password_access(): void
    {
        $superAdministrator = $this->userWithRole(UserRole::SuperAdmin);
        $customer = $this->userWithRole(UserRole::Customer, [
            'name' => 'Original Customer',
            'email' => 'original@example.test',
        ]);

        $this->actingAs($superAdministrator);

        $this->get('/admin/users')->assertOk();

        Livewire::test(ListUsers::class)
            ->assertCanSeeTableRecords([$superAdministrator, $customer]);

        Livewire::test(EditUser::class, ['record' => $customer->getRouteKey()])
            ->assertFormFieldEnabled('roles')
            ->fillForm([
                'name' => 'Managed Customer',
                'email' => 'managed@example.test',
                'roles' => [UserRole::Administrator->value],
            ])
            ->call('save')
            ->assertHasNoFormErrors()
            ->assertNotified();

        $customer->refresh();

        $this->assertSame('Managed Customer', $customer->name);
        $this->assertSame('managed@example.test', $customer->email);
        $this->assertNull($customer->email_verified_at);
        $this->assertTrue($customer->hasExactRoles(UserRole::Administrator->value));
        $this->assertArrayNotHasKey('password', Livewire::test(EditUser::class, [
            'record' => $customer->getRouteKey(),
        ])->get('data'));
    }

    public function test_regular_administrators_have_no_user_or_role_administration_by_default(): void
    {
        $administrator = $this->userWithRole(UserRole::Administrator);

        $this->actingAs($administrator)
            ->get('/admin/users')
            ->assertForbidden();

        $this->actingAs($administrator)
            ->get('/admin/shield/roles')
            ->assertForbidden();
    }

    public function test_user_capabilities_can_allow_identity_updates_but_never_role_assignment_or_super_admin_edits(): void
    {
        $administrator = $this->userWithRole(UserRole::Administrator);
        $customer = $this->userWithRole(UserRole::Customer);
        $superAdministrator = $this->userWithRole(UserRole::SuperAdmin);

        foreach (['ViewAny:User', 'View:User', 'Update:User', 'ViewAny:Role'] as $permissionName) {
            $administrator->givePermissionTo(Permission::findOrCreate($permissionName, 'web'));
        }

        $this->actingAs($administrator)
            ->get('/admin/users')
            ->assertOk();

        $this->actingAs($administrator)
            ->get('/admin/shield/roles')
            ->assertForbidden();

        $this->assertTrue(Gate::forUser($administrator)->allows('update', $customer));
        $this->assertFalse(Gate::forUser($administrator)->allows('assignRoles', $customer));
        $this->assertFalse(Gate::forUser($administrator)->allows('update', $superAdministrator));
        $this->assertFalse(Gate::forUser($administrator)->allows('viewAny', Role::class));

        Livewire::test(EditUser::class, ['record' => $customer->getRouteKey()])
            ->assertFormFieldDisabled('roles');

        app(UpdateManagedUser::class)->handle(
            $administrator,
            $customer,
            ['name' => 'Capability Managed', 'email' => $customer->email],
            [UserRole::Customer->value],
        );

        $this->assertSame('Capability Managed', $customer->refresh()->name);

        $this->expectException(AuthorizationException::class);

        app(UpdateManagedUser::class)->handle(
            $administrator,
            $customer,
            ['name' => $customer->name, 'email' => $customer->email],
            [UserRole::Administrator->value],
        );
    }

    public function test_a_super_administrator_cannot_change_their_own_roles(): void
    {
        $superAdministrator = $this->userWithRole(UserRole::SuperAdmin);

        $this->actingAs($superAdministrator);

        Livewire::test(EditUser::class, ['record' => $superAdministrator->getRouteKey()])
            ->assertFormFieldDisabled('roles');

        try {
            app(UpdateManagedUser::class)->handle(
                $superAdministrator,
                $superAdministrator,
                ['name' => $superAdministrator->name, 'email' => $superAdministrator->email],
                [UserRole::Administrator->value],
            );

            $this->fail('Expected self-role assignment to be rejected.');
        } catch (ValidationException $exception) {
            $this->assertSame(
                ['You cannot change your own role assignments.'],
                $exception->errors()['roles'],
            );
        }

        $this->assertTrue($superAdministrator->refresh()->hasExactRoles(UserRole::SuperAdmin->value));
    }

    public function test_managed_users_require_a_valid_identity_and_application_access_role(): void
    {
        $superAdministrator = $this->userWithRole(UserRole::SuperAdmin);
        $customer = $this->userWithRole(UserRole::Customer);
        $otherCustomer = $this->userWithRole(UserRole::Customer);
        Role::findOrCreate('custom_access', 'web');

        foreach ([
            [
                'attributes' => ['name' => '', 'email' => 'invalid'],
                'roles' => [UserRole::Customer->value],
                'fields' => ['name', 'email'],
            ],
            [
                'attributes' => ['name' => $customer->name, 'email' => $otherCustomer->email],
                'roles' => [UserRole::Customer->value],
                'fields' => ['email'],
            ],
            [
                'attributes' => ['name' => $customer->name, 'email' => $customer->email],
                'roles' => ['custom_access'],
                'fields' => ['roles'],
            ],
        ] as $case) {
            try {
                app(UpdateManagedUser::class)->handle(
                    $superAdministrator,
                    $customer,
                    $case['attributes'],
                    $case['roles'],
                );

                $this->fail('Expected managed-user validation to fail.');
            } catch (ValidationException $exception) {
                foreach ($case['fields'] as $field) {
                    $this->assertArrayHasKey($field, $exception->errors());
                }
            }
        }
    }

    public function test_user_creation_and_deletion_are_not_exposed_as_admin_routes(): void
    {
        $superAdministrator = $this->userWithRole(UserRole::SuperAdmin);

        $this->actingAs($superAdministrator)
            ->get('/admin/users/create')
            ->assertNotFound();

        $this->assertSame(['index', 'edit'], array_keys(UserResource::getPages()));
    }

    public function test_shield_discovers_only_the_intended_user_resource_permissions(): void
    {
        $permissions = app(FilamentShield::class)->getEntitiesPermissions();

        $this->assertContains('ViewAny:User', $permissions);
        $this->assertContains('View:User', $permissions);
        $this->assertContains('Update:User', $permissions);
        $this->assertNotContains('Create:User', $permissions);
        $this->assertNotContains('Delete:User', $permissions);
    }

    public function test_application_access_roles_cannot_be_renamed_or_deleted(): void
    {
        $customerRole = Role::findByName(UserRole::Customer->value, 'web');

        try {
            $customerRole->update(['name' => 'renamed_customer']);
            $this->fail('Expected the application role rename to fail.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('name', $exception->errors());
        }

        try {
            $customerRole->delete();
            $this->fail('Expected the application role deletion to fail.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('name', $exception->errors());
        }

        $customRole = Role::create(['name' => 'temporary_operator', 'guard_name' => 'web']);
        $customRole->update(['name' => 'renamed_operator']);
        $customRole->delete();

        $this->assertDatabaseHas('roles', ['name' => UserRole::Customer->value]);
        $this->assertDatabaseMissing('roles', ['name' => 'renamed_operator']);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function userWithRole(UserRole $role, array $attributes = []): User
    {
        $user = User::factory()->create($attributes);
        $user->assignRole($role->value);

        return $user;
    }
}
