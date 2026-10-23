<?php

namespace Tests\Feature;

use App\Actions\Customers\ManagePortalCustomer;
use App\Actions\Portal\PortalActions;
use App\Enums\PortalCapability;
use App\Filament\Account\Pages\Support;
use App\Filament\Pages\Portal\Customers;
use App\Livewire\Portal\StaffSupportInbox;
use App\Livewire\Portal\SupportInbox;
use App\Models\PortalEvent;
use App\Models\SupportCase;
use App\Models\User;
use App\Notifications\CustomerAccountActivation;
use App\Services\Portal\PortalRead;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Support\Portal\CreatesPortal;
use Tests\TestCase;

class PortalWorkflowPolishTest extends TestCase
{
    use CreatesPortal, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpPortal();
    }

    public function test_linked_inbox_creates_and_replies_without_navigation_and_all_inbox_includes_standalone_cases(): void
    {
        $work = $this->portalWork();
        $otherWork = $this->portalWork(['title' => 'Separate service', 'type' => 'service']);
        $standalone = app(PortalActions::class)->createCase($this->portalCustomer, $this->caseInput(['subject' => 'General account help']), (string) Str::uuid());
        Filament::setCurrentPanel(Filament::getPanel('account'));
        $this->actingAs($this->portalCustomer);
        $this->get('/projects?record='.$work->public_id)->assertOk()->assertSeeLivewire(SupportInbox::class)->assertDontSee('@livewire', false);
        $inbox = Livewire::test(SupportInbox::class, ['work' => $work->public_id])->assertDontSee('General account help');
        $inbox->callAction('createCase', $this->caseInput(['subject' => 'Linked inline report', 'work' => $otherWork->public_id]))->assertHasNoActionErrors()->assertNoRedirect()->assertSee('Linked inline report');
        $case = SupportCase::where('subject', 'Linked inline report')->sole();
        $this->assertSame($work->id, $case->work_item_id);
        $inbox->assertSet('record', $case->public_id)->callAction('reply', ['body' => 'Stay in this project'])->assertHasNoActionErrors()->assertNoRedirect()->assertSee('Stay in this project');
        $inbox->call('closeConversation')->assertDontSee('Stay in this project')->call('openConversation', $case->public_id)->assertSee('Stay in this project');
        Livewire::test(Support::class)->assertSee('General account help')->assertSee('Linked inline report')->call('openConversation', $standalone->public_id)->assertSee($standalone->description);
    }

    public function test_context_rejects_other_owned_work_and_other_customer_conversations_and_revoked_access(): void
    {
        $work = $this->portalWork();
        $separate = app(PortalActions::class)->createCase($this->portalCustomer, $this->caseInput(), (string) Str::uuid());
        $private = app(PortalActions::class)->createCase($this->portalOther, $this->caseInput(), (string) Str::uuid());
        Filament::setCurrentPanel(Filament::getPanel('account'));
        $this->actingAs($this->portalCustomer);
        Livewire::test(SupportInbox::class, ['work' => $work->public_id])->call('openConversation', $separate->public_id)->assertNotFound();
        Livewire::test(SupportInbox::class, ['work' => $work->public_id])->call('openConversation', $private->public_id)->assertNotFound();
        $component = Livewire::test(SupportInbox::class, ['work' => $work->public_id]);
        $this->portalCustomer->removeRole('customer');
        $component->call('$refresh')->assertForbidden();
    }

    public function test_staff_context_hides_private_notes_without_permission_and_closed_case_has_no_reply(): void
    {
        $work = $this->portalWork();
        $case = app(PortalActions::class)->createCase($this->portalCustomer, $this->caseInput(['work' => $work->public_id]), (string) Str::uuid());
        app(PortalActions::class)->reply($this->portalAdmin, $case->public_id, ['expected_revision' => 1, 'body' => 'Secret note'], (string) Str::uuid(), true, true);
        $this->portalAdmin->revokePermissionTo(PortalCapability::NoteCases->value);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($this->portalAdmin);
        $component = Livewire::test(StaffSupportInbox::class, ['work' => $work->public_id])->call('openConversation', $case->public_id)->assertDontSee('Secret note');
        $component->callAction('manageCase', ['status' => 'closed', 'priority' => 'normal'])->assertHasNoActionErrors()->assertActionDoesNotExist('reply');
        $this->portalAdmin->revokePermissionTo(PortalCapability::ViewCases->value);
        $component->call('$refresh')->assertForbidden();
    }

    public function test_stale_read_acknowledgement_cannot_clear_a_newer_reply(): void
    {
        $case = app(PortalActions::class)->createCase($this->portalCustomer, $this->caseInput(), (string) Str::uuid());
        DB::table('support_cases')->where('id', $case->id)->update(['customer_read_at' => null]);
        app(PortalActions::class)->reply($this->portalAdmin, $case->public_id, ['expected_revision' => 1, 'body' => 'New reply'], (string) Str::uuid(), true);
        app(PortalRead::class)->markCaseRead($this->portalCustomer, $case->public_id, false, 1);
        $this->assertNull($case->fresh()->customer_read_at);
        app(PortalRead::class)->markCaseRead($this->portalCustomer, $case->public_id, false, 2);
        $this->assertNotNull($case->fresh()->customer_read_at);
    }

    public function test_admin_can_create_customer_invite_and_find_the_account_without_granting_staff_access(): void
    {
        Notification::fake();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($this->portalAdmin);
        Livewire::test(Customers::class)->callAction('createCustomer', ['name' => ' New Customer ', 'email' => 'NEW@example.test'])->assertHasNoActionErrors();
        $customer = User::where('email', 'new@example.test')->sole();
        $this->assertSame('New Customer', $customer->name);
        $this->assertNull($customer->email_verified_at);
        $this->assertSame(['customer'], $customer->getRoleNames()->all());
        $this->assertSame(0, $customer->permissions()->count());
        Notification::assertSentTo($customer, CustomerAccountActivation::class);
        $this->assertDatabaseHas('portal_events', ['actor_id' => $this->portalAdmin->id, 'action' => 'customer_created']);
        Livewire::test(Customers::class)->set('search', 'NEW@')->assertSee('New Customer')->assertDontSee($this->portalAdmin->email);
        Livewire::withQueryParams(['record' => $customer->id])->test(Customers::class)->assertSee('Awaiting activation')->assertSee('Assign project or service');
        $this->travel(61)->seconds();
        app(ManagePortalCustomer::class)->resend($this->portalAdmin, $customer->id);
        Notification::assertSentToTimes($customer, CustomerAccountActivation::class, 2);
        try {
            app(ManagePortalCustomer::class)->create($this->portalAdmin, 'Overwrite', ' NEW@EXAMPLE.TEST ');
            $this->fail('Duplicate accepted');
        } catch (ValidationException) {
            $this->assertSame('New Customer', $customer->fresh()->name);
        }
        $this->portalAdmin->revokePermissionTo(PortalCapability::ManageCustomers->value);
        $this->expectException(HttpException::class);
        app(ManagePortalCustomer::class)->create($this->portalAdmin, 'Denied', 'denied@example.test');
    }

    public function test_invitation_is_durably_queued_on_the_same_database_and_rollback_keeps_both_atomic(): void
    {
        config(['queue.default' => 'sync', 'queue.connections.database.connection' => null]);
        $customer = app(ManagePortalCustomer::class)->create($this->portalAdmin, 'Queued Customer', 'queued@example.test');
        $this->assertDatabaseCount('jobs', 1);
        $this->assertStringContainsString(CustomerAccountActivation::class, json_decode(DB::table('jobs')->sole()->payload, true)['data']['command']);
        Event::listen('eloquent.creating: '.PortalEvent::class, fn () => throw new \RuntimeException('Audit failure after enqueue'));
        try {
            app(ManagePortalCustomer::class)->create($this->portalAdmin, 'Rollback Both', 'rollback-both@example.test');
            $this->fail('Audit failure ignored');
        } catch (\RuntimeException $e) {
            $this->assertSame('Audit failure after enqueue', $e->getMessage());
        }
        $this->assertDatabaseMissing('users', ['email' => 'rollback-both@example.test']);
        $this->assertDatabaseCount('jobs', 1);
        $this->assertNotNull($customer->fresh());
    }

    public function test_customer_create_rolls_back_when_queue_acceptance_fails_and_unprivileged_users_cannot_view_customers(): void
    {
        Notification::shouldReceive('send')->andThrow(new \RuntimeException('Synthetic queue unavailable'));
        try {
            app(ManagePortalCustomer::class)->create($this->portalAdmin, 'Rollback', 'rollback@example.test');
            $this->fail('Queue failure ignored');
        } catch (\RuntimeException $e) {
            $this->assertSame('Synthetic queue unavailable', $e->getMessage());
        }
        $this->assertDatabaseMissing('users', ['email' => 'rollback@example.test']);
        $this->assertDatabaseMissing('portal_events', ['action' => 'customer_created']);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($this->portalCustomer)->get('/admin/customers')->assertForbidden();
        $this->portalAdmin->revokePermissionTo(PortalCapability::ViewCustomers->value);
        $this->actingAs($this->portalAdmin)->get('/admin/customers')->assertForbidden();
    }
}
