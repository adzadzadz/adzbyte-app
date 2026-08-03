<?php

namespace Tests\Feature;

use App\Actions\Customers\CreateProvisionalCustomer;
use App\Actions\Customers\SendCustomerAccountActivation;
use App\Enums\UserRole;
use App\Events\CustomerAccountActivated;
use App\Events\CustomerAccountProvisioned;
use App\Exceptions\ExistingAccountRequiresAuthentication;
use App\Models\User;
use App\Notifications\CustomerAccountActivation;
use Database\Seeders\RoleSeeder;
use Filament\Auth\Notifications\ResetPassword as ResetPasswordNotification;
use Filament\Auth\Pages\Login as LoginPage;
use Filament\Auth\Pages\PasswordReset\RequestPasswordReset;
use Filament\Facades\Filament;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Livewire\Livewire;
use Tests\TestCase;

class AuthenticationLifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    public function test_trusted_logic_creates_only_an_unverified_customer_account(): void
    {
        Event::fake([CustomerAccountProvisioned::class]);

        $user = app(CreateProvisionalCustomer::class)->handle(
            name: '  Test Customer  ',
            email: '  CUSTOMER@EXAMPLE.COM ',
        );

        $this->assertSame('Test Customer', $user->name);
        $this->assertSame('customer@example.com', $user->email);
        $this->assertFalse($user->hasVerifiedEmail());
        $this->assertTrue($user->hasRole(UserRole::Customer->value));
        $this->assertCount(1, $user->roles);
        $this->assertNotSame('', $user->password);
        Event::assertDispatched(
            CustomerAccountProvisioned::class,
            fn (CustomerAccountProvisioned $event): bool => $event->user->is($user),
        );
    }

    public function test_existing_emails_require_authentication_before_checkout_continues(): void
    {
        User::factory()->create(['email' => 'existing@example.com']);

        try {
            app(CreateProvisionalCustomer::class)->handle(
                name: 'Existing Customer',
                email: 'EXISTING@example.com',
            );

            $this->fail('An existing email was allowed to create another user.');
        } catch (ExistingAccountRequiresAuthentication $exception) {
            $this->assertSame(
                'Authentication is required before checkout can continue.',
                $exception->getMessage(),
            );
        }

        $this->assertDatabaseCount('users', 1);
    }

    public function test_activation_is_sent_only_to_unverified_customer_accounts(): void
    {
        Notification::fake();

        $customer = User::factory()->unverified()->create();
        $customer->assignRole(UserRole::Customer->value);

        $this->assertTrue(app(SendCustomerAccountActivation::class)->handle($customer));

        Notification::assertSentTo($customer, CustomerAccountActivation::class);

        $customer->markEmailAsVerified();

        $this->assertFalse(app(SendCustomerAccountActivation::class)->handle($customer));
        Notification::assertSentToTimes($customer, CustomerAccountActivation::class, 1);
    }

    public function test_non_customer_accounts_cannot_receive_customer_activation_links(): void
    {
        Notification::fake();

        $administrator = User::factory()->unverified()->create();
        $administrator->assignRole(UserRole::Administrator->value);

        $this->expectException(\LogicException::class);

        app(SendCustomerAccountActivation::class)->handle($administrator);
    }

    public function test_a_valid_activation_sets_the_password_verifies_email_and_signs_in(): void
    {
        Event::fake([CustomerAccountActivated::class, Verified::class]);

        $customer = User::factory()->unverified()->create();
        $customer->assignRole(UserRole::Customer->value);
        $activationUrl = (new CustomerAccountActivation)->activationUrl($customer);

        $this->get($activationUrl)
            ->assertOk()
            ->assertSee('Activate your account')
            ->assertSee($customer->email);

        $this->post($activationUrl, [
            'password' => 'StrongPassword123!',
            'password_confirmation' => 'StrongPassword123!',
        ])->assertRedirect('/account');

        $customer->refresh();

        $this->assertTrue($customer->hasVerifiedEmail());
        $this->assertTrue(Hash::check('StrongPassword123!', $customer->password));
        $this->assertAuthenticatedAs($customer);
        Event::assertDispatched(Verified::class);
        Event::assertDispatched(
            CustomerAccountActivated::class,
            fn (CustomerAccountActivated $event): bool => $event->user->is($customer),
        );

        auth()->logout();

        $this->post($activationUrl, [
            'password' => 'AnotherPassword123!',
            'password_confirmation' => 'AnotherPassword123!',
        ])->assertForbidden();
    }

    public function test_expired_activation_links_are_rejected(): void
    {
        $customer = User::factory()->unverified()->create();
        $customer->assignRole(UserRole::Customer->value);
        $activationUrl = (new CustomerAccountActivation)->activationUrl($customer);

        $this->travel(24)->hours();
        $this->travel(1)->minute();

        $this->get($activationUrl)->assertForbidden();
    }

    public function test_activation_links_cannot_authorize_another_email_or_role(): void
    {
        $administrator = User::factory()->unverified()->create();
        $administrator->assignRole(UserRole::Administrator->value);

        $signedUrl = URL::temporarySignedRoute(
            'account-activation.edit',
            now()->addDay(),
            [
                'user' => $administrator->getKey(),
                'hash' => sha1($administrator->getEmailForVerification()),
            ],
        );

        $this->get($signedUrl)->assertForbidden();
    }

    public function test_activation_submissions_are_rate_limited(): void
    {
        $customer = User::factory()->unverified()->create();
        $customer->assignRole(UserRole::Customer->value);
        $activationUrl = (new CustomerAccountActivation)->activationUrl($customer);
        RateLimiter::clear("{$customer->getKey()}|127.0.0.1");

        foreach (range(1, 5) as $attempt) {
            $this->post($activationUrl, [
                'password' => 'weak',
                'password_confirmation' => 'weak',
            ])->assertSessionHasErrors('password');
        }

        $this->post($activationUrl, [
            'password' => 'weak',
            'password_confirmation' => 'weak',
        ])->assertTooManyRequests();
    }

    public function test_filament_authentication_features_are_enabled_without_registration(): void
    {
        $this->get('/account/password-reset/request')->assertOk();
        $this->get('/admin/password-reset/request')->assertOk();
        $this->get('/account/register')->assertNotFound();
        $this->get('/admin/register')->assertNotFound();

        $customer = User::factory()->create();
        $customer->assignRole(UserRole::Customer->value);

        $this->actingAs($customer)
            ->get('/account/profile')
            ->assertOk();

        $this->post('/account/logout')
            ->assertRedirect('/account/login');

        $this->assertGuest();
    }

    public function test_unverified_panel_users_must_complete_email_verification(): void
    {
        $customer = User::factory()->unverified()->create();
        $customer->assignRole(UserRole::Customer->value);

        $this->actingAs($customer)
            ->get('/account')
            ->assertRedirect('/account/email-verification/prompt');

        $this->get('/account/email-verification/prompt')->assertOk();
    }

    public function test_filament_email_verification_marks_the_shared_user_verified(): void
    {
        Event::fake([Verified::class]);

        $customer = User::factory()->unverified()->create();
        $customer->assignRole(UserRole::Customer->value);
        $verificationUrl = Filament::getPanel('account')->getVerifyEmailUrl($customer);

        $this->actingAs($customer)
            ->get($verificationUrl)
            ->assertRedirect('/account');

        $this->assertTrue($customer->refresh()->hasVerifiedEmail());
        Event::assertDispatched(Verified::class);
    }

    public function test_filament_login_emits_the_framework_authentication_event(): void
    {
        Event::fake([Login::class]);

        $customer = User::factory()->create();
        $customer->assignRole(UserRole::Customer->value);
        Filament::setCurrentPanel(Filament::getPanel('account'));

        Livewire::test(LoginPage::class)
            ->fillForm([
                'email' => $customer->email,
                'password' => 'password',
            ])
            ->call('authenticate')
            ->assertHasNoFormErrors();

        $this->assertAuthenticatedAs($customer);
        Event::assertDispatched(Login::class);
    }

    public function test_filament_password_reset_uses_the_shared_customer_identity(): void
    {
        Notification::fake();

        $customer = User::factory()->create();
        $customer->assignRole(UserRole::Customer->value);
        Filament::setCurrentPanel(Filament::getPanel('account'));

        Livewire::test(RequestPasswordReset::class)
            ->fillForm(['email' => $customer->email])
            ->call('request')
            ->assertHasNoFormErrors();

        Notification::assertSentTo($customer, ResetPasswordNotification::class);
    }

    public function test_confirmed_production_origins_are_stateful_sanctum_domains(): void
    {
        $this->assertContains('adzbyte.com', config('sanctum.stateful'));
        $this->assertContains('app.adzbyte.com', config('sanctum.stateful'));
    }
}
