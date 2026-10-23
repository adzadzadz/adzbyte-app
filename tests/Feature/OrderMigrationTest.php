<?php

namespace Tests\Feature;

use App\Actions\Carts\CartActions;
use App\Actions\Orders\CommercePolicyActions;
use App\Actions\Orders\OrderActions;
use App\Models\OrderEvent;
use App\Models\Product;
use App\Models\User;
use App\Services\Carts\CartPrincipal;
use Database\Seeders\RoleSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\Support\UsesIsolatedCatalogDatabase;
use Tests\TestCase;

class OrderMigrationTest extends TestCase
{
    use UsesIsolatedCatalogDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpCatalogDatabase(migrate: false);
        $paths = array_values(array_filter(glob(database_path('migrations/*.php')), fn ($path) => basename($path) < '2026_10_18_060000_create_order_domain.php'));
        $this->artisan('migrate', ['--path' => $paths, '--realpath' => true, '--force' => true])->assertSuccessful();
    }

    protected function tearDown(): void
    {
        $this->tearDownCatalogDatabase();
        parent::tearDown();
    }

    public function test_populated_predecessor_upgrade_empty_reversal_and_reapply_preserve_cart_and_identity(): void
    {
        $user = User::factory()->create();
        $principal = CartPrincipal::guest(bin2hex(random_bytes(32)));
        $cart = app(CartActions::class)->create($principal, (string) Str::uuid());
        $before = DB::table('carts')->first();
        $migration = require database_path('migrations/2026_10_18_060000_create_order_domain.php');
        $migration->up();
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('commerce_settings', 1);
        $migration->down();
        $this->assertFalse(Schema::hasTable('orders'));
        $this->assertEquals($before, DB::table('carts')->first());
        $this->assertSame($user->id, $user->fresh()->id);
        $migration->up();
        $this->assertSame($cart['public_id'], app(CartActions::class)->read($principal, $cart['public_id'])['public_id']);
        $this->assertDatabaseCount('cart_changes', 1);
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_populated_order_reversal_and_database_constraints_preserve_financial_history(): void
    {
        $migration = require database_path('migrations/2026_10_18_060000_create_order_domain.php');
        $migration->up();
        $this->seed(RoleSeeder::class);
        $customer = User::factory()->create();
        $customer->assignRole('customer');
        $product = Product::factory()->published()->create();
        $product->prices()->create(['version' => 1, 'amount_minor' => 12500, 'currency' => 'PHP']);
        $product->termsVersions()->create(['version' => 1, 'content' => ['scope' => 'Synthetic retained scope']]);
        $product->is_available = true;
        $product->save();
        $guest = CartPrincipal::guest(bin2hex(random_bytes(32)));
        $carts = app(CartActions::class);
        $cart = $carts->create($guest, (string) Str::uuid());
        $carts->add($guest, $cart['public_id'], $product->public_id, (string) Str::uuid());
        $preview = $carts->mergePreview($customer, $guest, $cart['public_id']);
        $carts->attach($customer, $guest, $cart['public_id'], (string) Str::uuid(), $preview['fingerprint']);
        $actions = app(OrderActions::class);
        $input = ['brief' => ['goal' => 'Synthetic goal', 'scope' => 'Synthetic scope'], 'billing' => ['name' => 'Synthetic buyer', 'email' => 'buyer@example.test', 'country' => 'PH'], 'accept_terms' => true, 'fingerprint' => $actions->review($customer)['fingerprint']];
        $order = Event::fakeFor(fn () => $actions->submit($customer, $input, (string) Str::uuid()), ['eloquent.created: '.OrderEvent::class]);
        $before = $actions->read($customer, $order->public_id)->toArray();
        try {
            $migration->down();
            $this->fail('Accepted order was destroyed');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('Retain accepted commerce records', $exception->getMessage());
        }
        try {
            DB::table('orders')->where('id', $order->id)->delete();
            $this->fail('Order foreign keys allowed deletion');
        } catch (QueryException) {
            $this->assertTrue(true);
        }
        $duplicate = $order->getAttributes();
        unset($duplicate['id']);
        $duplicate['public_id'] = (string) Str::ulid();
        $duplicate['creation_operation_id'] = (string) Str::uuid();
        try {
            DB::table('orders')->insert($duplicate);
            $this->fail('Duplicate source revision accepted');
        } catch (QueryException) {
            $this->assertTrue(true);
        }
        $this->assertEquals($before, $actions->read($customer, $order->public_id)->toArray());
        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('order_lines', 1);
        $this->assertDatabaseCount('order_events', 1);
    }

    public function test_populated_policy_reversal_fails_before_any_ddl_and_leaves_accepted_content_intact(): void
    {
        $migration = require database_path('migrations/2026_10_18_060000_create_order_domain.php');
        $migration->up();
        $policy = app(CommercePolicyActions::class)->current();
        try {
            $migration->down();
            $this->fail('Accepted commerce policy was destroyed');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('Retain accepted commerce records', $exception->getMessage());
        }
        foreach (['orders', 'order_revisions', 'order_lines', 'order_events', 'commerce_settings', 'commerce_policy_versions'] as $table) {
            $this->assertTrue(Schema::hasTable($table));
        }
        $this->assertSame($policy->content_hash, $policy->fresh()->content_hash);
        $this->assertDatabaseCount('commerce_policy_versions', 1);
    }
}
