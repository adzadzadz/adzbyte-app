<?php

namespace Tests\Feature;

use App\Actions\Carts\CartActions;
use App\Actions\Carts\CartHandoffActions;
use App\Actions\Catalog\CatalogActions;
use App\Enums\UserRole;
use App\Models\Cart;
use App\Models\CustomerCart;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\User;
use App\Services\Carts\CartPrincipal;
use App\Services\Carts\StorefrontCredential;
use Database\Seeders\RoleSeeder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class CartOwnershipTest extends TestCase
{
    use RefreshDatabase;

    private CartActions $actions;

    private User $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->actions = app(CartActions::class);
        $this->customer = User::factory()->create();
        $this->customer->assignRole(UserRole::Customer->value);
    }

    public function test_first_claim_is_owned_audited_retry_safe_and_revokes_guest_access(): void
    {
        [$guest, $reference, $creation] = $this->guest([$this->offer()]);
        $preview = $this->actions->mergePreview($this->customer, $guest, $reference);
        $this->assertNull($preview['target']);
        $this->assertSame([], $preview['groups']);
        $operation = (string) Str::uuid();
        $owned = $this->actions->attach($this->customer, $guest, $reference, $operation, $preview['fingerprint']);
        $this->assertSame($reference, $owned['public_id']);
        $this->assertCount(1, $owned['lines']);
        $retry = $this->actions->attach($this->customer, $guest, $reference, $operation, $preview['fingerprint']);
        $this->assertSame($owned['public_id'], $retry['public_id']);
        $this->assertSame($owned['revision'], $retry['revision']);
        $this->assertCount(1, $retry['lines']);
        $this->assertSame($this->customer->id, Cart::sole()->user_id);
        $this->assertNull(Cart::sole()->credential_hash);
        $this->assertDatabaseCount('customer_carts', 1);
        $this->assertDatabaseHas('cart_changes', ['cart_id' => Cart::sole()->id, 'actor_id' => $this->customer->id, 'action' => 'attach']);
        $this->assertSame($reference, $this->actions->owned($this->customer)['public_id']);
        $this->assertTrue($this->actions->retired($guest, $creation));
        $this->missing(fn () => $this->actions->read($guest, $reference));
        $this->missing(fn () => $this->actions->resolveCreation($guest, $creation));
        $this->missing(fn () => $this->actions->add($guest, $reference, $this->offer()->public_id, (string) Str::uuid()));
    }

    public function test_nonconflicting_merge_deduplicates_preserves_quotes_and_retains_source_history(): void
    {
        $same = $this->offer();
        $regular = $this->offer();
        [$first, $target] = $this->guest([$same]);
        $this->claim($first, $target);
        $staff = User::factory()->create();
        $staff->assignRole(UserRole::SuperAdmin->value);
        app(CatalogActions::class)->revisePrice($staff, $same, ['amount_minor' => 15500, 'currency' => 'PHP'], (string) Str::uuid());
        [$second, $source, $creation] = $this->guest([$same, $regular]);
        $result = $this->claim($second, $source);
        $this->assertSame($target, $result['public_id']);
        $this->assertCount(2, $result['lines']);
        $this->assertTrue($result['requires_review']);
        $this->assertSame(1, collect($result['lines'])->first(fn ($line) => $line['product']->is($same))['quoted_price_version']);
        $retired = Cart::where('public_id', $source)->sole();
        $this->assertSame(Cart::where('public_id', $target)->sole()->id, $retired->merged_into_cart_id);
        $this->assertSame(0, $retired->items()->count());
        $this->assertNotSame($second->credentialHash(), $retired->credential_hash);
        $this->assertNotNull($retired->changes()->where('action', 'merge')->sole()->before);
        $this->assertTrue($this->actions->retired($second, $creation));
        $this->assertDatabaseCount('customer_carts', 1);
        $this->missing(fn () => $this->actions->read($second, $source));
    }

    public function test_restricted_merge_requires_an_explicit_choice_and_preserves_regular_categories(): void
    {
        $category = ProductCategory::create(['name' => 'Restricted', 'limit_one_per_cart' => true]);
        $one = $this->offer($category);
        $two = $this->offer($category);
        $regular = $this->offer();
        [$first, $target] = $this->guest([$one]);
        $this->claim($first, $target);
        [$second, $source] = $this->guest([$two, $regular]);
        $state = $this->actions->mergePreview($this->customer, $second, $source);
        $this->assertCount(1, $state['groups']);
        try {
            $this->actions->attach($this->customer, $second, $source, (string) Str::uuid(), $state['fingerprint']);
            $this->fail('Conflicting merge silently chose a service.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('choices', $exception->errors());
        }
        $this->assertCount(2, $this->actions->read($second, $source)['lines']);
        $this->assertCount(1, $this->actions->owned($this->customer)['lines']);
        $chosen = $this->actions->attach($this->customer, $second, $source, (string) Str::uuid(), $state['fingerprint'], [$state['groups'][0]['key'] => $two->public_id]);
        $this->assertEqualsCanonicalizing([$two->public_id, $regular->public_id], array_map(fn ($line) => $line['product']->public_id, $chosen['lines']));
        $this->assertTrue($chosen['selection_valid']);
    }

    public function test_changed_catalog_or_cart_state_rejects_stale_merge_without_losing_either_cart(): void
    {
        $product = $this->offer();
        [$guest, $source] = $this->guest([$product]);
        $state = $this->actions->mergePreview($this->customer, $guest, $source);
        $staff = User::factory()->create();
        $staff->assignRole(UserRole::SuperAdmin->value);
        app(CatalogActions::class)->setAvailability($staff, $product, false, (string) Str::uuid());
        $this->expectStatus(409, fn () => $this->actions->attach($this->customer, $guest, $source, (string) Str::uuid(), $state['fingerprint']));
        $this->assertDatabaseCount('customer_carts', 0);
        $this->assertCount(1, $this->actions->read($guest, $source)['lines']);
        $fresh = $this->claim($guest, $source);
        $this->assertFalse($fresh['selection_valid']);
        $this->assertCount(1, $fresh['lines']); // Availability blocks purchase, never expires saved items.
    }

    public function test_unverified_staff_role_revocation_and_gate_override_cannot_claim_another_cart(): void
    {
        [$guest, $source] = $this->guest([$this->offer()]);
        $unverified = User::factory()->unverified()->create();
        $unverified->assignRole(UserRole::Customer->value);
        $staff = User::factory()->create();
        $staff->assignRole(UserRole::SuperAdmin->value);
        Gate::before(fn (User $user) => $user->hasRole(UserRole::SuperAdmin->value) ? true : null);
        foreach ([$unverified, $staff] as $actor) {
            $this->expectStatus(403, fn () => $this->actions->mergePreview($actor, $guest, $source));
        }
        $wrong = CartPrincipal::guest(bin2hex(random_bytes(32)));
        $this->missing(fn () => $this->actions->mergePreview($this->customer, $wrong, $source));
        $this->customer->removeRole(UserRole::Customer->value);
        $this->expectStatus(403, fn () => $this->actions->mergePreview($this->customer, $guest, $source));
        $this->assertDatabaseCount('customer_carts', 0);
        $this->assertNull(Cart::sole()->user_id);
    }

    public function test_claim_receipt_never_reauthorizes_another_identity_or_changed_input(): void
    {
        [$guest, $source] = $this->guest([$this->offer()]);
        $state = $this->actions->mergePreview($this->customer, $guest, $source);
        $operation = (string) Str::uuid();
        $this->actions->attach($this->customer, $guest, $source, $operation, $state['fingerprint']);
        $other = User::factory()->create();
        $other->assignRole(UserRole::Customer->value);
        $this->missing(fn () => $this->actions->attach($other, $guest, $source, $operation, $state['fingerprint']));
        $this->missing(fn () => $this->actions->attach($this->customer, CartPrincipal::guest(bin2hex(random_bytes(32))), $source, $operation, $state['fingerprint']));
        try {
            $this->actions->attach($this->customer, $guest, $source, $operation, str_repeat('a', 64));
            $this->fail('Changed claim reused a completed UUID.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('operation_id', $exception->errors());
        }
        $this->assertDatabaseCount('customer_carts', 1);
    }

    public function test_outstanding_handoff_is_invalidated_and_retirement_is_proof_scoped(): void
    {
        [$guest, $source, $creation] = $this->guest([$this->offer()]);
        $issuer = User::factory()->create()->createToken('storefront:ownership-test', StorefrontCredential::abilities())->accessToken;
        $link = app(CartHandoffActions::class)->mint($guest, $source, $issuer, (string) Str::uuid());
        $this->claim($guest, $source);
        $this->expectStatus(410, fn () => app(CartHandoffActions::class)->consume(explode('#', $link['url'])[1]));
        $this->expectStatus(404, fn () => $this->actions->retired(CartPrincipal::guest(bin2hex(random_bytes(32))), $creation));
        $this->expectStatus(404, fn () => $this->actions->retired($guest, (string) Str::uuid()));
        $this->assertTrue($this->actions->retired($guest, $creation));
        $this->assertSame($this->customer->id, CustomerCart::sole()->user_id);
    }

    private function guest(array $products): array
    {
        $guest = CartPrincipal::guest(bin2hex(random_bytes(32)));
        $operation = (string) Str::uuid();
        $reference = $this->actions->create($guest, $operation)['public_id'];
        foreach ($products as $product) {
            $this->actions->add($guest, $reference, $product->public_id, (string) Str::uuid());
        }

        return [$guest, $reference, $operation];
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

    private function claim(CartPrincipal $guest, string $reference): array
    {
        $state = $this->actions->mergePreview($this->customer, $guest, $reference);

        return $this->actions->attach($this->customer, $guest, $reference, (string) Str::uuid(), $state['fingerprint']);
    }

    private function missing(callable $action): void
    {
        try {
            $action();
            $this->fail('Foreign or revoked authority was accepted.');
        } catch (ModelNotFoundException) {
            $this->addToAssertionCount(1);
        }
    }

    private function expectStatus(int $status, callable $action): void
    {
        try {
            $action();
            $this->fail('Expected a denied transition.');
        } catch (HttpException $exception) {
            $this->assertSame($status, $exception->getStatusCode());
        }
    }
}
