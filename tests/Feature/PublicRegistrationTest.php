<?php

namespace Tests\Feature;

use App\Actions\Customers\RegisterPublicCustomer;
use App\Enums\UserRole;
use App\Http\Controllers\Auth\RegisterCustomerController;
use App\Jobs\RegisterPublicCustomer as RegisterJob;
use App\Models\User;
use App\Notifications\CustomerAccountActivation;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class PublicRegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        config(['app.url' => 'https://app.example.test']);
        $this->app->instance('env', 'local'); // Real CSRF; testing otherwise bypasses it.
    }

    public function test_bootstrap_and_native_page_are_public_without_cart_or_identity(): void
    {
        $this->get('/register')->assertOk()->assertSee('Create your account')->assertSee('Send account link')->assertDontSee('name="password"', false)->assertHeader('Cache-Control', 'no-store, private');
        $this->getJson('/api/v1/registration')->assertOk()->assertJsonStructure(['data' => ['csrf_token', 'submit'], 'meta' => ['api_version']])->assertJsonPath('data.submit', '/api/v1/registration');
        $this->getJson('/api/v1/registration?role=administrator')->assertUnprocessable();
        $this->assertGuest();
        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('carts', 0);
    }

    public function test_real_csrf_origin_and_strict_input_guards_run_before_dispatch(): void
    {
        $this->withoutMiddleware(ThrottleRequests::class);
        Queue::fake();
        $input = ['name' => 'Synthetic customer', 'email' => 'customer@example.test'];
        $this->postJson('/api/v1/registration', $input, ['Origin' => 'https://app.example.test'])->assertStatus(419);
        $this->withSession(['_token' => 'csrf-token'])->postJson('/api/v1/registration', $input, ['X-CSRF-TOKEN' => 'csrf-token', 'Origin' => 'https://evil.example'])->assertForbidden();
        foreach (['role' => 'super_admin', 'user_id' => 1, 'cart' => 'foreign', 'password' => 'Injected-password1!', 'email_verified_at' => now()->toIso8601String()] as $field => $value) {
            $this->submit([...$input, $field => $value])->assertUnprocessable();
        }
        $this->submit(['name' => 'Name', 'email' => 'invalid'])->assertUnprocessable();
        $this->withSession(['_token' => 'csrf-token'])->post('/api/v1/registration', $input, ['X-CSRF-TOKEN' => 'csrf-token', 'Origin' => 'https://app.example.test', 'Accept' => 'application/json'])->assertUnprocessable();
        Queue::assertNothingPushed();
        $this->assertDatabaseCount('users', 0);
    }

    public function test_unknown_and_existing_identities_receive_identical_asynchronous_outcomes(): void
    {
        Queue::fake();
        $staff = User::factory()->create(['email' => 'existing@example.test']);
        $staff->assignRole(UserRole::SuperAdmin->value);
        $responses = [];
        foreach (['unknown@example.test', 'existing@example.test'] as $email) {
            $responses[] = $this->submit(['name' => 'New supplied name', 'email' => $email])->assertStatus(202)->assertJsonPath('data.message', RegisterCustomerController::MESSAGE)->assertHeader('Cache-Control', 'no-store, private')->getContent();
        }
        $this->assertSame($responses[0], $responses[1]);
        Queue::assertPushed(RegisterJob::class, 2);
        Queue::assertPushed(RegisterJob::class, fn (RegisterJob $job): bool => $job->connection === 'database' && $job->afterCommit === true);
        $this->assertDatabaseCount('users', 1);
        $this->assertGuest();
        $this->assertSame($staff->name, $staff->fresh()->name);
    }

    public function test_native_submission_preserves_guest_cart_session_without_attaching_it(): void
    {
        Queue::fake();
        $context = ['version' => 1, 'public_id' => (string) Str::ulid(), 'authority_hash' => hash('sha256', 'synthetic')];
        $this->withSession(['_token' => 'csrf-token', 'commerce.cart' => $context])->post('/register', ['_token' => 'csrf-token', 'name' => 'Synthetic', 'email' => 'native@example.test'], ['Origin' => 'https://app.example.test'])->assertRedirect('/register')->assertSessionHas('registration_sent', RegisterCustomerController::MESSAGE)->assertSessionHas('commerce.cart', $context);
        Queue::assertPushed(RegisterJob::class);
        $this->assertDatabaseCount('carts', 0);
        $this->assertGuest();
    }

    public function test_public_identity_action_normalizes_and_retries_without_changing_an_existing_customer(): void
    {
        Notification::fake();
        $action = app(RegisterPublicCustomer::class);
        $action->handle('  Synthetic name  ', '  CUSTOMER@EXAMPLE.TEST ');
        $user = User::sole();
        $password = $user->password;
        $this->assertSame('Synthetic name', $user->name);
        $this->assertSame('customer@example.test', $user->email);
        $this->assertFalse($user->hasVerifiedEmail());
        $this->assertSame(['customer'], $user->getRoleNames()->all());
        Notification::assertSentTo($user, CustomerAccountActivation::class);
        $action->handle('Different supplied name', 'CUSTOMER@EXAMPLE.TEST');
        $this->assertDatabaseCount('users', 1);
        $this->assertSame('Synthetic name', $user->fresh()->name);
        $this->assertSame($password, $user->fresh()->password);
        Notification::assertSentToTimes($user, CustomerAccountActivation::class, 2);
    }

    public function test_public_activation_never_notifies_verified_staff_dual_role_or_directly_privileged_identities(): void
    {
        Notification::fake();
        foreach (['verified', 'staff', 'dual', 'permission', 'roleless'] as $kind) {
            $user = User::factory()->unverified()->create(['email' => "$kind@example.test"]);
            if ($kind === 'verified') {
                $user->assignRole(UserRole::Customer->value);
                $user->markEmailAsVerified();
            } elseif ($kind === 'staff') {
                $user->assignRole(UserRole::Administrator->value);
            } elseif ($kind === 'dual') {
                $user->assignRole([UserRole::Customer->value, UserRole::Administrator->value]);
            } elseif ($kind === 'permission') {
                $user->assignRole(UserRole::Customer->value);
                $user->givePermissionTo('ViewAny:Product');
            }
            $before = $user->getRawOriginal();
            app(RegisterPublicCustomer::class)->handle('Injected name', strtoupper($user->email));
            $this->assertEquals($before, $user->fresh()->getRawOriginal());
        }
        Notification::assertNothingSent();
        $this->assertDatabaseCount('users', 5);
    }

    public function test_activation_verifies_control_sets_strong_password_rotates_session_and_rejects_replay(): void
    {
        Notification::fake();
        app(RegisterPublicCustomer::class)->handle('New customer', 'activation@example.test');
        $user = User::sole();
        $notification = Notification::sent($user, CustomerAccountActivation::class)->sole();
        $url = $notification->activationUrl($user);
        $old = session()->getId();
        $this->withSession(['_token' => 'csrf-token', 'unrelated' => 'retained'])->post($url, ['_token' => 'csrf-token', 'password' => 'weak', 'password_confirmation' => 'weak'])->assertSessionHasErrors('password');
        $this->assertFalse($user->fresh()->hasVerifiedEmail());
        $this->post($url, ['_token' => 'csrf-token', 'password' => 'Synthetic-password1!', 'password_confirmation' => 'Synthetic-password1!'])->assertRedirect('/')->assertSessionHas('unrelated', 'retained');
        $this->assertAuthenticatedAs($user);
        $this->assertTrue($user->fresh()->hasVerifiedEmail());
        $this->assertTrue(Hash::check('Synthetic-password1!', $user->fresh()->password));
        $this->assertNotSame($old, session()->getId());
        $this->post($url, ['_token' => session()->token(), 'password' => 'Changed-password2!', 'password_confirmation' => 'Changed-password2!'])->assertForbidden();
    }

    public function test_email_and_ip_abuse_limits_apply_even_when_identity_is_unknown(): void
    {
        Queue::fake();
        foreach (range(1, 3) as $i) {
            $this->submit(['name' => 'Synthetic', 'email' => 'limited@example.test'])->assertStatus(202);
        }
        $this->submit(['name' => 'Synthetic', 'email' => 'LIMITED@EXAMPLE.TEST'])->assertTooManyRequests();
        // Five accepted attempts exhaust the IP limit, even across distinct emails.
        $this->submit(['name' => 'Synthetic', 'email' => 'other@example.test'])->assertStatus(202);
        $this->submit(['name' => 'Synthetic', 'email' => 'last@example.test'])->assertStatus(202);
        $this->submit(['name' => 'Synthetic', 'email' => 'sixth@example.test'])->assertTooManyRequests();
        Queue::assertPushed(RegisterJob::class, 5);
        $this->assertDatabaseCount('users', 0);
    }

    private function submit(array $input): TestResponse
    {
        return $this->withSession(['_token' => 'csrf-token'])->postJson('/api/v1/registration', $input, ['X-CSRF-TOKEN' => 'csrf-token', 'Origin' => 'https://app.example.test']);
    }
}
