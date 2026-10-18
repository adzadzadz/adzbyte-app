<?php

namespace Tests\Feature;

use App\Actions\Carts\CartActions;
use App\Actions\Orders\CommercePolicyActions;
use App\Actions\Orders\OrderActions;
use App\Models\CommercePolicyVersion;
use App\Models\OrderEvent;
use App\Models\OrderLine;
use App\Models\OrderRevision;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\User;
use App\Services\Carts\CartPrincipal;
use App\Services\Orders\CommerceRules;
use App\Services\Orders\OrderLifecycle;
use Database\Seeders\RoleSeeder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class OrderDomainTest extends TestCase
{
    use RefreshDatabase;

    private User $customer;

    private User $admin;

    private Product $product;

    private array $input;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        Permission::findOrCreate('Manage:CommerceSettings', 'web');
        $this->admin = User::factory()->create();
        $this->admin->assignRole('administrator');
        $this->admin->givePermissionTo('Manage:CommerceSettings');
        $this->customer = User::factory()->create();
        $this->customer->assignRole('customer');
        $this->product = Product::factory()->published()->create(['name' => 'Synthetic accepted service']);
        $this->product->prices()->create(['version' => 1, 'amount_minor' => 12500, 'currency' => 'PHP']);
        $this->product->termsVersions()->create(['version' => 1, 'content' => ['scope' => 'Original synthetic scope']]);
        $this->product->is_available = true;
        $this->product->save();
        $guest = CartPrincipal::guest(bin2hex(random_bytes(32)));
        $carts = app(CartActions::class);
        $cart = $carts->create($guest, (string) Str::uuid());
        $carts->add($guest, $cart['public_id'], $this->product->public_id, (string) Str::uuid());
        $preview = $carts->mergePreview($this->customer, $guest, $cart['public_id']);
        $carts->attach($this->customer, $guest, $cart['public_id'], (string) Str::uuid(), $preview['fingerprint']);
        $this->input = ['brief' => ['goal' => 'Synthetic goal', 'scope' => 'Synthetic requested scope'], 'billing' => ['name' => 'Synthetic buyer', 'email' => 'buyer@example.test', 'country' => 'PH'], 'accept_terms' => true,
            'fingerprint' => app(OrderActions::class)->review($this->customer)['fingerprint']];
    }

    public function test_verified_owned_submission_captures_immutable_commercial_and_customer_snapshots(): void
    {
        $order = app(OrderActions::class)->submit($this->customer, $this->input, (string) Str::uuid());
        $this->assertSame($this->customer->id, $order->user_id);
        $this->assertSame(OrderLifecycle::INITIAL, $order->only(array_keys(OrderLifecycle::INITIAL)));
        $revision = $order->currentRevision;
        $this->assertSame('12500', $revision->total_minor);
        $this->assertSame('0', $revision->tax_minor);
        $this->assertSame('unconfigured', $revision->policy_snapshot['tax_mode']);
        $this->assertSame('order_summary', $revision->policy_snapshot['document_kind']);
        $this->assertSame($this->input['brief'], $revision->brief);
        $this->assertTrue($revision->accepted_at->isPast());
        $this->assertSame(1, $revision->lines->sole()->snapshot['quantity']);
        $this->assertSame('Original synthetic scope', $revision->lines->sole()->snapshot['terms']['scope']);
        $this->assertDatabaseCount('cart_items', 1);
        $this->assertSame('submitted', $order->events()->sole()->action);
        $this->assertSame($this->customer->id, $order->events()->sole()->actor_id);
        $before = $revision->toArray();
        $this->product->name = 'Changed synthetic name';
        $this->product->save();
        $this->product->prices()->create(['version' => 2, 'amount_minor' => 25000, 'currency' => 'PHP']);
        $this->product->termsVersions()->create(['version' => 2, 'content' => ['scope' => 'Changed scope']]);
        $content = CommerceRules::defaults();
        $content['tax_mode'] = 'exclusive';
        $content['tax_basis_points'] = 1200;
        app(CommercePolicyActions::class)->publish($this->admin, $content, (string) Str::uuid());
        $this->customer->name = 'Changed customer name';
        $this->customer->save();
        $this->assertEquals($before, app(OrderActions::class)->read($this->customer, $order->public_id)->currentRevision->toArray());
    }

    public function test_original_and_alias_uuid_receipts_survive_live_changes_and_cannot_be_reused(): void
    {
        $actions = app(OrderActions::class);
        $uuid = (string) Str::uuid();
        $order = $actions->submit($this->customer, $this->input, $uuid);
        $alias = (string) Str::uuid();
        $this->assertSame($order->id, $actions->submit($this->customer, $this->input, $alias)->id);
        $this->product->is_available = false;
        $this->product->save();
        $this->assertSame($order->id, $actions->submit($this->customer, $this->input, $uuid)->id);
        $this->assertSame($order->id, $actions->submit($this->customer, $this->input, $alias)->id);
        foreach ([$uuid, $alias, (string) Str::uuid()] as $operation) {
            $changed = $this->input;
            $changed['brief']['goal'] = 'Different request';
            $this->denied(409, fn () => $actions->submit($this->customer, $changed, $operation));
        }
        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('order_events', 2);
    }

    public function test_changed_price_terms_policy_and_unavailable_cart_reject_stale_acceptance_atomically(): void
    {
        $this->product->prices()->create(['version' => 2, 'amount_minor' => 20000, 'currency' => 'PHP']);
        $this->denied(409, fn () => app(OrderActions::class)->submit($this->customer, $this->input, (string) Str::uuid()));
        $review = app(OrderActions::class)->review($this->customer);
        $this->assertFalse($review['cart']['selection_valid']);
        $this->assertTrue($review['cart']['requires_review']);
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('order_events', 0);
        $this->assertDatabaseCount('order_lines', 0);
    }

    public function test_new_policy_invalidates_review_and_is_configurable_without_rewriting_history(): void
    {
        $content = CommerceRules::defaults();
        $content['tax_mode'] = 'inclusive';
        $content['tax_basis_points'] = 1200;
        $content['required_billing'][] = 'address';
        $uuid = (string) Str::uuid();
        $policy = app(CommercePolicyActions::class)->publish($this->admin, $content, $uuid);
        $this->assertSame($policy->id, app(CommercePolicyActions::class)->publish($this->admin, $content, $uuid)->id);
        $this->expectValidation(fn () => app(OrderActions::class)->submit($this->customer, $this->input, (string) Str::uuid()));
        $this->input['billing']['address'] = 'Synthetic address';
        $this->denied(409, fn () => app(OrderActions::class)->submit($this->customer, $this->input, (string) Str::uuid()));
        $this->input['fingerprint'] = app(OrderActions::class)->review($this->customer)['fingerprint'];
        $order = app(OrderActions::class)->submit($this->customer, $this->input, (string) Str::uuid());
        $this->assertSame('1339', $order->currentRevision->tax_minor);
        $this->assertSame('12500', $order->currentRevision->total_minor);
        $content['approval_days'] = 15;
        $this->denied(409, fn () => app(CommercePolicyActions::class)->publish($this->admin, $content, $uuid));
    }

    public function test_foreign_unverified_revoked_staff_and_gate_override_cannot_submit_or_read_as_customer(): void
    {
        $order = app(OrderActions::class)->submit($this->customer, $this->input, (string) Str::uuid());
        $other = User::factory()->create();
        $other->assignRole('customer');
        Gate::before(fn () => true);
        try {
            app(OrderActions::class)->read($other, $order->public_id);
            $this->fail('Cross-owner read allowed');
        } catch (ModelNotFoundException) {
            $this->assertTrue(true);
        }
        $this->denied(403, fn () => app(OrderActions::class)->submit($this->admin, $this->input, (string) Str::uuid()));
        $this->customer->email_verified_at = null;
        $this->customer->save();
        $this->denied(403, fn () => app(OrderActions::class)->submit($this->customer, $this->input, (string) Str::uuid()));
        $this->customer->email_verified_at = now();
        $this->customer->save();
        $this->customer->removeRole('customer');
        $this->denied(403, fn () => app(OrderActions::class)->read($this->customer, $order->public_id));
        $this->assertDatabaseCount('orders', 1);
    }

    public function test_strict_inputs_reject_owner_amount_role_and_unaccepted_terms(): void
    {
        foreach (['user_id' => $this->admin->id, 'amount_minor' => 1, 'approval_status' => 'approved'] as $key => $value) {
            $this->expectValidation(fn () => app(OrderActions::class)->submit($this->customer, [...$this->input, $key => $value], (string) Str::uuid()));
        }
        $this->denied(422, fn () => app(OrderActions::class)->submit($this->customer, [...$this->input, 'accept_terms' => '1'], (string) Str::uuid()));
        $this->expectValidation(fn () => app(OrderActions::class)->submit($this->customer, [...$this->input, 'billing' => []], (string) Str::uuid()));
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_accepted_records_and_identity_cannot_be_edited_or_deleted(): void
    {
        $order = app(OrderActions::class)->submit($this->customer, $this->input, (string) Str::uuid());
        foreach ([CommercePolicyVersion::sole(), OrderRevision::sole(), OrderLine::sole(), OrderEvent::sole()] as $record) {
            $this->expectValidation(fn () => $record->delete());
            $this->expectValidation(fn () => $record->forceFill(['created_at' => now()->subDay()])->save());
        }
        $this->expectValidation(fn () => $order->delete());
        $this->expectValidation(fn () => $order->forceFill(['user_id' => $this->admin->id])->save());
        $this->assertDatabaseCount('order_lines', 1);
    }

    public function test_settings_publication_requires_verified_admin_and_capability(): void
    {
        $this->denied(403, fn () => app(CommercePolicyActions::class)->publish($this->customer, CommerceRules::defaults(), (string) Str::uuid()));
        $this->admin->revokePermissionTo('Manage:CommerceSettings');
        $this->denied(403, fn () => app(CommercePolicyActions::class)->publish($this->admin, CommerceRules::defaults(), (string) Str::uuid()));
        $this->assertDatabaseCount('commerce_policy_versions', 1);
    }

    public function test_failure_after_header_and_revision_rolls_back_the_entire_submission(): void
    {
        OrderLine::creating(fn () => throw new \RuntimeException('Synthetic transactional failure'));
        try {
            app(OrderActions::class)->submit($this->customer, $this->input, (string) Str::uuid());
            $this->fail('Expected injected failure');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Synthetic transactional failure', $exception->getMessage());
        }
        foreach (['orders', 'order_revisions', 'order_lines', 'order_events'] as $table) {
            $this->assertDatabaseCount($table, 0);
        }
        $this->assertDatabaseCount('cart_items', 1);
    }

    public function test_model_rejects_unversioned_or_invalid_approval_and_any_payment_transition(): void
    {
        $order = app(OrderActions::class)->submit($this->customer, $this->input, (string) Str::uuid());
        $this->expectValidation(fn () => $order->forceFill(['approval_status' => 'approved', 'approval_expires_at' => now()->addDays(30)])->save());
        $order->refresh();
        $this->expectValidation(fn () => $order->forceFill(['approval_status' => 'approved', 'revision' => 2])->save());
        $order->refresh();
        $this->expectValidation(fn () => $order->forceFill(['payment_status' => 'paid', 'revision' => 2])->save());
        $order->refresh();
        $this->expectValidation(fn () => $order->forceFill(['revision' => 0])->save());
        $this->assertSame('pending', $order->fresh()->approval_status);
        $this->assertSame('unpaid', $order->fresh()->payment_status);
    }

    public function test_new_unavailable_or_changed_terms_are_blocked_even_with_a_fresh_review_fingerprint(): void
    {
        $this->product->is_available = false;
        $this->product->save();
        $this->input['fingerprint'] = app(OrderActions::class)->review($this->customer)['fingerprint'];
        $this->denied(409, fn () => app(OrderActions::class)->submit($this->customer, $this->input, (string) Str::uuid()));
        $this->product->is_available = true;
        $this->product->save();
        $this->product->termsVersions()->create(['version' => 2, 'content' => ['scope' => 'New terms requiring acceptance']]);
        $review = app(OrderActions::class)->review($this->customer);
        $this->assertTrue($review['cart']['requires_review']);
        $this->input['fingerprint'] = $review['fingerprint'];
        $this->denied(409, fn () => app(OrderActions::class)->submit($this->customer, $this->input, (string) Str::uuid()));
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_restricted_category_conflicts_never_convert_to_an_order_or_drop_selections(): void
    {
        $category = ProductCategory::create(['name' => 'Synthetic category', 'limit_one_per_cart' => false]);
        $this->product->category_id = $category->id;
        $this->product->save();
        $second = Product::factory()->published()->create(['category_id' => $category->id]);
        $second->prices()->create(['version' => 1, 'amount_minor' => 5000, 'currency' => 'PHP']);
        $second->termsVersions()->create(['version' => 1, 'content' => ['scope' => 'Synthetic second scope']]);
        $second->is_available = true;
        $second->save();
        $cart = app(CartActions::class)->owned($this->customer);
        app(CartActions::class)->add(CartPrincipal::customer($this->customer), $cart['public_id'], $second->public_id, (string) Str::uuid());
        $category->limit_one_per_cart = true;
        $category->save();
        $review = app(OrderActions::class)->review($this->customer);
        $this->assertFalse($review['cart']['selection_valid']);
        $this->assertCount(1, $review['cart']['category_conflicts']);
        $this->input['fingerprint'] = $review['fingerprint'];
        $this->denied(409, fn () => app(OrderActions::class)->submit($this->customer, $this->input, (string) Str::uuid()));
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('cart_items', 2);
    }

    private function denied(int $status, callable $action): void
    {
        try {
            $action();
            $this->fail('Expected HTTP denial');
        } catch (HttpException $exception) {
            $this->assertSame($status, $exception->getStatusCode());
        }
    }

    private function expectValidation(callable $action): void
    {
        try {
            $action();
            $this->fail('Expected validation failure');
        } catch (ValidationException) {
            $this->assertTrue(true);
        }
    }
}
