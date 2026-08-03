<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Filament\Account\Pages\Dashboard;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;
use Filament\Widgets\AccountWidget;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_customer_panel_uses_the_first_party_home_without_stock_widgets(): void
    {
        $panel = Filament::getPanel('account');

        $this->assertContains(Dashboard::class, $panel->getPages());
        $this->assertNotContains(AccountWidget::class, $panel->getWidgets());
        $this->assertSame('Home', Dashboard::getNavigationLabel());
    }

    public function test_a_customer_sees_only_their_own_account_context_on_the_home_page(): void
    {
        $this->seed(RoleSeeder::class);

        $customer = User::factory()->create([
            'name' => 'Amina Santos',
            'email' => 'amina@example.test',
        ]);
        $customer->assignRole(UserRole::Customer->value);

        User::factory()->create([
            'name' => 'Another Customer',
            'email' => 'other@example.test',
        ])->assignRole(UserRole::Customer->value);

        $this->actingAs($customer)
            ->get('/')
            ->assertOk()
            ->assertSee('data-customer-home', false)
            ->assertSee('Welcome back, Amina.')
            ->assertSee('amina@example.test')
            ->assertSee('Email verified')
            ->assertSee('Your workspace is ready')
            ->assertSee('Manage profile')
            ->assertDontSee('other@example.test');
    }

    public function test_the_customer_home_does_not_replace_the_administration_dashboard(): void
    {
        $this->seed(RoleSeeder::class);

        $administrator = User::factory()->create();
        $administrator->assignRole(UserRole::Administrator->value);

        $this->actingAs($administrator)
            ->get('/admin')
            ->assertOk()
            ->assertDontSee('data-customer-home', false)
            ->assertDontSee('Your workspace is ready');
    }
}
