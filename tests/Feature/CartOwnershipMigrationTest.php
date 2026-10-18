<?php

namespace Tests\Feature;

use App\Actions\Carts\CartActions;
use App\Enums\UserRole;
use App\Models\Cart;
use App\Models\Product;
use App\Models\User;
use App\Services\Carts\CartPrincipal;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\Support\UsesIsolatedCatalogDatabase;
use Tests\TestCase;

class CartOwnershipMigrationTest extends TestCase
{
    use UsesIsolatedCatalogDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpCatalogDatabase(migrate: false);
        $paths = array_values(array_filter(glob(database_path('migrations/*.php')), fn ($path) => basename($path) < '2026_10_18_050000_add_customer_cart_ownership.php'));
        $this->artisan('migrate', ['--path' => $paths, '--realpath' => true, '--force' => true])->assertSuccessful();
        $this->seed(RoleSeeder::class);
    }

    protected function tearDown(): void
    {
        $this->tearDownCatalogDatabase();
        parent::tearDown();
    }

    public function test_populated_upgrade_reversal_reapply_preserves_ownership_history_and_revocation(): void
    {
        $actor = User::factory()->create();
        $actor->assignRole(UserRole::Customer->value);
        $actions = app(CartActions::class);
        $oldOwned = $actions->create(CartPrincipal::customer($actor), (string) Str::uuid());
        $product = Product::factory()->published()->create();
        $product->prices()->create(['version' => 1, 'amount_minor' => 12500, 'currency' => 'PHP']);
        $product->termsVersions()->create(['version' => 1, 'content' => ['scope' => 'Synthetic upgrade scope']]);
        $product->is_available = true;
        $product->save();
        $guest = CartPrincipal::guest(bin2hex(random_bytes(32)));
        $creation = (string) Str::uuid();
        $source = $actions->create($guest, $creation);
        $actions->add($guest, $source['public_id'], $product->public_id, (string) Str::uuid());
        $this->applyOwnership();
        $this->assertDatabaseCount('customer_carts', 1);
        $this->assertSame($oldOwned['public_id'], $actions->owned($actor)['public_id']);
        $preview = $actions->mergePreview($actor, $guest, $source['public_id']);
        $operation = (string) Str::uuid();
        $result = $actions->attach($actor, $guest, $source['public_id'], $operation, $preview['fingerprint']);
        $this->assertCount(1, $result['lines']);
        $retired = Cart::where('public_id', $source['public_id'])->sole();
        $rotated = $retired->credential_hash;
        $changes = $retired->changes()->count();
        $this->artisan('migrate:rollback', ['--step' => 1, '--force' => true])->assertSuccessful();
        $this->assertFalse(Schema::hasTable('customer_carts'));
        $this->assertFalse(Schema::hasColumn('carts', 'merged_into_cart_id'));
        $this->assertDatabaseCount('carts', 2);
        $this->assertDatabaseCount('cart_items', 1);
        $this->assertSame($rotated, $retired->fresh()->credential_hash);
        $this->assertSame($changes, $retired->changes()->count());
        $this->assertCount(1, $actions->read(CartPrincipal::customer($actor), $oldOwned['public_id'])['lines']);
        $this->applyOwnership();
        $this->assertDatabaseCount('customer_carts', 1);
        $this->assertNotNull($retired->fresh()->merged_into_cart_id);
        $this->assertSame($rotated, $retired->fresh()->credential_hash);
        $this->assertTrue($actions->retired($guest, $creation));
        $retry = $actions->attach($actor, $guest, $source['public_id'], $operation, $preview['fingerprint']);
        $this->assertSame($oldOwned['public_id'], $retry['public_id']);
        $this->assertCount(1, $retry['lines']);
        $this->assertSame($changes, $retired->changes()->count());
        $this->assertSame($actor->id, $actor->fresh()->id);
        $this->assertSame($product->name, $product->fresh()->name);
    }

    public function test_ambiguous_existing_owned_carts_are_rejected_before_schema_changes(): void
    {
        $actor = User::factory()->create();
        $actor->assignRole(UserRole::Customer->value);
        foreach (range(1, 2) as $unused) {
            app(CartActions::class)->create(CartPrincipal::customer($actor), (string) Str::uuid());
        }
        try {
            (require database_path('migrations/2026_10_18_050000_add_customer_cart_ownership.php'))->up();
            $this->fail('Ambiguous owned carts were silently chosen.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('multiple existing customer carts', $exception->getMessage());
        }
        $this->assertFalse(Schema::hasTable('customer_carts'));
        $this->assertFalse(Schema::hasColumn('carts', 'merged_into_cart_id'));
        $this->assertDatabaseCount('carts', 2);
        $this->assertSame(2, Cart::where('user_id', $actor->id)->count());
    }

    private function applyOwnership(): void
    {
        $this->artisan('migrate', ['--path' => database_path('migrations/2026_10_18_050000_add_customer_cart_ownership.php'), '--realpath' => true, '--force' => true])->assertSuccessful();
    }
}
