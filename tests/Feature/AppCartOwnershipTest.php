<?php

namespace Tests\Feature;

use App\Actions\Carts\CartActions;
use App\Actions\Carts\CartHandoffActions;
use App\Actions\Catalog\CatalogActions;
use App\Enums\UserRole;
use App\Http\Responses\CustomerLoginResponse;
use App\Models\Cart;
use App\Models\CustomerCart;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\User;
use App\Notifications\CustomerAccountActivation;
use App\Services\Carts\CartPrincipal;
use App\Services\Carts\StorefrontCredential;
use Database\Seeders\RoleSeeder;
use Filament\Auth\Http\Responses\Contracts\LoginResponse;
use Filament\Auth\Notifications\ResetPassword;
use Filament\Auth\Pages\Login;
use Filament\Auth\Pages\PasswordReset\RequestPasswordReset;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Events\RouteMatched;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class AppCartOwnershipTest extends TestCase
{
    use RefreshDatabase;

    private User $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        config(['app.url' => 'https://app.example.test']);
        $this->customer = User::factory()->create(['password' => 'Synthetic-login1!']);
        $this->customer->assignRole(UserRole::Customer->value);
        Filament::setCurrentPanel(Filament::getPanel('account'));
        // Livewire unit requests deliberately disable middleware. Attach the
        // real test session to those synthetic requests so login listeners and
        // response adapters exercise the same session boundary as real HTTP.
        $this->app['events']->listen(RouteMatched::class, function ($event): void {
            if (str_contains($event->request->path(), 'livewire') && ! $event->request->hasSession()) {
                $event->request->setLaravelSession($this->app['session']->driver());
            }
        });
    }

    public function test_real_customer_login_claims_guest_cart_and_recovers_it_after_logout(): void
    {
        [$guest, $source] = $this->guest([$this->offer()]);
        $this->withSession($this->guestContext($guest, $source));
        $this->assertInstanceOf(CustomerLoginResponse::class, app(LoginResponse::class));
        Livewire::test(Login::class)->fillForm(['email' => $this->customer->email, 'password' => 'Synthetic-login1!'])->call('authenticate')->assertHasNoFormErrors()->assertRedirect('/cart');
        $this->assertAuthenticatedAs($this->customer);
        $this->assertSame(2, session('commerce.cart.version'));
        $this->get('/api/v1/cart', ['Accept' => 'application/json'])->assertOk()->assertJsonPath('data.public_id', $source)->assertJsonCount(1, 'data.items');
        $this->get('/cart')->assertOk()->assertSee('Your selections are saved in your account.')->assertDontSee('Sign in to continue');
        $revision = Cart::sole()->revision;
        $this->post('/logout')->assertRedirect('/login');
        $this->assertGuest();
        $this->get('/api/v1/cart', ['Accept' => 'application/json'])->assertNotFound();
        Livewire::test(Login::class)->fillForm(['email' => $this->customer->email, 'password' => 'Synthetic-login1!'])->call('authenticate')->assertRedirect('/cart');
        $this->get('/api/v1/cart', ['Accept' => 'application/json'])->assertOk()->assertJsonPath('data.public_id', $source);
        $this->assertSame($revision, Cart::sole()->revision);
        $this->assertDatabaseCount('customer_carts', 1);
        $this->assertDatabaseCount('carts', 1);
    }

    public function test_registration_activation_keeps_guest_cart_and_attaches_only_after_email_control(): void
    {
        $unverified = User::factory()->unverified()->create();
        $unverified->assignRole(UserRole::Customer->value);
        [$guest, $source] = $this->guest([$this->offer()]);
        $this->withSession($this->guestContext($guest, $source));
        $this->get('/register')->assertOk();
        $this->assertNull(Cart::sole()->user_id);
        $url = (new CustomerAccountActivation)->activationUrl($unverified);
        $this->post($url, ['password' => 'Synthetic-activation1!', 'password_confirmation' => 'Synthetic-activation1!'])->assertRedirect('/cart');
        $this->assertAuthenticatedAs($unverified);
        $this->assertTrue($unverified->fresh()->hasVerifiedEmail());
        $this->assertSame($unverified->id, Cart::sole()->user_id);
        $this->assertNull(Cart::sole()->credential_hash);
        $this->get('/api/v1/cart', ['Accept' => 'application/json'])->assertOk()->assertJsonPath('data.public_id', $source);
    }

    public function test_conflicting_login_preserves_both_carts_then_explicit_api_choice_merges_once(): void
    {
        [$guest, $source, $two, $regular] = $this->conflictingCarts();
        $this->withSession($this->guestContext($guest, $source));
        Livewire::test(Login::class)->fillForm(['email' => $this->customer->email, 'password' => 'Synthetic-login1!'])->call('authenticate')->assertRedirect('/cart');
        $this->assertSame(1, session('commerce.cart.version'));
        $this->get('/cart')->assertOk()->assertSee('Save your selections')->assertSee('Save selections to my account');
        $preview = $this->get('/api/v1/cart/merge', ['Accept' => 'application/json'])->assertOk()->assertJsonCount(1, 'data.conflicts')->assertJsonCount(2, 'data.incoming.items')->assertJsonCount(1, 'data.saved.items')->json('data');
        $this->assertStringNotContainsString($guest->credentialHash(), json_encode($preview));
        $this->app->instance('env', 'local');
        $operation = (string) Str::uuid();
        $body = ['fingerprint' => $preview['fingerprint'], 'choices' => [$preview['conflicts'][0]['key'] => $two->public_id]];
        $headers = ['Origin' => 'https://app.example.test', 'Idempotency-Key' => $operation, 'X-CSRF-TOKEN' => 'test-csrf'];
        $this->withSession(['_token' => 'test-csrf'])->postJson('/api/v1/cart/attach', $body, [...$headers, 'Origin' => 'https://evil.example'])->assertForbidden();
        $this->withSession(['_token' => 'test-csrf'])->postJson('/api/v1/cart/attach', [...$body, 'user_id' => $this->customer->id], $headers)->assertUnprocessable();
        $result = $this->withSession(['_token' => 'test-csrf'])->postJson('/api/v1/cart/attach', $body, $headers)->assertOk()->assertJsonCount(2, 'data.items');
        $this->assertEqualsCanonicalizing([$two->public_id, $regular->public_id], array_map(fn ($line) => $line['product']['public_id'], $result->json('data.items')));
        $this->assertSame(2, session('commerce.cart.version'));
        $this->withSession(['_token' => 'test-csrf'])->postJson('/api/v1/cart/attach', $body, $headers)->assertOk()->assertJsonPath('data.public_id', $result->json('data.public_id'));
        $this->assertDatabaseCount('customer_carts', 1);
        $this->assertDatabaseCount('cart_items', 2);
        $this->assertSame(1, Cart::where('public_id', $source)->sole()->changes()->where('action', 'merge')->count());
    }

    public function test_native_stale_preview_redirects_to_review_and_valid_choice_preserves_regular_items(): void
    {
        [$guest, $source, $two, $regular] = $this->conflictingCarts();
        $this->actingAs($this->customer)->withSession($this->guestContext($guest, $source));
        $preview = $this->get('/api/v1/cart/merge', ['Accept' => 'application/json'])->assertOk()->json('data');
        app(CartActions::class)->add($guest, $source, $this->offer()->public_id, (string) Str::uuid());
        $body = ['operation_id' => (string) Str::uuid(), 'fingerprint' => $preview['fingerprint'], 'choices' => [$preview['conflicts'][0]['key'] => $two->public_id]];
        $this->post('/cart/attach', $body, ['Origin' => 'https://app.example.test'])->assertRedirect('/cart')->assertSessionHas('status', 'Your selections changed. Review them again before saving.');
        $this->assertSame(1, session('commerce.cart.version'));
        $fresh = $this->get('/api/v1/cart/merge', ['Accept' => 'application/json'])->assertOk()->json('data');
        $body['fingerprint'] = $fresh['fingerprint'];
        $this->post('/cart/attach', $body, ['Origin' => 'https://app.example.test'])->assertRedirect('/cart')->assertSessionHas('status', 'Your selections are saved in your account cart.');
        $this->get('/api/v1/cart', ['Accept' => 'application/json'])->assertOk()->assertJsonCount(3, 'data.items');
        $this->assertSame(2, session('commerce.cart.version'));
    }

    public function test_owned_context_cannot_cross_identity_verification_or_role_boundaries(): void
    {
        [$guest, $source] = $this->guest([$this->offer()]);
        $this->withSession($this->guestContext($guest, $source));
        Livewire::test(Login::class)->fillForm(['email' => $this->customer->email, 'password' => 'Synthetic-login1!'])->call('authenticate');
        $owned = session('commerce.cart');
        $other = User::factory()->create();
        $other->assignRole([UserRole::Customer->value, UserRole::SuperAdmin->value]);
        $this->actingAs($other)->withSession(['commerce.cart' => $owned])->get('/api/v1/cart', ['Accept' => 'application/json'])->assertForbidden();
        $this->actingAs($this->customer)->withSession(['commerce.cart' => $owned]);
        $this->customer->forceFill(['email_verified_at' => null])->save();
        $this->get('/api/v1/cart', ['Accept' => 'application/json'])->assertForbidden();
        $this->customer->markEmailAsVerified();
        $this->customer->removeRole(UserRole::Customer->value);
        $this->get('/api/v1/cart', ['Accept' => 'application/json'])->assertForbidden();
        $this->assertSame($this->customer->id, CustomerCart::sole()->user_id);
    }

    public function test_already_authenticated_handoff_claims_nonconflicting_guest_with_real_csrf(): void
    {
        [$guest, $source] = $this->guest([$this->offer()]);
        $service = User::factory()->create();
        $issuer = $service->createToken('storefront:authenticated-handoff', StorefrontCredential::abilities())->accessToken;
        $link = app(CartHandoffActions::class)->mint($guest, $source, $issuer, (string) Str::uuid());
        $token = explode('#', $link['url'])[1];
        $this->app->instance('env', 'local');
        $this->actingAs($this->customer)->withSession(['_token' => 'test-csrf'])->postJson('/api/v1/cart-handoffs/consume', ['token' => $token], ['Origin' => 'https://app.example.test', 'X-CSRF-TOKEN' => 'test-csrf'])->assertOk()->assertExactJson(['data' => ['redirect' => '/cart'], 'meta' => ['api_version' => 'v1']]);
        $this->assertSame(2, session('commerce.cart.version'));
        $this->get('/api/v1/cart', ['Accept' => 'application/json'])->assertOk()->assertJsonPath('data.public_id', $source);
        $this->assertSame($this->customer->id, Cart::sole()->user_id);
    }

    public function test_unverified_or_service_only_session_cannot_preview_or_attach_owned_carts(): void
    {
        [$guest, $source] = $this->guest([$this->offer()]);
        $this->withSession($this->guestContext($guest, $source))->get('/api/v1/cart/merge', ['Accept' => 'application/json'])->assertUnauthorized();
        $unverified = User::factory()->unverified()->create();
        $unverified->assignRole(UserRole::Customer->value);
        $this->actingAs($unverified)->get('/api/v1/cart/merge', ['Accept' => 'application/json'])->assertForbidden();
        $service = User::factory()->create();
        $token = $service->createToken('storefront:no-customer-auth', StorefrontCredential::abilities())->plainTextToken;
        Auth::forgetGuards();
        $this->get('/api/v1/cart/merge', ['Accept' => 'application/json', 'Authorization' => 'Bearer '.$token, 'X-Cart-Proof' => bin2hex(random_bytes(32))])->assertUnauthorized();
        $this->assertDatabaseCount('customer_carts', 0);
        $this->assertNull(Cart::sole()->user_id);
    }

    public function test_password_recovery_request_preserves_unclaimed_guest_context(): void
    {
        Notification::fake();
        [$guest, $source] = $this->guest([$this->offer()]);
        $this->withSession($this->guestContext($guest, $source));
        Livewire::test(RequestPasswordReset::class)->fillForm(['email' => $this->customer->email])->call('request')->assertHasNoFormErrors();
        $this->assertSame($source, session('commerce.cart.public_id'));
        $this->assertSame($guest->credentialHash(), session('commerce.cart.authority_hash'));
        $this->assertNull(Cart::sole()->user_id);
        $notice = Notification::sent($this->customer, ResetPassword::class)->sole();
        Livewire::test(\Filament\Auth\Pages\PasswordReset\ResetPassword::class, ['email' => $this->customer->email, 'token' => $notice->token])
            ->fillForm(['password' => 'Recovered-password2!', 'passwordConfirmation' => 'Recovered-password2!'])->call('resetPassword')->assertHasNoFormErrors()->assertRedirect('/login');
        $this->assertGuest();
        $this->assertSame($source, session('commerce.cart.public_id'));
        $this->assertNull(Cart::sole()->user_id);
        Livewire::test(Login::class)->fillForm(['email' => $this->customer->email, 'password' => 'Recovered-password2!'])->call('authenticate')->assertHasNoFormErrors()->assertRedirect('/cart');
        $this->assertSame(2, session('commerce.cart.version'));
        $this->assertSame($this->customer->id, Cart::sole()->user_id);
    }

    public function test_authenticated_email_verification_attaches_the_preserved_guest_cart(): void
    {
        $this->customer->forceFill(['email_verified_at' => null])->save();
        [$guest, $source] = $this->guest([$this->offer()]);
        $this->actingAs($this->customer)->withSession($this->guestContext($guest, $source));
        $url = URL::temporarySignedRoute('filament.account.auth.email-verification.verify', now()->addHour(), ['id' => $this->customer->id, 'hash' => sha1($this->customer->email)]);
        $this->get($url)->assertRedirect('/');
        $this->assertTrue($this->customer->fresh()->hasVerifiedEmail());
        $this->assertSame(2, session('commerce.cart.version'));
        $this->get('/api/v1/cart', ['Accept' => 'application/json'])->assertOk()->assertJsonPath('data.public_id', $source);
    }

    public function test_merge_preview_hides_unpublished_product_and_category_details(): void
    {
        [$guest, $source] = $this->conflictingCarts();
        $staff = User::factory()->create();
        $staff->assignRole(UserRole::SuperAdmin->value);
        $actions = app(CatalogActions::class);
        foreach (Product::whereNotNull('category_id')->get() as $product) {
            $actions->unpublish($staff, $product, (string) Str::uuid());
            $actions->update($staff, $product, ['name' => 'PRIVATE DRAFT OFFER'], (string) Str::uuid());
        }
        $this->actingAs($this->customer)->withSession($this->guestContext($guest, $source));
        $response = $this->get('/api/v1/cart/merge', ['Accept' => 'application/json'])->assertOk()->assertJsonPath('data.conflicts.0.category', 'Service category');
        $this->assertStringNotContainsString('PRIVATE DRAFT OFFER', $response->getContent());
        $this->assertStringNotContainsString('Restricted synthetic', $response->getContent());
        $this->assertStringNotContainsString($guest->credentialHash(), $response->getContent());
        $this->get('/cart')->assertOk()->assertSee('Unavailable service')->assertDontSee('PRIVATE DRAFT OFFER')->assertDontSee('Restricted synthetic');
    }

    public function test_a_stale_guest_session_recovers_only_the_verified_customers_current_cart(): void
    {
        [$guest, $source] = $this->guest([$this->offer()]);
        $preview = app(CartActions::class)->mergePreview($this->customer, $guest, $source);
        app(CartActions::class)->attach($this->customer, $guest, $source, (string) Str::uuid(), $preview['fingerprint']);
        // Another tab still holds the pre-claim app session, but its guest
        // proof has been revoked. Verified ownership is the only recovery path.
        $this->actingAs($this->customer)->withSession($this->guestContext($guest, $source));
        $this->get('/cart')->assertOk()->assertSee('Your selections are saved in your account.')->assertDontSee('No cart is open');
        $this->assertSame(2, session('commerce.cart.version'));
        $other = User::factory()->create();
        $other->assignRole(UserRole::Customer->value);
        $this->actingAs($other)->withSession($this->guestContext($guest, $source));
        $this->get('/cart')->assertOk()->assertSee('No cart is open')->assertDontSee('Your selections are saved in your account.');
        $this->assertSame($this->customer->id, Cart::sole()->user_id);
    }

    public function test_administrator_login_does_not_implicitly_claim_a_guest_cart(): void
    {
        $this->customer->assignRole(UserRole::Administrator->value);
        [$guest, $source] = $this->guest([$this->offer()]);
        $this->withSession($this->guestContext($guest, $source));
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Livewire::test(Login::class)->fillForm(['email' => $this->customer->email, 'password' => 'Synthetic-login1!'])->call('authenticate')->assertHasNoFormErrors()->assertRedirect('/admin');
        $this->assertAuthenticatedAs($this->customer);
        $this->assertNull(Cart::sole()->user_id);
        $this->assertSame(1, session('commerce.cart.version'));
        $this->assertDatabaseCount('customer_carts', 0);
    }

    private function conflictingCarts(): array
    {
        $category = ProductCategory::create(['name' => 'Restricted synthetic', 'limit_one_per_cart' => true]);
        $one = $this->offer($category);
        $two = $this->offer($category);
        $regular = $this->offer();
        [$first, $target] = $this->guest([$one]);
        $preview = app(CartActions::class)->mergePreview($this->customer, $first, $target);
        app(CartActions::class)->attach($this->customer, $first, $target, (string) Str::uuid(), $preview['fingerprint']);
        [$guest, $source] = $this->guest([$two, $regular]);

        return [$guest, $source, $two, $regular];
    }

    private function guest(array $products): array
    {
        $guest = CartPrincipal::guest(bin2hex(random_bytes(32)));
        $reference = app(CartActions::class)->create($guest, (string) Str::uuid())['public_id'];
        foreach ($products as $product) {
            app(CartActions::class)->add($guest, $reference, $product->public_id, (string) Str::uuid());
        }

        return [$guest, $reference];
    }

    private function guestContext(CartPrincipal $guest, string $reference): array
    {
        return ['commerce.cart' => ['version' => 1, 'public_id' => $reference, 'authority_hash' => $guest->credentialHash()]];
    }

    private function offer(?ProductCategory $category = null): Product
    {
        $product = Product::factory()->published()->create(['category_id' => $category?->id]);
        $product->prices()->create(['version' => 1, 'amount_minor' => 12500, 'currency' => 'PHP']);
        $product->termsVersions()->create(['version' => 1, 'content' => ['scope' => 'Synthetic scope']]);
        $product->is_available = true;
        $product->save();

        return $product;
    }
}
