<?php

namespace Tests\Feature;

use App\Enums\PortalCapability;
use App\Filament\Account\Pages\Projects;
use App\Filament\Account\Pages\Services;
use App\Filament\Account\Pages\Support;
use App\Filament\Pages\Portal\ClientWork;
use App\Filament\Pages\Portal\CustomerSupport;
use App\Models\SupportCase;
use App\Models\WorkItem;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\Support\Portal\CreatesPortal;
use Tests\TestCase;

class CustomerPortalPanelTest extends TestCase
{
    use CreatesPortal, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpPortal();
    }

    protected function tearDown(): void
    {
        // The production-preview case enables Symfony's static host matcher.
        // Do not carry that synthetic host restriction into later test apps.
        Request::setTrustedHosts([]);
        parent::tearDown();
    }

    public function test_staff_create_work_publish_update_and_complete_milestone_through_real_panel_actions(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($this->portalAdmin);
        $initial = Livewire::test(ClientWork::class);
        $this->assertTrue(Str::isUuid($initial->get('operationIds.create')));
        $initial->callAction('createWork', $this->workInput())->assertHasNoActionErrors();
        $work = WorkItem::sole();
        $component = Livewire::withQueryParams(['record' => $work->public_id])->test(ClientWork::class)->assertSee($work->title);
        $component->callAction('addMilestone', ['title' => 'First design', 'completed' => true])->assertHasNoActionErrors();
        $component->callAction('publishUpdate', ['body' => 'Design completed <script>unsafe()</script> https://drive.google.com/file/d/synthetic/view'])->assertHasNoActionErrors()->assertSee('Design completed')->assertDontSee('<script>unsafe()</script>', false);
        $data = $this->workInput(['status' => 'waiting_for_customer']);
        unset($data['customer'], $data['type']);
        $component->callAction('editWork', $data)->assertHasNoActionErrors();
        $this->assertSame('waiting_for_customer', $work->fresh()->status);
        $this->assertSame(4, $work->fresh()->revision);
        $this->portalAdmin->revokePermissionTo(PortalCapability::ViewWork->value);
        $component->call('$refresh')->assertForbidden();
    }

    public function test_customer_creates_and_follows_support_and_staff_reply_notes_and_manual_status_changes(): void
    {
        $work = $this->portalWork();
        Filament::setCurrentPanel(Filament::getPanel('account'));
        $this->actingAs($this->portalCustomer);
        $initial = Livewire::test(Support::class);
        $this->assertTrue(Str::isUuid($initial->get('operationIds.create')));
        $initial->callAction('createCase', $this->caseInput(['work' => $work->public_id]))->assertHasNoActionErrors();
        $case = SupportCase::sole();
        $component = Livewire::withQueryParams(['record' => $case->public_id])->test(Support::class)->assertSee($case->subject)->assertSee($work->title);
        $component->callAction('reply', ['body' => 'Customer followup'])->assertHasNoActionErrors()->assertSee('Customer followup');
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($this->portalAdmin);
        $staff = Livewire::withQueryParams(['record' => $case->public_id])->test(CustomerSupport::class)->assertSee('Customer followup');
        $staff->callAction('note', ['body' => 'CONFIDENTIAL STAFF ONLY'])->assertHasNoActionErrors()->assertSee('CONFIDENTIAL STAFF ONLY');
        $staff->callAction('reply', ['body' => 'Team response'])->assertHasNoActionErrors()->assertSee('Team response');
        $staff->callAction('manageCase', ['status' => 'resolved', 'priority' => 'normal'])->assertHasNoActionErrors()->assertSee('resolved');
        Filament::setCurrentPanel(Filament::getPanel('account'));
        $this->actingAs($this->portalCustomer);
        Livewire::withQueryParams(['record' => $case->public_id])->test(Support::class)->assertSee('Team response')->assertDontSee('CONFIDENTIAL STAFF ONLY')->assertDontSee('Internal notes')->assertActionDoesNotExist('reply');
    }

    public function test_home_empty_lists_live_search_owned_details_and_guest_or_cross_owner_access(): void
    {
        $work = $this->portalWork(['title' => '<script>workUnsafe()</script> Website']);
        $this->portalWork(['customer' => $this->portalOther->id, 'title' => 'Other PRIVATE project']);
        Filament::setCurrentPanel(Filament::getPanel('account'));
        $this->actingAs($this->portalCustomer)->get('/')->assertOk()->assertSee('Active projects')->assertSee('Your projects & services', false)->assertDontSee('Other PRIVATE project');
        Livewire::test(Projects::class)->assertSee('Website')->assertDontSee('<script>workUnsafe()</script>', false)->set('search', 'nothing matches')->assertSee('No matching work');
        Livewire::test(Services::class)->assertSee('No services yet');
        Livewire::withQueryParams(['record' => $work->public_id])->test(Projects::class)->assertSee('Milestones')->assertSee('Next step');
        $this->actingAs($this->portalOther)->get('/projects?record='.$work->public_id)->assertNotFound();
        $this->actingAs($this->portalCustomer)->get('/admin/client-work')->assertForbidden();
        auth()->logout();
        foreach (['/projects', '/services', '/support', '/commerce-preview'] as $path) {
            $this->get($path)->assertRedirect('/login');
        }
    }

    public function test_view_only_staff_cannot_mutate_work_or_retrieve_notes_and_stale_forms_do_not_overwrite(): void
    {
        $work = $this->portalWork();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($this->portalAdmin);
        $first = Livewire::withQueryParams(['record' => $work->public_id])->test(ClientWork::class);
        $second = Livewire::withQueryParams(['record' => $work->public_id])->test(ClientWork::class);
        $first->callAction('publishUpdate', ['body' => 'First wins'])->assertHasNoActionErrors();
        $second->callAction('publishUpdate', ['body' => 'Stale attempt']);
        $this->assertSame(1, $work->updates()->count());
        $this->portalAdmin->revokePermissionTo(PortalCapability::ManageWork->value);
        Livewire::withQueryParams(['record' => $work->public_id])->test(ClientWork::class)->assertActionHidden('publishUpdate')->assertActionHidden('editWork');
    }

    public function test_production_keeps_commerce_visible_as_preview_and_blocks_customer_transactions(): void
    {
        $this->app->instance('env', 'production');
        Filament::setCurrentPanel(Filament::getPanel('account'));
        $this->actingAs($this->portalCustomer);
        $this->get('/')->assertOk()->assertSee('Under development')->assertSee('Ecommerce')->assertSee('Ecommerce · Under development');
        $this->get('/commerce-preview')->assertOk()->assertSee('Checkout and online payments are not available here yet.')->assertSee('Subscriptions');
        $this->get('/cart')->assertOk()->assertSee('Under development');
        $this->get('/orders')->assertOk()->assertSee('Under development');
        $this->get('/checkout')->assertRedirect('/cart');
        // Production CSRF can run before the guard: supply the actual session token.
        $this->withSession(['_token' => 'synthetic-token']);
        $headers = ['Origin' => 'http://localhost', 'X-CSRF-TOKEN' => 'synthetic-token'];
        $this->post('/checkout', [], $headers)->assertStatus(503)->assertSee('Ecommerce is under development');
        $this->postJson('/api/v1/orders', [], $headers)->assertStatus(503)->assertJsonPath('error.message', 'Ecommerce is under development. Checkout is not available yet.');
        $this->postJson('/api/v1/cart-handoffs/consume', [], $headers)->assertStatus(503);
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('payment_attempts', 0);
    }
}
