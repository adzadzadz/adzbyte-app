<?php

namespace Tests\Feature;

use App\Actions\Carts\CartActions;
use App\Actions\Orders\OrderActions;
use App\Actions\Orders\OrderReviewActions;
use App\Enums\OrderCapability;
use App\Models\OrderEvent;
use App\Models\OrderNotificationDelivery;
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

class OrderNotificationMigrationTest extends TestCase
{
    use UsesIsolatedCatalogDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpCatalogDatabase(migrate: false);
        $paths = array_values(array_filter(glob(database_path('migrations/*.php')), fn ($path) => basename($path) < '2026_10_18_080000_create_order_notification_deliveries.php'));
        $this->artisan('migrate', ['--path' => $paths, '--realpath' => true, '--force' => true])->assertSuccessful();
        $this->installVisibilitySchemaForLegacyFixture();
        $this->seed(RoleSeeder::class);
        config(['queue.default' => 'database']);
    }

    protected function tearDown(): void
    {
        $this->tearDownCatalogDatabase();
        parent::tearDown();
    }

    public function test_populated_upgrade_does_not_broadcast_history_and_preserves_recovery_receipts(): void
    {
        $customer = $this->customerCart();
        $a = app(OrderActions::class);
        $input = ['brief' => ['goal' => 'Synthetic goal', 'scope' => 'Synthetic scope'], 'billing' => ['name' => 'Synthetic buyer', 'email' => 'buyer@example.test', 'country' => 'PH'], 'accept_terms' => true, 'fingerprint' => $a->review($customer)['fingerprint']];
        // Emulate the predecessor's event behavior, retaining every domain model guard.
        $order = Event::fakeFor(fn () => $a->submit($customer, $input, (string) Str::uuid()), ['eloquent.created: '.OrderEvent::class]);
        $before = $order->currentRevision->toArray();
        $migration = require database_path('migrations/2026_10_18_080000_create_order_notification_deliveries.php');
        $migration->up();
        $this->assertDatabaseCount('order_notification_deliveries', 0);
        $this->assertDatabaseCount('jobs', 0);
        $migration->down();
        $this->assertFalse(Schema::hasTable('order_notification_deliveries'));
        $this->assertEquals($before, $a->read($customer, $order->public_id)->currentRevision->toArray());
        $migration->up();
        $admin = User::factory()->create();
        $admin->assignRole('administrator');
        $admin->givePermissionTo(array_column(OrderCapability::cases(), 'value'));
        app(OrderReviewActions::class)->staff($admin, $order->public_id, 'clarify', ['expected_revision' => 1, 'message' => 'Synthetic clarification'], (string) Str::uuid());
        $this->assertDatabaseCount('order_notification_deliveries', 1);
        $this->assertDatabaseCount('jobs', 1);
        try {
            $migration->down();
            $this->fail('Delivery history was destroyed');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('Retain notification delivery history', $e->getMessage());
        }
        $this->assertDatabaseCount('order_notification_deliveries', 1);
        $this->assertEquals($before, $a->read($customer, $order->public_id)->currentRevision->toArray());
        $row = OrderNotificationDelivery::sole()->getAttributes();
        unset($row['id']);
        try {
            DB::table('order_notification_deliveries')->insert($row);
            $this->fail('Duplicate event recipient allowed');
        } catch (QueryException) {
            $this->assertTrue(true);
        }
        try {
            DB::table('order_events')->where('id', $row['event_id'])->delete();
            $this->fail('Referenced event was deleted');
        } catch (QueryException) {
            $this->assertTrue(true);
        }
    }

    private function customerCart(): User
    {
        $customer = User::factory()->create();
        $customer->assignRole('customer');
        $product = Product::factory()->published()->create();
        $product->prices()->create(['version' => 1, 'amount_minor' => 12500, 'currency' => 'PHP']);
        $product->termsVersions()->create(['version' => 1, 'content' => ['scope' => 'Synthetic concurrent scope']]);
        $product->is_available = true;
        $product->save();
        $guest = CartPrincipal::guest(bin2hex(random_bytes(32)));
        $carts = app(CartActions::class);
        $cart = $carts->create($guest, (string) Str::uuid());
        $carts->add($guest, $cart['public_id'], $product->public_id, (string) Str::uuid());
        $preview = $carts->mergePreview($customer, $guest, $cart['public_id']);
        $carts->attach($customer, $guest, $cart['public_id'], (string) Str::uuid(), $preview['fingerprint']);

        return $customer;
    }
}
