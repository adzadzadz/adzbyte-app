<?php

namespace Tests\Feature;

use App\Actions\Carts\CartActions;
use App\Actions\Orders\OrderActions;
use App\Enums\ProductStatus;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\Carts\CartPrincipal;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class CheckoutTest extends TestCase
{
    use RefreshDatabase;

    private User $customer;

    private Product $product;

    private array $input;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        config(['app.url' => 'https://app.example.test']);
        $this->app->instance('env', 'local');
        $this->customer = User::factory()->create();
        $this->customer->assignRole('customer');
        $this->product = Product::factory()->published()->create(['name' => 'Synthetic <script>bad()</script> service']);
        $this->product->prices()->create(['version' => 1, 'amount_minor' => 12500, 'currency' => 'PHP']);
        $this->product->termsVersions()->create(['version' => 1, 'content' => ['scope' => 'Synthetic <script>scope()</script>']]);
        $this->product->is_available = true;
        $this->product->save();
        $carts = app(CartActions::class);
        $guest = CartPrincipal::guest(bin2hex(random_bytes(32)));
        $cart = $carts->create($guest, (string) Str::uuid());
        $carts->add($guest, $cart['public_id'], $this->product->public_id, (string) Str::uuid());
        $preview = $carts->mergePreview($this->customer, $guest, $cart['public_id']);
        $carts->attach($this->customer, $guest, $cart['public_id'], (string) Str::uuid(), $preview['fingerprint']);
        $this->input = ['brief' => ['goal' => 'Synthetic <script>goal()</script>', 'scope' => 'Synthetic request'], 'billing' => ['name' => 'Synthetic buyer', 'email' => 'buyer@example.test', 'country' => 'PH'], 'accept_terms' => true,
            'fingerprint' => app(OrderActions::class)->review($this->customer)['fingerprint']];
    }

    public function test_actual_native_and_api_review_share_safe_current_data_and_never_offer_payment(): void
    {
        $this->actingAs($this->customer)->withSession(['_token' => 'csrf']);
        $this->get('/checkout')->assertOk()->assertSee('Send request for review')->assertSee('&lt;script&gt;bad()', false)->assertDontSee('<script>bad()', false)->assertDontSee('Pay Now')->assertHeader('Cache-Control', 'no-store, private');
        $r = $this->get('/api/v1/checkout', ['Accept' => 'application/json'])->assertOk()->assertJsonPath('data.totals.total_minor', '12500')->assertJsonPath('data.policy.tax_mode', 'unconfigured')->assertJsonPath('meta.csrf_token', 'csrf')->assertJsonPath('data.existing_order', null)->assertHeader('Cache-Control', 'no-store, private');
        $this->assertStringNotContainsString('actor_id', $r->getContent());
        $this->assertStringNotContainsString('content_hash', $r->getContent());
        $this->get('/orders')->assertOk()->assertSee('No requests yet.');
    }

    public function test_api_creation_replay_owned_read_listing_and_native_existing_request_redirect(): void
    {
        $this->actingAs($this->customer)->withSession(['_token' => 'csrf']);
        $operation = (string) Str::uuid();
        $created = $this->submit($this->input, $operation)->assertCreated()->assertJsonPath('data.approval_status', 'pending')->assertJsonPath('data.payment_status', 'unpaid')->assertJsonPath('data.accepted.total_minor', '12500');
        $ref = $created->json('data.public_id');
        $this->submit($this->input, $operation)->assertCreated()->assertJsonPath('data.public_id', $ref);
        $this->get('/api/v1/orders/'.$ref, ['Accept' => 'application/json'])->assertOk()->assertJsonPath('data.accepted.lines.0.quantity', 1);
        $this->get('/orders/'.$ref)->assertOk()->assertSee('Synthetic &lt;script&gt;goal()', false)->assertDontSee('<script>goal()', false)->assertDontSee('Pay Now');
        $this->get('/api/v1/orders?per_page=1', ['Accept' => 'application/json'])->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('meta.pagination.total', 1)->assertJsonMissingPath('data.0.accepted.brief');
        $this->get('/checkout')->assertRedirect('/orders/'.$ref);
        $this->get('/api/v1/checkout', ['Accept' => 'application/json'])->assertJsonPath('data.existing_order', $ref);
        $this->assertDatabaseCount('orders', 1);
        foreach (['user_id', 'request_hash', 'actor_id', 'policy_version_id', 'cart_id'] as $private) {
            $this->assertStringNotContainsString('"'.$private.'"', $created->getContent());
        }
    }

    public function test_native_submission_omits_blank_optional_fields_and_records_exact_acceptance(): void
    {
        $this->actingAs($this->customer)->withSession(['_token' => 'csrf']);
        $input = [...$this->input, 'accept_terms' => '1', 'operation_id' => (string) Str::uuid(), '_token' => 'csrf'];
        $input['brief']['website'] = '';
        $input['billing']['address'] = '';
        $this->post('/checkout', $input, ['Origin' => 'https://app.example.test'])->assertRedirect('/orders/'.Order::sole()->public_id)->assertSessionHas('status', 'Your request was sent to Admin for review.');
        $this->assertSame($this->input['brief'], Order::sole()->currentRevision->brief);
    }

    public function test_real_csrf_origin_strict_json_query_uuid_and_identity_fields_are_enforced(): void
    {
        $this->actingAs($this->customer);
        $this->postJson('/api/v1/orders', $this->input, ['Origin' => 'https://app.example.test', 'Idempotency-Key' => (string) Str::uuid()])->assertStatus(419);
        $this->withSession(['_token' => 'csrf'])->postJson('/api/v1/orders', $this->input, ['Origin' => 'https://evil.example', 'X-CSRF-TOKEN' => 'csrf', 'Idempotency-Key' => (string) Str::uuid()])->assertForbidden();
        $this->postJson('/api/v1/orders', $this->input, ['Origin' => 'https://app.example.test', 'X-CSRF-TOKEN' => 'csrf'])->assertUnprocessable();
        $this->get('/api/v1/checkout?user_id=1', ['Accept' => 'application/json'])->assertUnprocessable();
        $this->getJson('/api/v1/checkout')->assertUnprocessable();
        $this->get('/api/v1/orders?per_page=100', ['Accept' => 'application/json'])->assertUnprocessable();
        foreach (['user_id' => 1, 'total_minor' => '1', 'role' => 'super_admin', 'return_url' => 'https://evil.example'] as $key => $value) {
            $this->submit([...$this->input, $key => $value], (string) Str::uuid())->assertUnprocessable();
        }
        $this->submit([...$this->input, 'brief' => []], (string) Str::uuid())->assertUnprocessable();
        $this->submit([...$this->input, 'accept_terms' => '1'], (string) Str::uuid())->assertUnprocessable();
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_anonymous_unverified_staff_service_bearer_and_other_customer_cannot_cross_identity_boundaries(): void
    {
        $this->get('/api/v1/checkout', ['Accept' => 'application/json'])->assertUnauthorized();
        $this->actingAs($this->customer)->withSession(['_token' => 'csrf']);
        $created = $this->submit($this->input, (string) Str::uuid())->assertCreated();
        $ref = $created->json('data.public_id');
        $other = User::factory()->create();
        $other->assignRole('customer');
        Gate::before(fn () => true);
        $this->actingAs($other)->get('/api/v1/orders/'.$ref, ['Accept' => 'application/json'])->assertNotFound();
        $this->get('/orders/'.$ref)->assertNotFound();
        $this->get('/api/v1/orders', ['Accept' => 'application/json'])->assertOk()->assertJsonCount(0, 'data');
        $this->customer->email_verified_at = null;
        $this->customer->save();
        $this->actingAs($this->customer)->get('/api/v1/checkout', ['Accept' => 'application/json'])->assertForbidden();
        $staff = User::factory()->create();
        $staff->assignRole('super_admin');
        $this->actingAs($staff)->get('/api/v1/orders/'.$ref, ['Accept' => 'application/json'])->assertForbidden();
        auth()->logout();
        $token = User::factory()->create()->createToken('synthetic-service', ['*'])->plainTextToken;
        $this->get('/api/v1/orders/'.$ref, ['Accept' => 'application/json', 'Authorization' => 'Bearer '.$token])->assertUnauthorized();
    }

    public function test_hidden_draft_price_and_content_never_leak_through_review_totals_or_markup(): void
    {
        $this->product->forceFill(['status' => ProductStatus::Draft, 'is_available' => false])->save();
        $this->actingAs($this->customer);
        $r = $this->get('/api/v1/checkout', ['Accept' => 'application/json'])->assertOk()->assertJsonPath('data.totals.total_minor', '0')->assertJsonPath('data.cart.items.0.product.name', 'Unavailable service')->assertJsonPath('data.cart.selection_valid', false);
        $this->assertStringNotContainsString('12500', $r->getContent());
        $this->assertStringNotContainsString('scope()', $r->getContent());
        $this->get('/checkout')->assertOk()->assertDontSee('Synthetic &lt;script&gt;bad()', false)->assertDontSee('₱125.00')->assertSee('disabled', false);
    }

    public function test_pending_guest_merge_is_required_and_changed_offer_returns_conflict_preserving_native_fields(): void
    {
        $this->actingAs($this->customer)->withSession(['commerce.cart' => ['version' => 1, 'public_id' => (string) Str::ulid(), 'authority_hash' => str_repeat('a', 64)], '_token' => 'csrf']);
        $this->get('/checkout')->assertRedirect('/cart');
        $this->get('/api/v1/checkout', ['Accept' => 'application/json'])->assertConflict();
        $this->submit($this->input, (string) Str::uuid())->assertConflict();
        $this->withSession(['commerce.cart' => null]);
        $this->product->prices()->create(['version' => 2, 'amount_minor' => 20000, 'currency' => 'PHP']);
        $this->submit($this->input, (string) Str::uuid())->assertConflict();
        $this->post('/checkout', [...$this->input, 'accept_terms' => '1', 'operation_id' => (string) Str::uuid(), '_token' => 'csrf'], ['Origin' => 'https://app.example.test'])->assertRedirect('/checkout')->assertSessionHasInput('brief.goal', $this->input['brief']['goal'])->assertSessionMissing('_old_input.accept_terms');
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_invalid_native_array_values_can_be_corrected_without_breaking_the_review_page(): void
    {
        $this->actingAs($this->customer)->withSession(['_token' => 'csrf']);
        $bad = [...$this->input, 'accept_terms' => '1', 'operation_id' => ['not-a-uuid'], '_token' => 'csrf'];
        $bad['brief']['goal'] = ['not-a-string'];
        $this->from('/checkout')->post('/checkout', $bad, ['Origin' => 'https://app.example.test'])->assertRedirect('/checkout')->assertSessionHasErrors();
        // Carry the real session identity across the synthetic redirect. With
        // JSON sessions a fresh test request without its cookie can remarshal
        // an already-loaded error bag instead of reading the stored payload.
        $r = $this->withCookie(config('session.cookie'), session()->getId())->get('/checkout')->assertOk();
        $r->assertSee('Check the details below.')->assertSee('Send request for review');
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_oversized_submission_is_rejected_without_persisting_an_order_or_form_draft(): void
    {
        $this->actingAs($this->customer)->withSession(['_token' => 'csrf']);
        $input = $this->input;
        $input['brief']['scope'] = str_repeat('x', 262145);
        $this->submit($input, (string) Str::uuid())->assertStatus(413)->assertHeader('Cache-Control', 'no-store, private');
        $this->assertDatabaseCount('orders', 0);
        $this->assertFalse(session()->has('commerce.checkout_draft'));
    }

    private function submit(array $input, string $operation): TestResponse
    {
        return $this->postJson('/api/v1/orders', $input, ['Origin' => 'https://app.example.test', 'X-CSRF-TOKEN' => 'csrf', 'Idempotency-Key' => $operation]);
    }
}
