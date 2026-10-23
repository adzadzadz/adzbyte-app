<?php

namespace Tests\Feature;

use App\Actions\Carts\CartActions;
use App\Actions\Orders\CommercePolicyActions;
use App\Actions\Orders\OrderActions;
use App\Actions\Orders\OrderReviewActions;
use App\Enums\OrderCapability;
use App\Models\Order;
use App\Models\OrderMessage;
use App\Models\OrderProposal;
use App\Models\OrderRevision;
use App\Models\Product;
use App\Models\User;
use App\Services\Carts\CartPrincipal;
use App\Services\Orders\CommerceRules;
use Database\Seeders\RoleSeeder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class OrderReviewTest extends TestCase
{
    use RefreshDatabase;

    private User $customer;

    private User $admin;

    private Product $product;

    private array $input;

    protected function setUp(): void
    {
        parent::setUp();
        $this->freezeTime();
        $this->seed(RoleSeeder::class);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('administrator');
        $this->admin->givePermissionTo(array_map(fn ($c) => $c->value, OrderCapability::cases()));
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

    public function test_clarification_reply_resubmission_and_approval_preserve_accepted_history(): void
    {
        $actions = app(OrderReviewActions::class);
        $order = $this->order();
        $original = $order->currentRevision->toArray();
        $order = $actions->staff($this->admin, $order->public_id, 'clarify', ['expected_revision' => 1, 'message' => 'Please clarify your goal.'], (string) Str::uuid());
        $this->assertSame('clarification', $order->approval_status);
        $order = $actions->customer($this->customer, $order->public_id, 'reply', ['expected_revision' => 2, 'message' => 'Here is more context.'], (string) Str::uuid());
        $this->assertSame('clarification', $order->approval_status);
        $order = $actions->customer($this->customer, $order->public_id, 'resubmit', $this->resubmission(3), (string) Str::uuid());
        $this->assertSame('pending', $order->approval_status);
        $this->assertSame(2, $order->currentRevision->version);
        $this->assertSame('12500', $order->currentRevision->total_minor);
        $order = $actions->staff($this->admin, $order->public_id, 'approve', ['expected_revision' => 4], (string) Str::uuid());
        $this->assertSame('approved', $order->approval_status);
        $this->assertSame(now()->addDays(30)->toDateTimeString(), $order->approval_expires_at->toDateTimeString());
        $this->assertSame('unpaid', $order->payment_status);
        $this->assertEquals($original, OrderRevision::orderBy('id')->first()->load('lines', 'policyVersion')->toArray());
        $this->assertDatabaseCount('order_messages', 2);
        $this->assertDatabaseCount('order_events', 5);
    }

    public function test_exact_capabilities_fresh_actor_and_owner_queries_cannot_be_overridden(): void
    {
        $actions = app(OrderReviewActions::class);
        $order = $this->order();
        Gate::before(fn () => true);
        $this->admin->revokePermissionTo(OrderCapability::Approve->value);
        $this->denied(403, fn () => $actions->staff($this->admin, $order->public_id, 'approve', ['expected_revision' => 1], (string) Str::uuid()));
        $other = User::factory()->create();
        $other->assignRole('customer');
        try {
            $actions->customer($other, $order->public_id, 'reply', ['expected_revision' => 1, 'message' => 'No'], (string) Str::uuid());
            $this->fail('Cross owner allowed');
        } catch (ModelNotFoundException) {
            $this->assertTrue(true);
        }
        $this->customer->removeRole('customer');
        $this->denied(403, fn () => $actions->customer($this->customer, $order->public_id, 'reply', ['expected_revision' => 1, 'message' => 'No'], (string) Str::uuid()));
        $this->admin->email_verified_at = null;
        $this->admin->save();
        $this->denied(403, fn () => $actions->staff($this->admin, $order->public_id, 'clarify', ['expected_revision' => 1, 'message' => 'No'], (string) Str::uuid()));
        $this->assertDatabaseCount('order_messages', 0);
    }

    public function test_duplicate_command_recovers_once_and_uuid_cannot_change_order_or_input(): void
    {
        $a = app(OrderReviewActions::class);
        $order = $this->order();
        $uuid = (string) Str::uuid();
        $input = ['expected_revision' => 1, 'message' => 'Public response'];
        $result = $a->staff($this->admin, $order->public_id, 'message', $input, $uuid);
        $this->assertSame(2, $a->staff($this->admin, $order->public_id, 'message', $input, $uuid)->revision);
        $this->denied(409, fn () => $a->staff($this->admin, $order->public_id, 'note', $input, $uuid));
        $this->denied(409, fn () => $a->staff($this->admin, $order->public_id, 'message', [...$input, 'message' => 'changed'], $uuid));
        $this->denied(409, fn () => $a->staff($this->admin, $order->public_id, 'message', $input, (string) Str::uuid()));
        $this->assertDatabaseCount('order_messages', 1);
        $this->assertDatabaseCount('order_events', 2);
    }

    public function test_internal_notes_are_immutable_and_never_in_customer_conversation(): void
    {
        $a = app(OrderReviewActions::class);
        $order = $this->order();
        $order = $a->staff($this->admin, $order->public_id, 'note', ['expected_revision' => 1, 'message' => 'PRIVATE secret note'], (string) Str::uuid());
        $order = $a->staff($this->admin, $order->public_id, 'message', ['expected_revision' => 2, 'message' => 'Public update'], (string) Str::uuid());
        $messages = $a->conversation($this->customer, $order->public_id);
        $this->assertCount(1, $messages);
        $this->assertSame('Public update', $messages->sole()->body);
        $this->assertCount(2, $a->staffConversation($this->admin, $order->public_id));
        $this->expectValidation(fn () => OrderMessage::first()->forceFill(['body' => 'Rewrite'])->save());
        $this->expectValidation(fn () => OrderMessage::first()->delete());
    }

    public function test_material_offer_requires_fresh_customer_acceptance_then_admin_approval(): void
    {
        $a = app(OrderReviewActions::class);
        $order = $this->order();
        $order = $a->staff($this->admin, $order->public_id, 'approve', ['expected_revision' => 1], (string) Str::uuid());
        $this->product->prices()->create(['version' => 2, 'amount_minor' => 20000, 'currency' => 'PHP']);
        $order = $a->staff($this->admin, $order->public_id, 'propose', ['expected_revision' => 2, 'message' => 'Updated offer for your review.'], (string) Str::uuid());
        $this->assertSame('clarification', $order->approval_status);
        $this->assertNull($order->approval_expires_at);
        $this->assertSame('12500', $order->currentRevision->total_minor);
        $proposal = OrderProposal::sole();
        $this->assertSame('20000', $proposal->content['total_minor']);
        $this->denied(409, fn () => $a->customer($this->customer, $order->public_id, 'resubmit', $this->resubmission(3), (string) Str::uuid()));
        $input = [...$this->resubmission(3), 'proposal' => $proposal->public_id];
        $order = $a->customer($this->customer, $order->public_id, 'accept_proposal', $input, (string) Str::uuid());
        $this->assertSame('pending', $order->approval_status);
        $this->assertNull($order->pending_proposal_id);
        $this->assertSame('20000', $order->currentRevision->total_minor);
        $this->assertSame(2, $order->currentRevision->version);
        $this->assertSame('12500', OrderRevision::orderBy('id')->first()->total_minor);
        $order = $a->staff($this->admin, $order->public_id, 'approve', ['expected_revision' => 4], (string) Str::uuid());
        $this->assertSame('approved', $order->approval_status);
        $this->expectValidation(fn () => $proposal->delete());
    }

    public function test_price_policy_or_availability_changes_make_proposal_acceptance_stale(): void
    {
        $a = app(OrderReviewActions::class);
        $order = $this->order();
        $order = $a->staff($this->admin, $order->public_id, 'propose', ['expected_revision' => 1, 'message' => 'Offer review'], (string) Str::uuid());
        $input = [...$this->resubmission(2), 'proposal' => OrderProposal::sole()->public_id];
        $this->product->termsVersions()->create(['version' => 2, 'content' => ['scope' => 'Changed scope']]);
        $this->denied(409, fn () => $a->customer($this->customer, $order->public_id, 'accept_proposal', $input, (string) Str::uuid()));
        $this->assertDatabaseCount('order_revisions', 1);
        $this->product->is_available = false;
        $this->product->save();
        $this->denied(409, fn () => $a->staff($this->admin, $order->public_id, 'propose', ['expected_revision' => 2, 'message' => 'Unavailable'], (string) Str::uuid()));
        $this->assertDatabaseCount('order_proposals', 1);
    }

    public function test_expiry_uses_captured_policy_once_and_does_not_expire_cart(): void
    {
        $a = app(OrderReviewActions::class);
        $order = $this->order();
        $policy = CommerceRules::defaults();
        $policy['approval_days'] = 1;
        app(CommercePolicyActions::class)->publish($this->admin, $policy, (string) Str::uuid());
        $order = $a->staff($this->admin, $order->public_id, 'approve', ['expected_revision' => 1], (string) Str::uuid());
        $expiry = $order->approval_expires_at;
        $this->assertSame(now()->addDays(30)->toDateTimeString(), $expiry->toDateTimeString());
        $this->assertFalse($a->expire($order->public_id));
        $this->travelTo($expiry);
        $this->assertTrue($a->expire($order->public_id));
        $this->assertFalse($a->expire($order->public_id));
        $order = $order->fresh();
        $this->assertSame('expired', $order->approval_status);
        $this->assertNull($order->approval_expires_at);
        $this->assertDatabaseCount('cart_items', 1);
        $this->assertDatabaseCount('order_events', 3);
        $this->assertSame(3, $order->revision);
        $this->artisan('commerce:expire-order-approvals')->assertSuccessful();
        $this->assertDatabaseCount('order_events', 3);
    }

    public function test_new_proposal_supersedes_reference_and_new_policy_makes_acceptance_stale(): void
    {
        $a = app(OrderReviewActions::class);
        $order = $this->order();
        $order = $a->staff($this->admin, $order->public_id, 'propose', ['expected_revision' => 1, 'message' => 'First offer'], (string) Str::uuid());
        $old = OrderProposal::sole()->public_id;
        $order = $a->staff($this->admin, $order->public_id, 'propose', ['expected_revision' => 2, 'message' => 'Latest offer'], (string) Str::uuid());
        $this->denied(409, fn () => $a->customer($this->customer, $order->public_id, 'accept_proposal', [...$this->resubmission(3), 'proposal' => $old], (string) Str::uuid()));
        $current = OrderProposal::orderByDesc('id')->first()->public_id;
        $policy = CommerceRules::defaults();
        $policy['terms'] .= ' Additional synthetic terms.';
        app(CommercePolicyActions::class)->publish($this->admin, $policy, (string) Str::uuid());
        $this->denied(409, fn () => $a->customer($this->customer, $order->public_id, 'accept_proposal', [...$this->resubmission(3), 'proposal' => $current], (string) Str::uuid()));
        $this->assertDatabaseCount('order_revisions', 1);
        $this->assertDatabaseCount('order_proposals', 2);
    }

    public function test_settings_permission_cannot_be_bypassed_by_a_global_gate_override(): void
    {
        $this->admin->revokePermissionTo(OrderCapability::Settings->value);
        Gate::before(fn () => true);
        $this->denied(403, fn () => app(CommercePolicyActions::class)->publish($this->admin, CommerceRules::defaults(), (string) Str::uuid()));
    }

    public function test_rejection_requires_reason_is_terminal_and_does_not_enable_payment(): void
    {
        $a = app(OrderReviewActions::class);
        $order = $this->order();
        $this->expectValidation(fn () => $a->staff($this->admin, $order->public_id, 'reject', ['expected_revision' => 1, 'message' => ' '], (string) Str::uuid()));
        $order = $a->staff($this->admin, $order->public_id, 'reject', ['expected_revision' => 1, 'message' => 'Cannot take this scope.'], (string) Str::uuid());
        $this->assertSame('rejected', $order->approval_status);
        $this->assertSame('unpaid', $order->payment_status);
        $this->denied(409, fn () => $a->staff($this->admin, $order->public_id, 'approve', ['expected_revision' => 2], (string) Str::uuid()));
        $this->denied(409, fn () => $a->customer($this->customer, $order->public_id, 'resubmit', $this->resubmission(2), (string) Str::uuid()));
    }

    public function test_injected_fields_and_non_boolean_acceptance_are_rejected_without_writes(): void
    {
        $a = app(OrderReviewActions::class);
        $order = $this->order();
        $this->expectValidation(fn () => $a->staff($this->admin, $order->public_id, 'approve', ['expected_revision' => 1, 'payment_status' => 'paid'], (string) Str::uuid()));
        $order = $a->staff($this->admin, $order->public_id, 'clarify', ['expected_revision' => 1, 'message' => 'Clarify'], (string) Str::uuid());
        $this->denied(422, fn () => $a->customer($this->customer, $order->public_id, 'resubmit', [...$this->resubmission(2), 'accept_terms' => '1'], (string) Str::uuid()));
        $this->assertDatabaseCount('order_revisions', 1);
    }

    public function test_message_failure_rolls_back_state_event_and_receipt(): void
    {
        $a = app(OrderReviewActions::class);
        $order = $this->order();
        OrderMessage::creating(fn () => throw new \RuntimeException('Synthetic message failure'));
        try {
            $a->staff($this->admin, $order->public_id, 'clarify', ['expected_revision' => 1, 'message' => 'Clarify'], (string) Str::uuid());
            $this->fail('Expected failure');
        } catch (\RuntimeException $e) {
            $this->assertSame('Synthetic message failure', $e->getMessage());
        }
        $this->assertSame('pending', $order->fresh()->approval_status);
        $this->assertDatabaseCount('order_events', 1);
        $this->assertDatabaseCount('order_messages', 0);
    }

    private function order(): Order
    {
        return app(OrderActions::class)->submit($this->customer, $this->input, (string) Str::uuid());
    }

    private function resubmission(int $revision): array
    {
        return ['expected_revision' => $revision, 'brief' => $this->input['brief'], 'billing' => $this->input['billing'], 'accept_terms' => true];
    }

    private function denied(int $code, callable $action): void
    {
        try {
            $action();
            $this->fail('Expected denial');
        } catch (HttpException $e) {
            $this->assertSame($code, $e->getStatusCode());
        }
    }

    private function expectValidation(callable $action): void
    {
        try {
            $action();
            $this->fail('Expected validation');
        } catch (ValidationException) {
            $this->assertTrue(true);
        }
    }
}
