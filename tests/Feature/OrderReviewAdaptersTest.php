<?php

namespace Tests\Feature;

use App\Actions\Carts\CartActions;
use App\Actions\Orders\CommercePolicyActions;
use App\Actions\Orders\OrderActions;
use App\Actions\Orders\OrderReviewActions;
use App\Enums\OrderCapability;
use App\Filament\Pages\CommerceSettings;
use App\Filament\Resources\Orders\OrderResource;
use App\Filament\Resources\Orders\Pages\ListOrders;
use App\Filament\Resources\Orders\Pages\ViewOrder;
use App\Models\CommercePolicyVersion;
use App\Models\Order;
use App\Models\OrderProposal;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use App\Services\Carts\CartPrincipal;
use App\Services\Orders\CommerceRules;
use BezhanSalleh\FilamentShield\Facades\FilamentShield;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class OrderReviewAdaptersTest extends TestCase
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
        config(['app.url' => 'https://app.example.test']);
        $this->withSession(['_token' => 'csrf']);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
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

    public function test_customer_and_management_api_complete_clarification_acceptance_and_approval(): void
    {
        $order = $this->order();
        $path = '/api/v1/management/orders/'.$order->public_id;
        $this->actingAs($this->admin)->get($path, ['Accept' => 'application/json'])->assertOk()->assertJsonPath('data.order.revision', 1)->assertJsonPath('meta.csrf_token', 'csrf');
        $this->postCommand($path.'/clarify', ['expected_revision' => 1, 'message' => 'Please clarify.'])->assertOk()->assertJsonPath('data.order.approval_status', 'clarification');
        $this->postCommand($path.'/note', ['expected_revision' => 2, 'message' => 'PRIVATE ONLY'])->assertOk();
        $this->actingAs($this->customer)->get('/api/v1/orders/'.$order->public_id.'/review', ['Accept' => 'application/json'])->assertOk()->assertJsonCount(1, 'data.messages')->assertJsonPath('data.pagination.total', 1)->assertDontSee('PRIVATE ONLY');
        $this->get('/orders/'.$order->public_id)->assertOk()->assertSee('Conversation with Admin')->assertSee('Resubmit for approval')->assertDontSee('PRIVATE ONLY');
        $this->postCommand('/api/v1/orders/'.$order->public_id.'/reply', ['expected_revision' => 3, 'message' => 'More customer context.'])->assertOk();
        $this->postCommand('/api/v1/orders/'.$order->public_id.'/resubmit', $this->correction(4))->assertOk()->assertJsonPath('data.order.approval_status', 'pending')->assertJsonPath('data.order.accepted.version', 2);
        $this->actingAs($this->admin);
        $this->product->prices()->create(['version' => 2, 'amount_minor' => 18000, 'currency' => 'PHP']);
        $this->postCommand($path.'/propose', ['expected_revision' => 5, 'message' => 'Updated offer.'])->assertOk()->assertJsonPath('data.proposal.is_current', true)->assertJsonPath('data.proposal.total_minor', '18000');
        $proposal = OrderProposal::sole()->public_id;
        $this->actingAs($this->customer);
        $uuid = (string) Str::uuid();
        $input = [...$this->correction(6), 'proposal' => $proposal];
        $this->postCommand('/api/v1/orders/'.$order->public_id.'/accept-proposal', $input, $uuid)->assertOk()->assertJsonPath('data.order.approval_status', 'pending')->assertJsonPath('data.order.accepted.total_minor', '18000')->assertJsonPath('data.proposal', null);
        $this->postCommand('/api/v1/orders/'.$order->public_id.'/accept-proposal', $input, $uuid)->assertOk()->assertJsonPath('data.order.accepted.version', 3);
        $this->actingAs($this->admin);
        $this->postCommand($path.'/approve', ['expected_revision' => 7])->assertOk()->assertJsonPath('data.order.approval_status', 'approved')->assertJsonPath('data.order.payment_status', 'unpaid');
        $this->assertDatabaseCount('order_revisions', 3);
        $this->assertDatabaseCount('orders', 1);
    }

    public function test_real_csrf_origin_strict_payload_owner_and_capability_denials(): void
    {
        $this->app->instance('env', 'local');
        $order = $this->order();
        $path = '/api/v1/management/orders/'.$order->public_id;
        $this->actingAs($this->admin)->postJson($path.'/approve', ['expected_revision' => 1], ['Origin' => 'https://app.example.test', 'Idempotency-Key' => (string) Str::uuid()])->assertStatus(419);
        $this->postJson($path.'/approve', ['expected_revision' => 1], ['Origin' => 'https://evil.example', 'X-CSRF-TOKEN' => 'csrf', 'Idempotency-Key' => (string) Str::uuid()])->assertForbidden();
        $this->postCommand($path.'/approve', ['expected_revision' => 1, 'user_id' => 1])->assertUnprocessable();
        $this->postCommand($path.'/approve', ['expected_revision' => '1'])->assertUnprocessable();
        $this->admin->revokePermissionTo(OrderCapability::Approve->value);
        $this->postCommand($path.'/approve', ['expected_revision' => 1])->assertForbidden();
        $this->actingAs($this->customer)->get($path, ['Accept' => 'application/json'])->assertForbidden();
        $other = User::factory()->create();
        $other->assignRole('customer');
        $this->actingAs($other);
        $this->get('/api/v1/orders/'.$order->public_id.'/review', ['Accept' => 'application/json'])->assertNotFound();
        $this->postCommand('/api/v1/orders/'.$order->public_id.'/reply', ['expected_revision' => 1, 'message' => 'No'])->assertNotFound();
        $this->actingAs($this->customer)->get('/api/v1/orders/'.$order->public_id.'/review?owner=1', ['Accept' => 'application/json'])->assertUnprocessable();
        $this->postCommand('/api/v1/orders/'.$order->public_id.'/reply', ['expected_revision' => 1, 'message' => str_repeat('x', 262145)])->assertStatus(413);
        $this->assertDatabaseCount('order_messages', 0);
        $this->assertSame('pending', $order->fresh()->approval_status);
    }

    public function test_admin_resource_named_actions_history_revocation_and_no_crud(): void
    {
        $order = $this->order();
        $this->actingAs($this->admin);
        Livewire::test(ListOrders::class)->assertCountTableRecords(1);
        $component = Livewire::test(ViewOrder::class, ['record' => $order->public_id])->assertSee('Synthetic goal');
        $component->mountAction('clarify')->fillForm(['message' => 'Please clarify <script>unsafe()</script>'])->assertSet('mountedActions.0.data.message', 'Please clarify <script>unsafe()</script>')->callMountedAction()->assertHasNoActionErrors();
        $this->assertSame('clarification', $order->fresh()->approval_status);
        $component->callAction('note', ['message' => 'Private internal note'])->assertHasNoActionErrors()->assertSee('Private internal note');
        $component->assertDontSee('<script>unsafe()</script>', false);
        $this->admin->revokePermissionTo(OrderCapability::View->value);
        $component->call('$refresh')->assertForbidden();
        $this->assertDatabaseCount('order_messages', 2);
        $this->get('/admin/orders/'.$order->public_id.'/edit')->assertNotFound();
    }

    public function test_admin_settings_publish_versions_reject_stale_and_preserve_accepted_settings(): void
    {
        $order = $this->order();
        $this->actingAs($this->admin);
        $page = Livewire::test(CommerceSettings::class)->fillForm(['tax_mode' => 'exclusive', 'tax_basis_points' => 1200, 'approval_days' => 15])->assertSet('data.tax_mode', 'exclusive')->call('save')->assertHasNoFormErrors();
        $this->assertSame(2, app(CommercePolicyActions::class)->current()->version);
        $this->assertSame('exclusive', app(CommercePolicyActions::class)->current()->content['tax_mode']);
        $this->assertSame('unconfigured', $order->currentRevision->policy_snapshot['tax_mode']);
        $stale = Livewire::test(CommerceSettings::class);
        $page->fillForm(['approval_days' => 20])->call('save')->assertHasNoFormErrors();
        $stale->fillForm(['approval_days' => 10])->call('save');
        $this->assertSame(3, CommercePolicyVersion::count());
        $this->assertSame(20, app(CommercePolicyActions::class)->current()->content['approval_days']);
        $this->admin->revokePermissionTo(OrderCapability::Settings->value);
        $page->call('save')->assertForbidden();
    }

    public function test_settings_api_has_strict_optimistic_version_and_idempotent_receipts(): void
    {
        $this->actingAs($this->admin);
        $path = '/api/v1/management/commerce-settings';
        $this->get($path, ['Accept' => 'application/json'])->assertOk()->assertJsonPath('data.version', 1);
        $this->head($path, ['Accept' => 'application/json'])->assertOk()->assertHeader('Cache-Control', 'no-store, private');
        $content = CommerceRules::defaults();
        $content['approval_days'] = 17;
        $input = ['expected_version' => 1, 'content' => $content];
        $uuid = (string) Str::uuid();
        $headers = ['Origin' => 'https://app.example.test', 'X-CSRF-TOKEN' => 'csrf', 'Idempotency-Key' => $uuid];
        $this->putJson($path, $input, $headers)->assertOk()->assertJsonPath('data.version', 2);
        $this->putJson($path, $input, $headers)->assertOk()->assertJsonPath('data.version', 2);
        $headers['Idempotency-Key'] = (string) Str::uuid();
        $this->putJson($path, $input, $headers)->assertConflict();
        $this->assertDatabaseCount('commerce_policy_versions', 2);
    }

    public function test_stale_native_correction_recovers_only_for_same_owner_and_requires_fresh_acceptance(): void
    {
        $order = $this->order();
        app(OrderReviewActions::class)->staff($this->admin, $order->public_id, 'clarify', ['expected_revision' => 1, 'message' => 'Clarify'], (string) Str::uuid());
        $this->actingAs($this->customer);
        $input = $this->correction(1);
        $input['brief']['goal'] = 'Typed correction';
        $input['accept_terms'] = '1';
        $this->post('/orders/'.$order->public_id.'/resubmit', [...$input, '_token' => 'csrf', 'operation_id' => (string) Str::uuid()], ['Origin' => 'https://app.example.test'])->assertRedirect('/orders/'.$order->public_id);
        $this->withCookie(config('session.cookie'), session()->getId())->get('/orders/'.$order->public_id)->assertOk()->assertSee('Typed correction')->assertDontSee('checked');
        $this->assertDatabaseCount('order_revisions', 1);
        $other = User::factory()->create();
        $other->assignRole('customer');
        $guest = CartPrincipal::guest(bin2hex(random_bytes(32)));
        $carts = app(CartActions::class);
        $cart = $carts->create($guest, (string) Str::uuid());
        $carts->add($guest, $cart['public_id'], $this->product->public_id, (string) Str::uuid());
        $preview = $carts->mergePreview($other, $guest, $cart['public_id']);
        $carts->attach($other, $guest, $cart['public_id'], (string) Str::uuid(), $preview['fingerprint']);
        $otherOrder = app(OrderActions::class)->submit($other, [...$this->input, 'fingerprint' => app(OrderActions::class)->review($other)['fingerprint']], (string) Str::uuid());
        app(OrderReviewActions::class)->staff($this->admin, $otherOrder->public_id, 'clarify', ['expected_revision' => 1, 'message' => 'Other owner clarification'], (string) Str::uuid());
        $this->actingAs($other)->withSession(['commerce.review_input_owner' => [$this->customer->id, $order->public_id], '_old_input' => ['brief' => ['goal' => 'PRIVATE PREVIOUS OWNER']]])
            ->get('/orders/'.$otherOrder->public_id)->assertOk()->assertDontSee('PRIVATE PREVIOUS OWNER');
    }

    public function test_role_controls_expose_exact_review_and_settings_capabilities_without_default_grants(): void
    {
        $permissions = FilamentShield::getCustomPermissions();
        $this->assertSame(['Manage:CommerceSettings'], array_keys($permissions));
        $this->assertTrue(config('filament-shield.shield_resource.tabs.custom_permissions'));
        $this->assertSame(['viewAny', 'view', 'approve', 'reject', 'clarify', 'message', 'note', 'propose'], config('filament-shield.resources.manage.'.OrderResource::class));
        $this->assertSame(0, Role::where('name', 'administrator')->firstOrFail()->permissions()->count());
    }

    public function test_admin_shows_only_actions_available_in_current_review_state(): void
    {
        $order = $this->order();
        app(OrderReviewActions::class)->staff($this->admin, $order->public_id, 'approve', ['expected_revision' => 1], (string) Str::uuid());
        $this->actingAs($this->admin);
        Livewire::test(ViewOrder::class, ['record' => $order->public_id])->assertActionHidden('approve')->assertActionHidden('reject')->assertActionVisible('propose')->assertActionVisible('clarify');
    }

    public function test_native_acceptance_validation_preserves_unknown_keys_for_rejection(): void
    {
        $order = $this->order();
        app(OrderReviewActions::class)->staff($this->admin, $order->public_id, 'clarify', ['expected_revision' => 1, 'message' => 'Clarify'], (string) Str::uuid());
        $this->actingAs($this->customer);
        $input = [...$this->correction(2), '_token' => 'csrf', 'operation_id' => (string) Str::uuid()];
        unset($input['accept_terms']);
        $this->from('/orders/'.$order->public_id)->post('/orders/'.$order->public_id.'/resubmit', $input, ['Origin' => 'https://app.example.test'])->assertRedirect('/orders/'.$order->public_id)->assertSessionHasErrors('body.accept_terms');
        $input['accept_terms'] = '1';
        $input['brief']['forbidden'] = null;
        $this->from('/orders/'.$order->public_id)->post('/orders/'.$order->public_id.'/resubmit', $input, ['Origin' => 'https://app.example.test'])->assertRedirect('/orders/'.$order->public_id)->assertSessionHasErrors('input.brief');
        $this->assertDatabaseCount('order_revisions', 1);
    }

    private function postCommand(string $path, array $input, ?string $uuid = null)
    {
        return $this->postJson($path, $input, ['Origin' => 'https://app.example.test', 'X-CSRF-TOKEN' => 'csrf', 'Idempotency-Key' => $uuid ?? (string) Str::uuid()]);
    }

    private function order(): Order
    {
        return app(OrderActions::class)->submit($this->customer, $this->input, (string) Str::uuid());
    }

    private function correction(int $revision): array
    {
        return ['expected_revision' => $revision, 'brief' => $this->input['brief'], 'billing' => $this->input['billing'], 'accept_terms' => true];
    }
}
