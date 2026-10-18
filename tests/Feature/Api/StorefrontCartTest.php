<?php

namespace Tests\Feature\Api;

use App\Actions\Carts\CartActions;
use App\Actions\Catalog\CatalogActions;
use App\Enums\UserRole;
use App\Models\Cart;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\User;
use App\Services\Carts\CartPrincipal;
use App\Services\Carts\StorefrontCredential;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class StorefrontCartTest extends TestCase
{
    use RefreshDatabase;

    private string $token;

    private string $proof;

    private User $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->service = User::factory()->create();
        $this->token = $this->service->createToken('storefront:test', StorefrontCredential::abilities())->plainTextToken;
        $this->proof = bin2hex(random_bytes(32));
    }

    public function test_real_bearer_flow_preserves_mixed_categories_and_replays_replacement_notices(): void
    {
        config(['sanctum.stateful' => ['adzbyte.com'], 'session.driver' => 'database']);
        $creation = (string) Str::uuid();
        $created = $this->api('POST', '/api/v1/storefront/carts', [], ['Idempotency-Key' => $creation, 'Origin' => 'https://adzbyte.com'])->assertCreated()->assertHeaderMissing('Set-Cookie');
        $cart = $created->json('data.public_id');
        $this->assertDatabaseCount('sessions', 0);
        $this->api('POST', '/api/v1/storefront/carts', [], ['Idempotency-Key' => $creation])->assertCreated()->assertJsonPath('data.public_id', $cart);
        $this->api('GET', '/api/v1/storefront/carts/resolve?creation_operation_id='.$creation)->assertOk()->assertJsonPath('data.public_id', $cart);
        $category = ProductCategory::create(['name' => 'Synthetic restricted', 'limit_one_per_cart' => true]);
        $first = $this->offer($category);
        $regular = $this->offer(amount: 2100);
        $replacement = $this->offer($category, amount: 15000);
        foreach ([$first, $regular] as $product) {
            $this->api('POST', "/api/v1/storefront/carts/$cart/items", ['product' => $product->public_id], ['Idempotency-Key' => (string) Str::uuid()])->assertOk();
        }
        $operation = (string) Str::uuid();
        $result = $this->api('POST', "/api/v1/storefront/carts/$cart/items", ['product' => $replacement->public_id], ['Idempotency-Key' => $operation]);
        $result->assertOk()->assertJsonCount(2, 'data.items')->assertJsonPath('data.subtotal_minor', '17100')
            ->assertJsonPath('data.effects.replaced_products.0.public_id', $first->public_id)->assertJsonPath('data.effects.replaced_products.0.name', $first->name)
            ->assertJsonPath('meta.api_version', 'v1');
        $replay = $this->api('POST', "/api/v1/storefront/carts/$cart/items", ['product' => $replacement->public_id], ['Idempotency-Key' => $operation]);
        $this->assertSame($result->getContent(), $replay->getContent());
        $read = $this->api('GET', "/api/v1/storefront/carts/$cart")->assertOk()->assertJsonPath('data.effects.replaced_products', []);
        $this->api('HEAD', "/api/v1/storefront/carts/$cart")->assertOk()->assertContent('');
        $this->assertStringNotContainsString($this->proof, $read->getContent());
        $this->assertSame(['public_id', 'revision', 'currency', 'subtotal_minor', 'subtotal_complete', 'selection_valid', 'requires_review', 'category_conflicts', 'items', 'effects'], array_keys($read->json('data')));
        foreach ($read->json('data.items') as $line) {
            $this->assertSame(1, $line['quantity']);
            $this->assertArrayHasKey('category', $line['product']);
            foreach (['id', 'user_id', 'status', 'category_id', 'created_at', 'updated_at'] as $private) {
                $this->assertArrayNotHasKey($private, $line['product']);
            }
        }
        $this->assertDatabaseCount('carts', 1);
        $this->assertDatabaseCount('cart_items', 2);
        $this->assertDatabaseCount('cart_changes', 4);
        $this->assertNull(Cart::firstOrFail()->user_id);
    }

    public function test_handoff_http_requires_scoped_issuer_and_proof_and_replays_only_live_links(): void
    {
        config(['app.url' => 'https://app.example.test']);
        $cart = $this->api('POST', '/api/v1/storefront/carts', [], ['Idempotency-Key' => (string) Str::uuid()])->assertCreated()->json('data.public_id');
        $path = "/api/v1/storefront/carts/$cart/handoffs";
        $headers = ['Idempotency-Key' => (string) Str::uuid()];
        $readOnly = $this->service->createToken('read-only', ['cart:read'])->plainTextToken;
        $this->api('POST', $path, [], [...$headers, 'Authorization' => 'Bearer '.$readOnly])->assertForbidden();
        $this->api('POST', $path, [], [...$headers, 'X-Cart-Proof' => bin2hex(random_bytes(32))])->assertNotFound();
        $this->api('POST', $path, ['return_url' => 'https://untrusted.example'], $headers)->assertUnprocessable();
        $created = $this->api('POST', $path, [], $headers)->assertCreated()->assertHeader('Cache-Control', 'no-store, private');
        $this->assertMatchesRegularExpression('~^https://app\.example\.test/cart/handoff#[a-f0-9]{64}$~', $created->json('data.url'));
        $this->assertSame(['url', 'expires_at'], array_keys($created->json('data')));
        $this->assertStringNotContainsString($this->proof, $created->getContent());
        $this->assertSame($created->getContent(), $this->api('POST', $path, [], $headers)->assertCreated()->getContent());
        $this->travel(61)->seconds();
        $this->api('POST', $path, [], $headers)->assertConflict();
        $this->api('POST', $path, [], ['Idempotency-Key' => (string) Str::uuid()])->assertCreated();
        $this->assertDatabaseCount('cart_handoffs', 2);
        $this->assertDatabaseCount('carts', 1);
    }

    public function test_retirement_acknowledgement_never_exposes_owned_cart_or_future_activity(): void
    {
        $creation = (string) Str::uuid();
        $source = $this->api('POST', '/api/v1/storefront/carts', [], ['Idempotency-Key' => $creation])->assertCreated()->json('data.public_id');
        $path = '/api/v1/storefront/carts/retired?creation_operation_id='.$creation;
        $this->api('GET', $path)->assertNotFound();
        $actor = User::factory()->create();
        $actor->assignRole(UserRole::Customer->value);
        $guest = CartPrincipal::guest($this->proof);
        $actions = app(CartActions::class);
        $state = $actions->mergePreview($actor, $guest, $source);
        $actions->attach($actor, $guest, $source, (string) Str::uuid(), $state['fingerprint']);
        $expected = ['data' => ['retired' => true], 'meta' => ['api_version' => 'v1']];
        $this->api('GET', '/api/v1/storefront/carts/'.$source)->assertNotFound();
        $this->api('GET', $path)->assertOk()->assertExactJson($expected)->assertHeader('Cache-Control', 'no-store, private');
        $this->api('GET', $path, [], ['X-Cart-Proof' => bin2hex(random_bytes(32))])->assertNotFound();
        $this->api('GET', $path.'&owner='.$actor->id)->assertUnprocessable();
        $this->api('GET', '/api/v1/storefront/carts/retired?creation_operation_id='.Str::uuid())->assertNotFound();
        $actions->add(CartPrincipal::customer($actor), $source, $this->offer()->public_id, (string) Str::uuid());
        $this->api('GET', $path)->assertOk()->assertExactJson($expected);
        $this->api('HEAD', $path)->assertOk()->assertContent('');
        $token = $this->service->createToken('read-only', ['cart:read'])->plainTextToken;
        $this->api('GET', $path, [], ['Authorization' => 'Bearer '.$token])->assertOk()->assertExactJson($expected);
    }

    #[DataProvider('badAbilities')]
    public function test_absent_wildcard_unrelated_or_excessive_token_abilities_are_rejected(array $abilities): void
    {
        $token = $this->service->createToken('bad-test', $abilities)->plainTextToken;
        $this->api('POST', '/api/v1/storefront/carts', [], ['Authorization' => 'Bearer '.$token, 'Idempotency-Key' => (string) Str::uuid()])->assertForbidden()->assertJsonPath('error.code', 'forbidden');
        $this->assertDatabaseCount('carts', 0);
    }

    public static function badAbilities(): array
    {
        return [[[]], [['*']], [['integration']], [['cart:create', 'customer:write']], [['cart:read']]];
    }

    public function test_expired_revoked_unverified_and_privileged_identities_cannot_use_cart_api(): void
    {
        $expired = $this->service->createToken('expired', StorefrontCredential::abilities(), now()->subSecond());
        $revoked = $this->service->createToken('revoked', StorefrontCredential::abilities());
        $revoked->accessToken->delete();
        foreach ([$expired->plainTextToken, $revoked->plainTextToken, 'invalid'] as $token) {
            $this->api('GET', '/api/v1/storefront/carts/'.Str::ulid(), [], ['Authorization' => 'Bearer '.$token])->assertUnauthorized();
        }
        foreach ([UserRole::Customer, UserRole::Administrator, UserRole::SuperAdmin] as $role) {
            $identity = User::factory()->create();
            $identity->assignRole($role->value);
            $token = $identity->createToken('privileged', StorefrontCredential::abilities())->plainTextToken;
            $this->api('GET', '/api/v1/storefront/carts/'.Str::ulid(), [], ['Authorization' => 'Bearer '.$token])->assertForbidden();
        }
        $unverified = User::factory()->unverified()->create();
        $token = $unverified->createToken('unverified', StorefrontCredential::abilities())->plainTextToken;
        $this->api('GET', '/api/v1/storefront/carts/'.Str::ulid(), [], ['Authorization' => 'Bearer '.$token])->assertForbidden();
        $this->service->givePermissionTo('ViewAny:Product');
        $this->api('GET', '/api/v1/storefront/carts/'.Str::ulid())->assertForbidden();
    }

    public function test_missing_service_token_and_first_party_session_fallback_do_not_grant_cart_access(): void
    {
        $this->api('GET', '/api/v1/storefront/carts/'.Str::ulid(), [], ['Authorization' => ''])->assertUnauthorized();
        $this->actingAs($this->service);
        $this->getJson('/api/v1/storefront/carts/'.Str::ulid(), ['X-Cart-Proof' => $this->proof, 'Authorization' => '', 'Origin' => 'https://adzbyte.com'])->assertForbidden();
    }

    public function test_cart_proof_is_required_and_service_credential_cannot_cross_cart_authority(): void
    {
        $operation = (string) Str::uuid();
        $cart = $this->api('POST', '/api/v1/storefront/carts', [], ['Idempotency-Key' => $operation])->assertCreated()->json('data.public_id');
        $this->api('GET', "/api/v1/storefront/carts/$cart", [], ['X-Cart-Proof' => ''])->assertUnprocessable();
        foreach (["/api/v1/storefront/carts/$cart", '/api/v1/storefront/carts/resolve?creation_operation_id='.$operation] as $path) {
            $this->api('GET', $path, [], ['X-Cart-Proof' => bin2hex(random_bytes(32))])->assertNotFound();
        }
        $product = $this->offer();
        $this->api('POST', "/api/v1/storefront/carts/$cart/items", ['product' => $product->public_id], ['X-Cart-Proof' => bin2hex(random_bytes(32)), 'Idempotency-Key' => (string) Str::uuid()])->assertNotFound();
        $this->api('POST', '/api/v1/storefront/carts', [], ['X-Cart-Proof' => bin2hex(random_bytes(32)), 'Idempotency-Key' => $operation])->assertNotFound();
        $this->assertDatabaseCount('cart_items', 0);
    }

    public function test_read_only_credential_can_read_its_proof_scoped_cart_but_cannot_mutate(): void
    {
        $cart = $this->api('POST', '/api/v1/storefront/carts', [], ['Idempotency-Key' => (string) Str::uuid()])->assertCreated()->json('data.public_id');
        $token = $this->service->createToken('read-only', ['cart:read'])->plainTextToken;
        $this->api('GET', "/api/v1/storefront/carts/$cart", [], ['Authorization' => 'Bearer '.$token])->assertOk();
        $this->api('POST', "/api/v1/storefront/carts/$cart/items", ['product' => $this->offer()->public_id], ['Authorization' => 'Bearer '.$token, 'Idempotency-Key' => (string) Str::uuid()])->assertForbidden();
    }

    public function test_strict_inputs_reject_owners_totals_quantities_query_fields_and_bad_keys(): void
    {
        foreach ([['user_id' => $this->service->id], ['subtotal_minor' => '1'], ['proof' => $this->proof], ['quantity' => 2]] as $input) {
            $this->api('POST', '/api/v1/storefront/carts', $input, ['Idempotency-Key' => (string) Str::uuid()])->assertUnprocessable();
        }
        foreach (['', 'not-a-uuid'] as $key) {
            $this->api('POST', '/api/v1/storefront/carts', [], ['Idempotency-Key' => $key])->assertUnprocessable();
        }
        $this->api('POST', '/api/v1/storefront/carts?owner=1', [], ['Idempotency-Key' => (string) Str::uuid()])->assertUnprocessable();
        $this->api('GET', '/api/v1/storefront/carts/resolve?creation_operation_id=invalid')->assertUnprocessable();
        $this->api('GET', '/api/v1/storefront/carts/'.Str::ulid().'?include=changes')->assertUnprocessable();
        $cart = $this->api('POST', '/api/v1/storefront/carts', [], ['Idempotency-Key' => (string) Str::uuid()])->assertCreated()->json('data.public_id');
        $this->api('POST', "/api/v1/storefront/carts/$cart/items", ['product' => $this->offer()->public_id, 'quantity' => 2], ['Idempotency-Key' => (string) Str::uuid()])->assertUnprocessable();
        $this->assertDatabaseCount('cart_items', 0);
    }

    public function test_unknown_cart_reference_and_method_are_safe_json_errors_without_state_changes(): void
    {
        $this->api('GET', '/api/v1/storefront/carts/internal-id')->assertNotFound()->assertJsonPath('error.code', 'not_found');
        $this->api('PUT', '/api/v1/storefront/carts', [])->assertStatus(405)->assertJsonPath('error.code', 'method_not_allowed');
        $this->api('GET', '/api/v1/storefront/carts/'.Str::ulid())->assertNotFound();
        $this->assertDatabaseCount('carts', 0);
    }

    public function test_only_json_object_mutations_and_bodyless_reads_are_accepted(): void
    {
        foreach (['[]', 'null', '{broken', '"text"'] as $raw) {
            Auth::forgetGuards();
            $headers = $this->transformHeadersToServerVars(['Accept' => 'application/json', 'Content-Type' => 'application/json', 'Authorization' => 'Bearer '.$this->token, 'X-Cart-Proof' => $this->proof, 'Idempotency-Key' => (string) Str::uuid()]);
            $this->call('POST', '/api/v1/storefront/carts', [], [], [], $headers, $raw)->assertUnprocessable()->assertJsonPath('error.code', 'validation_failed');
        }
        Auth::forgetGuards();
        $this->call('GET', '/api/v1/storefront/carts/'.Str::ulid(), [], [], [], $headers, '{}')->assertUnprocessable();
        Auth::forgetGuards();
        $this->post('/api/v1/storefront/carts', [], ['Accept' => 'application/json', 'Authorization' => 'Bearer '.$this->token, 'X-Cart-Proof' => $this->proof, 'Idempotency-Key' => (string) Str::uuid()])->assertUnprocessable();
        $this->assertDatabaseCount('carts', 0);
    }

    public function test_offer_review_remove_and_conflicting_uuid_reuse_use_the_shared_service(): void
    {
        $cart = $this->api('POST', '/api/v1/storefront/carts', [], ['Idempotency-Key' => (string) Str::uuid()])->assertCreated()->json('data.public_id');
        $product = $this->offer();
        $operation = (string) Str::uuid();
        $this->api('POST', "/api/v1/storefront/carts/$cart/items", ['product' => $product->public_id], ['Idempotency-Key' => $operation])->assertOk();
        $admin = $this->admin();
        app(CatalogActions::class)->revisePrice($admin, $product, ['amount_minor' => 14000, 'currency' => 'PHP'], (string) Str::uuid());
        $this->api('GET', "/api/v1/storefront/carts/$cart")->assertOk()->assertJsonPath('data.requires_review', true)->assertJsonPath('data.subtotal_minor', '14000');
        $this->api('POST', "/api/v1/storefront/carts/$cart/confirm", ['versions' => [$product->public_id => ['price' => 1, 'terms' => 1]]], ['Idempotency-Key' => (string) Str::uuid()])->assertUnprocessable();
        $this->api('POST', "/api/v1/storefront/carts/$cart/confirm", ['versions' => [$product->public_id => ['price' => 2, 'terms' => 1]]], ['Idempotency-Key' => (string) Str::uuid()])->assertOk()->assertJsonPath('data.requires_review', false);
        $this->api('DELETE', "/api/v1/storefront/carts/$cart/items/$product->public_id", [], ['Idempotency-Key' => $operation])->assertUnprocessable()->assertJsonValidationErrors('operation_id', 'error.details.fields');
        $this->api('DELETE', "/api/v1/storefront/carts/$cart/items/$product->public_id", [], ['Idempotency-Key' => (string) Str::uuid()])->assertOk()->assertJsonPath('data.items', []);
    }

    public function test_unpublished_saved_product_draft_content_and_price_never_leak_through_cart_or_notice(): void
    {
        $cart = $this->api('POST', '/api/v1/storefront/carts', [], ['Idempotency-Key' => (string) Str::uuid()])->assertCreated()->json('data.public_id');
        $category = ProductCategory::create(['name' => 'Restricted', 'limit_one_per_cart' => true]);
        $product = $this->offer($category);
        $this->api('POST', "/api/v1/storefront/carts/$cart/items", ['product' => $product->public_id], ['Idempotency-Key' => (string) Str::uuid()])->assertOk();
        $admin = $this->admin();
        $catalog = app(CatalogActions::class);
        $catalog->unpublish($admin, $product, (string) Str::uuid());
        $catalog->update($admin, $product, ['name' => 'PRIVATE DRAFT NAME', 'description' => 'PRIVATE DRAFT CONTENT'], (string) Str::uuid());
        $catalog->revisePrice($admin, $product, ['amount_minor' => 735291, 'currency' => 'PHP'], (string) Str::uuid());
        $read = $this->api('GET', "/api/v1/storefront/carts/$cart")->assertOk()->assertJsonPath('data.subtotal_minor', '0')
            ->assertJsonPath('data.subtotal_complete', false)->assertJsonPath('data.items.0.available', false)
            ->assertJsonPath('data.items.0.product.name', 'Unavailable service')->assertJsonPath('data.items.0.product.price', null)->assertJsonPath('data.items.0.product.terms', null);
        foreach (['PRIVATE DRAFT', '735291'] as $private) {
            $this->assertStringNotContainsString($private, $read->getContent());
        }
        $replacement = $this->offer($category);
        $this->api('POST', "/api/v1/storefront/carts/$cart/items", ['product' => $replacement->public_id], ['Idempotency-Key' => (string) Str::uuid()])->assertOk()->assertJsonPath('data.effects.replaced_products.0.name', 'Unavailable service');
    }

    public function test_cart_proof_rate_limit_does_not_exhaust_different_cart_scope(): void
    {
        for ($i = 0; $i < 120; $i++) {
            $this->api('GET', '/api/v1/storefront/carts/'.Str::ulid())->assertNotFound();
        }
        $this->api('GET', '/api/v1/storefront/carts/'.Str::ulid())->assertStatus(429)->assertJsonPath('error.code', 'rate_limited')->assertHeader('Retry-After');
        $this->api('GET', '/api/v1/storefront/carts/'.Str::ulid(), [], ['X-Cart-Proof' => bin2hex(random_bytes(32))])->assertNotFound();
    }

    public function test_changing_cart_proof_cannot_bypass_the_global_service_rate_limit(): void
    {
        $this->freezeTime();
        for ($i = 0; $i < 600; $i++) {
            $this->api('GET', '/api/v1/storefront/carts/'.Str::ulid(), [], ['X-Cart-Proof' => bin2hex(random_bytes(32))])->assertNotFound();
        }
        $this->api('GET', '/api/v1/storefront/carts/'.Str::ulid(), [], ['X-Cart-Proof' => bin2hex(random_bytes(32))])->assertStatus(429)->assertJsonPath('error.code', 'rate_limited');
        $this->travelBack();
    }

    private function api(string $method, string $path, array $input = [], array $headers = []): TestResponse
    {
        Auth::forgetGuards();
        $headers = ['Accept' => 'application/json', 'Content-Type' => 'application/json', 'Authorization' => 'Bearer '.$this->token, 'X-Cart-Proof' => $this->proof, ...$headers];
        $response = $this->call($method, $path, [], [], [], $this->transformHeadersToServerVars($headers), in_array($method, ['GET', 'HEAD'], true) ? null : json_encode((object) $input, JSON_THROW_ON_ERROR));
        $response->assertHeader('Cache-Control', 'no-store, private');
        $this->assertFalse($response->headers->has('ETag'));

        return $response;
    }

    private function offer(?ProductCategory $category = null, int $amount = 12500): Product
    {
        $product = Product::factory()->published()->create(['category_id' => $category?->id]);
        $product->prices()->create(['version' => 1, 'amount_minor' => $amount, 'currency' => 'PHP']);
        $product->termsVersions()->create(['version' => 1, 'content' => ['scope' => 'Synthetic scope', 'internal_note' => 'PRIVATE']]);
        $product->is_available = true;
        $product->save();

        return $product;
    }

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->assignRole(UserRole::SuperAdmin->value);

        return $admin;
    }
}
