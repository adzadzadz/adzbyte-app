<?php

namespace Tests\Feature;

use App\Actions\Customers\CreateProvisionalCustomer;
use App\Notifications\CustomerAccountActivation;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;
use Tests\TestCase;

class AccountCommerceUxTest extends TestCase
{
    use RefreshDatabase;

    public function test_activation_explains_password_rules_and_associates_field_errors(): void
    {
        $this->seed(RoleSeeder::class);
        $user = app(CreateProvisionalCustomer::class)->handle('Preview Customer', 'preview@example.test');
        $url = (new CustomerAccountActivation)->activationUrl($user);
        $this->get($url)->assertOk()
            ->assertSee('at least 12 characters')
            ->assertSee('uppercase and lowercase letters, a number, and a symbol')
            ->assertSee('Show passwords')
            ->assertSee('aria-controls="password password_confirmation"', false)
            ->assertSee(route('filament.account.auth.password-reset.request'), false);
        view()->share('errors', (new ViewErrorBag)->put('default', new MessageBag([
            'password' => 'Choose a stronger password.',
            'password_confirmation' => 'Confirm your password.',
        ])));
        $html = view('auth.activate-account', ['user' => $user])->render();
        $this->assertStringContainsString('aria-invalid="true"', $html);
        $this->assertStringContainsString('aria-describedby="password-requirements password-error"', $html);
        $this->assertStringContainsString('id="password-confirmation-error"', $html);
    }

    public function test_registration_uses_account_styles_and_retains_accessible_validation(): void
    {
        $this->get('/register')->assertOk()
            ->assertSee('adz-activation-card')
            ->assertSee('Send account link')
            ->assertDontSee('name="password"', false);
        view()->share('errors', (new ViewErrorBag)->put('default', new MessageBag([
            'email' => 'Enter a valid email address.',
        ])));
        $html = view('auth.register', ['website' => 'https://example.test'])->render();
        $this->assertStringContainsString('aria-describedby="email-error"', $html);
        $this->assertStringContainsString('Enter a valid email address.', $html);
    }

    public function test_production_cart_preview_hides_checkout_and_keeps_portal_return(): void
    {
        $this->app->instance('env', 'production');
        config(['commerce.preview_in_production' => true]);
        view()->share('errors', new ViewErrorBag);
        $html = view('cart.show', [
            'shop' => 'https://example.test/services', 'owned' => true,
            'cart' => ['items' => [[
                'product' => ['public_id' => 'service', 'name' => 'Service', 'category' => null, 'price' => ['amount_minor' => '123456'], 'terms' => null],
                'available' => true, 'requires_review' => false, 'category_conflict' => false,
            ]], 'selection_valid' => true, 'subtotal_minor' => '123456', 'subtotal_complete' => true, 'requires_review' => false, 'category_conflicts' => []],
        ])->render();
        $this->assertStringContainsString('Read-only preview.', $html);
        $this->assertStringContainsString('Return to your dashboard', $html);
        $this->assertStringNotContainsString('Continue to review', $html);
        $this->assertStringNotContainsString('href="'.route('checkout.show').'"', $html);
        $this->assertStringContainsString('disabled', $html);
    }

    public function test_orders_empty_state_distinguishes_purchase_requests_from_support(): void
    {
        view()->share('errors', new ViewErrorBag);
        $html = view('orders.index', ['orders' => [], 'pagination' => ['last_page' => 1]])->render();
        $this->assertStringContainsString('Orders', $html);
        $this->assertStringContainsString('No purchase requests yet.', $html);
        $this->assertStringContainsString('For project questions and issue reports', $html);
    }
}
