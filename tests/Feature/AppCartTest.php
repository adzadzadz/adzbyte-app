<?php

namespace Tests\Feature;

use App\Actions\Carts\CartActions;
use App\Actions\Carts\CartHandoffActions;
use App\Actions\Catalog\CatalogActions;
use App\Enums\UserRole;
use App\Models\Cart;
use App\Models\CartHandoff;
use App\Models\Product;
use App\Models\User;
use App\Services\Carts\CartPrincipal;
use App\Services\Carts\StorefrontCredential;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class AppCartTest extends TestCase
{
    use RefreshDatabase;

    private CartPrincipal $principal;

    private string $cart;

    private string $secret;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        config(['app.url' => 'https://app.example.test', 'commerce.storefront_origin' => 'https://shop.example.test']);
        // Exercise real web CSRF middleware rather than its testing bypass.
        $this->app->instance('env', 'local');
        $this->principal = CartPrincipal::guest(bin2hex(random_bytes(32)));
        $this->cart = app(CartActions::class)->create($this->principal, (string) Str::uuid())['public_id'];
        $this->product = Product::factory()->published()->create(['name' => 'Synthetic <script>service()</script>']);
        $this->product->prices()->create(['version' => 1, 'amount_minor' => 12500, 'currency' => 'PHP']);
        $this->product->termsVersions()->create(['version' => 1, 'content' => ['scope' => '<script>terms()</script>', 'internal_note' => 'PRIVATE']]);
        $this->product->is_available = true;
        $this->product->save();
        app(CartActions::class)->add($this->principal, $this->cart, $this->product->public_id, (string) Str::uuid());
        $issuer = User::factory()->create()->createToken('storefront:app-test', StorefrontCredential::abilities())->accessToken;
        $link = app(CartHandoffActions::class)->mint($this->principal, $this->cart, $issuer, (string) Str::uuid());
        $this->secret = parse_url($link['url'], PHP_URL_FRAGMENT);
        $this->withSession(['_token' => Str::random(40)]);
    }

    public function test_landing_preserves_nonce_csp_no_referrer_and_fixed_destination_without_token_reflection(): void
    {
        $page = $this->get('/cart/handoff')->assertOk()->assertHeader('Cache-Control', 'no-store, private')
            ->assertHeader('Referrer-Policy', 'no-referrer')->assertHeader('X-Frame-Options', 'DENY');
        $policy = $page->headers->get('Content-Security-Policy');
        $this->assertStringContainsString("default-src 'none'", $policy);
        $this->assertStringContainsString("frame-ancestors 'none'", $policy);
        preg_match("/script-src 'nonce-([^']+)'/", $policy, $match);
        $this->assertNotEmpty($match[1]);
        $page->assertSee('nonce="'.$match[1].'"', false);
        $page->assertSee("window.history.replaceState(null, '', '/cart/handoff')", false);
        $this->assertStringNotContainsString($this->secret, $page->getContent());
        $this->get('/cart/handoff?return=https://evil.example')->assertNotFound();
    }

    public function test_missing_csrf_and_wrong_origin_cannot_consume_a_link(): void
    {
        $this->api('POST', '/api/v1/cart-handoffs/consume', ['token' => $this->secret], ['X-CSRF-TOKEN' => ''])->assertStatus(419);
        $this->api('POST', '/api/v1/cart-handoffs/consume', ['token' => $this->secret], ['Origin' => 'https://evil.example'])->assertForbidden();
        $this->assertNull(CartHandoff::firstOrFail()->consumed_at);
        $this->assertNull(Session::get('commerce.cart'));
    }

    public function test_valid_link_rotates_session_opens_only_its_cart_and_replay_fails(): void
    {
        $this->open();
        $this->assertSame($this->cart, Session::get('commerce.cart.public_id'));
        $read = $this->api('GET', '/api/v1/cart')->assertOk()->assertJsonPath('data.public_id', $this->cart)->assertJsonCount(1, 'data.items');
        $this->assertStringNotContainsString(Cart::firstOrFail()->credential_hash, $read->getContent());
        $this->assertStringNotContainsString('PRIVATE', $read->getContent());
        $this->api('POST', '/api/v1/cart-handoffs/consume', ['token' => $this->secret])->assertStatus(410);
        $this->api('GET', '/api/v1/cart')->assertOk()->assertJsonPath('data.public_id', $this->cart);
    }

    public function test_public_references_and_raw_hash_headers_cannot_replace_app_session_authority(): void
    {
        $this->api('GET', '/api/v1/cart')->assertNotFound();
        $this->api('GET', '/api/v1/cart', [], ['X-Cart-Proof' => Cart::firstOrFail()->credential_hash])->assertNotFound();
        $this->api('GET', '/api/v1/cart?cart='.$this->cart)->assertUnprocessable();
        $this->withSession(['commerce' => ['cart' => ['version' => 1, 'public_id' => $this->cart, 'authority_hash' => hash('sha256', 'wrong')]]]);
        $this->api('GET', '/api/v1/cart')->assertNotFound();
        $this->get('/cart')->assertOk()->assertSee('No cart is open')->assertDontSee($this->product->name);
    }

    public function test_extra_consume_fields_and_unknown_tokens_never_assign_session_authority(): void
    {
        foreach ([['token' => $this->secret, 'cart' => $this->cart], ['token' => $this->secret, 'redirect' => 'https://evil.example']] as $input) {
            $this->api('POST', '/api/v1/cart-handoffs/consume', $input)->assertUnprocessable();
        }
        $this->api('POST', '/api/v1/cart-handoffs/consume', ['token' => bin2hex(random_bytes(32))])->assertStatus(410);
        $this->assertNull(Session::get('commerce.cart'));
        $this->assertNull(CartHandoff::firstOrFail()->consumed_at);
    }

    public function test_full_cart_is_available_before_authentication_with_safe_exact_content(): void
    {
        $this->open();
        $page = $this->get('/cart')->assertOk()->assertHeader('Cache-Control', 'no-store, private');
        $page->assertSee('Your cart')->assertSee('₱125.00')->assertSee('Synthetic &lt;script&gt;service()&lt;/script&gt;', false)
            ->assertSee('&lt;script&gt;terms()&lt;/script&gt;', false)->assertDontSee('<script>service()', false)
            ->assertDontSee('PRIVATE')->assertSee('Sign in to continue');
        $this->assertGuest();
    }

    public function test_native_review_preserves_exact_money_and_rejects_intervening_prices(): void
    {
        $this->open();
        $admin = User::factory()->create();
        $admin->assignRole(UserRole::SuperAdmin->value);
        app(CatalogActions::class)->revisePrice($admin, $this->product, ['amount_minor' => PHP_INT_MAX, 'currency' => 'PHP'], (string) Str::uuid());
        $this->get('/cart')->assertOk()->assertSee('₱92,233,720,368,547,758.07')->assertSee('Confirm reviewed prices and terms');
        $this->from('/cart')->post('/cart/confirm', ['_token' => Session::token(), 'operation_id' => (string) Str::uuid(), 'versions' => [$this->product->public_id => ['price' => '1', 'terms' => '1']]], ['Origin' => 'https://app.example.test'])->assertSessionHasErrors('versions');
        $this->from('/cart')->post('/cart/confirm', ['_token' => Session::token(), 'operation_id' => (string) Str::uuid(), 'versions' => [$this->product->public_id => ['price' => '2', 'terms' => '1']]], ['Origin' => 'https://app.example.test'])->assertRedirect('/cart')->assertSessionHas('status', 'Current prices and terms confirmed.');
        $this->api('GET', '/api/v1/cart')->assertOk()->assertJsonPath('data.requires_review', false);
    }

    public function test_app_remove_api_rechecks_csrf_origin_and_accepts_only_the_bound_cart(): void
    {
        $this->open();
        $path = '/api/v1/cart/items/'.$this->product->public_id;
        $this->api('DELETE', $path, [], ['Idempotency-Key' => (string) Str::uuid(), 'X-CSRF-TOKEN' => ''])->assertStatus(419);
        $this->api('DELETE', $path, [], ['Idempotency-Key' => (string) Str::uuid(), 'Origin' => 'https://shop.example.test'])->assertForbidden();
        $this->api('DELETE', $path, ['owner_id' => 1], ['Idempotency-Key' => (string) Str::uuid()])->assertUnprocessable();
        $this->api('DELETE', $path, [], ['Idempotency-Key' => (string) Str::uuid()])->assertOk()->assertJsonPath('data.items', []);
        $this->get('/cart')->assertOk()->assertSee('Your cart is empty');
    }

    private function open(): void
    {
        $this->api('POST', '/api/v1/cart-handoffs/consume', ['token' => $this->secret])->assertOk()->assertExactJson(['data' => ['redirect' => '/cart'], 'meta' => ['api_version' => 'v1']]);
    }

    private function api(string $method, string $path, array $input = [], array $headers = []): TestResponse
    {
        Auth::forgetGuards();
        $headers = ['Accept' => 'application/json', 'Content-Type' => 'application/json', 'Origin' => 'https://app.example.test', 'X-CSRF-TOKEN' => Session::token(), ...$headers];
        $response = $this->call($method, $path, [], [], [], $this->transformHeadersToServerVars($headers), in_array($method, ['GET', 'HEAD'], true) ? null : json_encode((object) $input, JSON_THROW_ON_ERROR));
        $response->assertHeader('Cache-Control', 'no-store, private');

        return $response;
    }
}
