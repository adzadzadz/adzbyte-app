<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Filament\Pages\Dashboard;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;
use Filament\Widgets\AccountWidget;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_admin_panel_uses_the_first_party_operations_home_without_stock_widgets(): void
    {
        $panel = Filament::getPanel('admin');

        $this->assertContains(Dashboard::class, $panel->getPages());
        $this->assertNotContains(AccountWidget::class, $panel->getWidgets());
        $this->assertSame('Overview', Dashboard::getNavigationLabel());
    }

    public function test_an_administrator_sees_only_their_own_access_context(): void
    {
        $this->seed(RoleSeeder::class);

        $administrator = User::factory()->create([
            'name' => 'Operations Admin',
            'email' => 'operations@example.test',
        ]);
        $administrator->assignRole(UserRole::Administrator->value);

        User::factory()->create([
            'name' => 'Another Admin',
            'email' => 'other-admin@example.test',
        ])->assignRole(UserRole::Administrator->value);

        $this->actingAs($administrator)
            ->get('/admin')
            ->assertOk()
            ->assertSee('data-admin-home', false)
            ->assertSee('Welcome back, Operations.')
            ->assertSee('operations@example.test')
            ->assertSee('Administrator')
            ->assertSee('Core safeguards')
            ->assertDontSee('other-admin@example.test');
    }

    public function test_the_administration_home_remains_forbidden_to_customers(): void
    {
        $this->seed(RoleSeeder::class);

        $customer = User::factory()->create();
        $customer->assignRole(UserRole::Customer->value);

        $this->actingAs($customer)
            ->get('/admin')
            ->assertForbidden();
    }
}
