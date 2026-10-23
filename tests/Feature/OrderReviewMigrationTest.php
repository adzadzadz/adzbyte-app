<?php

namespace Tests\Feature;

use App\Actions\Carts\CartActions;
use App\Actions\Orders\OrderActions;
use App\Actions\Orders\OrderReviewActions;
use App\Enums\OrderCapability;
use App\Models\OrderEvent;
use App\Models\OrderMessage;
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

class OrderReviewMigrationTest extends TestCase
{
    use UsesIsolatedCatalogDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpCatalogDatabase(migrate: false);
        $paths = array_values(array_filter(glob(database_path('migrations/*.php')), fn ($path) => basename($path) < '2026_10_18_070000_create_order_review.php'));
        $this->artisan('migrate', ['--path' => $paths, '--realpath' => true, '--force' => true])->assertSuccessful();
        $this->seed(RoleSeeder::class);
    }

    protected function tearDown(): void
    {
        $this->tearDownCatalogDatabase();
        parent::tearDown();
    }

    public function test_populated_upgrade_empty_reverse_reapply_and_fail_closed_review_recovery(): void
    {
        $customer = $this->customerCart();
        $a = app(OrderActions::class);
        $input = ['brief' => ['goal' => 'Synthetic goal', 'scope' => 'Synthetic scope'], 'billing' => ['name' => 'Synthetic buyer', 'email' => 'buyer@example.test', 'country' => 'PH'], 'accept_terms' => true, 'fingerprint' => $a->review($customer)['fingerprint']];
        $order = Event::fakeFor(fn () => $a->submit($customer, $input, (string) Str::uuid()), ['eloquent.created: '.OrderEvent::class]);
        $before = $a->read($customer, $order->public_id)->currentRevision->toArray();
        $migration = require database_path('migrations/2026_10_18_070000_create_order_review.php');
        $migration->up();
        $this->assertDatabaseCount('order_proposals', 0);
        $this->assertDatabaseCount('order_messages', 0);
        $this->assertEquals($before, $a->read($customer, $order->public_id)->currentRevision->toArray());
        $migration->down();
        $this->assertFalse(Schema::hasTable('order_messages'));
        $this->assertFalse(Schema::hasColumn('orders', 'pending_proposal_id'));
        $this->assertEquals($before, $a->read($customer, $order->public_id)->currentRevision->toArray());
        $migration->up();
        $admin = User::factory()->create();
        $admin->assignRole('administrator');
        $admin->givePermissionTo(array_column(OrderCapability::cases(), 'value'));
        $order = Event::fakeFor(fn () => app(OrderReviewActions::class)->staff($admin, $order->public_id, 'propose', ['expected_revision' => 1, 'message' => 'Synthetic proposal'], (string) Str::uuid()), ['eloquent.created: '.OrderEvent::class]);
        try {
            $migration->down();
            $this->fail('Destructive reversal succeeded');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('Retain review history', $e->getMessage());
        }
        $this->assertTrue(Schema::hasTable('order_proposals'));
        $this->assertDatabaseCount('order_messages', 1);
        $this->assertDatabaseCount('order_proposals', 1);
        $this->assertEquals($before, $a->read($customer, $order->public_id)->currentRevision->toArray());
        $message = OrderMessage::sole()->getAttributes();
        unset($message['id']);
        $message['public_id'] = (string) Str::ulid();
        try {
            DB::table('order_messages')->insert($message);
            $this->fail('Duplicate event message allowed');
        } catch (QueryException) {
            $this->assertTrue(true);
        }
        try {
            DB::table('order_proposals')->where('id', $order->pending_proposal_id)->delete();
            $this->fail('Pending proposal deletion allowed');
        } catch (QueryException) {
            $this->assertTrue(true);
        }
        $this->assertDatabaseCount('order_messages', 1);
        $this->assertDatabaseCount('cart_items', 1);
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
