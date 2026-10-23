<?php

namespace Tests\Feature;

use App\Actions\Portal\PortalActions;
use App\Filament\Account\Pages\Projects;
use App\Filament\Pages\Portal\ClientWork;
use App\Filament\Pages\Portal\Customers;
use App\Filament\Pages\Portal\CustomerSupport;
use App\Livewire\Portal\SupportInbox;
use App\Models\WorkItem;
use App\Services\Portal\PortalRead;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\Support\Portal\CreatesPortal;
use Tests\TestCase;

class PortalUxTest extends TestCase
{
    use CreatesPortal, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpPortal();
    }

    public function test_attention_links_find_all_waiting_owned_work_and_filters_reset_pagination(): void
    {
        for ($i = 0; $i < 6; $i++) {
            $this->portalWork(['title' => 'Waiting '.$i, 'status' => 'waiting_for_customer']);
        }
        $this->portalWork(['title' => 'Already progressing']);
        $this->portalWork(['customer' => $this->portalOther->id, 'title' => 'PRIVATE other waiting', 'status' => 'waiting_for_customer']);
        Filament::setCurrentPanel(Filament::getPanel('account'));
        $this->actingAs($this->portalCustomer)->get('/')->assertOk()->assertSee('Needs your attention')->assertSee('queueFilter=waiting', false)->assertDontSee('PRIVATE other waiting');
        Livewire::withQueryParams(['queueFilter' => 'waiting'])->test(Projects::class)->assertSee('Waiting 5')->assertDontSee('Already progressing')->assertDontSee('PRIVATE other waiting')
            ->set('page', 2)->set('statusFilter', 'in_progress')->assertSet('page', 1)->call('clearFilters')->assertSee('Already progressing');
    }

    public function test_staff_filters_compose_and_never_expand_customer_ownership(): void
    {
        $actions = app(PortalActions::class);
        $high = $actions->createCase($this->portalCustomer, $this->caseInput(['subject' => 'Urgent unassigned case']), (string) Str::uuid());
        $actions->manageCase($this->portalAdmin, $high->public_id, ['expected_revision' => 1, 'status' => 'open', 'priority' => 'high'], (string) Str::uuid());
        $actions->createCase($this->portalOther, $this->caseInput(['subject' => 'Other customer case']), (string) Str::uuid());
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($this->portalAdmin);
        Livewire::test(CustomerSupport::class)->set('priorityFilter', 'high')->set('assignmentFilter', 'unassigned')->set('customerFilter', 'Synthetic Customer')
            ->assertSee('Urgent unassigned case')->assertDontSee('Other customer case')->set('customerFilter', 'Other private')->assertDontSee('Urgent unassigned case');
        $result = app(PortalRead::class)->cases($this->portalCustomer, filters: ['customer' => 'Other private']);
        $this->assertSame([$high->public_id], array_column($result['items'], 'public_id'));
    }

    public function test_dashboard_does_not_fetch_unused_conversations_and_work_authorization_does_not_load_history(): void
    {
        $work = $this->portalWork();
        DB::enableQueryLog();
        app(PortalRead::class)->home($this->portalCustomer);
        $queries = collect(DB::getQueryLog())->pluck('query');
        $this->assertFalse($queries->contains(fn ($sql) => preg_match('/select \* from ["`]support_cases["`]/i', $sql) === 1));
        DB::flushQueryLog();
        app(PortalRead::class)->authorizeWork($this->portalCustomer, $work->public_id);
        $queries = collect(DB::getQueryLog())->pluck('query')->implode(' ');
        $this->assertStringNotContainsString('work_updates', $queries);
        $this->assertStringNotContainsString('work_milestones', $queries);
        DB::disableQueryLog();
    }

    public function test_spa_does_not_prefetch_and_embedded_filters_do_not_take_over_parent_url(): void
    {
        $panel = Filament::getPanel('account');
        $this->assertTrue($panel->hasSpaMode());
        $this->assertFalse($panel->hasSpaPrefetching());
        Filament::setCurrentPanel($panel);
        $this->actingAs($this->portalCustomer);
        $inbox = Livewire::test(SupportInbox::class);
        $this->assertStringNotContainsString('"url":', $inbox->html());
        $inbox->set('queueFilter', 'unread')->assertSee('No matching conversations');
    }

    public function test_unread_conversation_stays_open_with_a_close_control_and_inline_reply_is_owned(): void
    {
        $actions = app(PortalActions::class);
        $case = $actions->createCase($this->portalCustomer, $this->caseInput(), (string) Str::uuid());
        $actions->reply($this->portalAdmin, $case->public_id, ['expected_revision' => 1, 'body' => 'A team reply'], (string) Str::uuid(), true);
        Filament::setCurrentPanel(Filament::getPanel('account'));
        $this->actingAs($this->portalCustomer);
        $inbox = Livewire::test(SupportInbox::class)->set('queueFilter', 'unread')->call('openConversation', $case->public_id)
            ->assertSee('A team reply')->assertSee('Close conversation')->assertDontSee('No matching conversations');
        $inbox->set('replyBody', 'My inline answer')->call('sendReply')->assertHasNoErrors()->assertSet('replyBody', '')->assertSee('My inline answer')
            ->call('closeConversation')->assertSee('No matching conversations');
        $this->assertDatabaseCount('support_messages', 2);
        $this->actingAs($this->portalOther);
        Livewire::test(SupportInbox::class)->call('openConversation', $case->public_id)->assertNotFound();
    }

    public function test_conflict_keeps_inline_draft_until_latest_version_is_explicitly_reviewed(): void
    {
        $actions = app(PortalActions::class);
        $case = $actions->createCase($this->portalCustomer, $this->caseInput(), (string) Str::uuid());
        Filament::setCurrentPanel(Filament::getPanel('account'));
        $this->actingAs($this->portalCustomer);
        $inbox = Livewire::test(SupportInbox::class)->call('openConversation', $case->public_id)->set('replyBody', 'Draft preserved');
        $actions->reply($this->portalAdmin, $case->public_id, ['expected_revision' => 1, 'body' => 'A newer team answer'], (string) Str::uuid(), true);
        $inbox->call('sendReply')->assertSet('commandConflict', true)->assertSet('replyBody', 'Draft preserved')->assertSee('A newer team answer');
        $this->assertDatabaseCount('support_messages', 1);
        $inbox->call('reviewLatestVersion')->assertSet('commandConflict', false)->assertSet('replyBody', 'Draft preserved')->call('sendReply')->assertSet('replyBody', '');
        $this->assertDatabaseCount('support_messages', 2);
    }

    public function test_customer_assignment_drawer_saves_in_context_and_milestone_row_prefills(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($this->portalAdmin);
        $data = $this->workInput();
        unset($data['customer']);
        $customerPage = Livewire::withQueryParams(['record' => $this->portalCustomer->id])->test(Customers::class);
        $this->assertTrue(Str::isUuid($customerPage->get('operationIds.assign')));
        $customerPage->callAction('assignWork', $data)->assertHasNoActionErrors()->assertNoRedirect()->assertSee($data['title']);
        $work = WorkItem::sole();
        $this->assertSame($this->portalCustomer->id, $work->user_id);
        app(PortalActions::class)->milestone($this->portalAdmin, $work->public_id, ['expected_revision' => 1, 'title' => 'Specific milestone', 'completed' => false], (string) Str::uuid());
        $milestone = $work->milestones()->sole();
        Livewire::withQueryParams(['record' => $work->public_id])->test(ClientWork::class)
            ->call('mountAction', 'milestone', ['milestone' => $milestone->public_id])->assertSchemaStateSet(['title' => 'Specific milestone', 'milestone' => $milestone->public_id]);
    }

    public function test_history_changes_cannot_send_a_draft_to_another_case_or_drop_it_on_new_request(): void
    {
        $actions = app(PortalActions::class);
        $first = $actions->createCase($this->portalCustomer, $this->caseInput(['subject' => 'First case']), (string) Str::uuid());
        $second = $actions->createCase($this->portalCustomer, $this->caseInput(['subject' => 'Second case']), (string) Str::uuid());
        Filament::setCurrentPanel(Filament::getPanel('account'));
        $this->actingAs($this->portalCustomer);
        $inbox = Livewire::test(SupportInbox::class)->call('openConversation', $first->public_id)->set('replyBody', 'Only for first case')
            ->set('record', $second->public_id)->call('sendReply')->assertSet('replyBody', 'Only for first case')->assertSee('Return to my draft');
        $this->assertDatabaseCount('support_messages', 0);
        $inbox->callAction('createCase', $this->caseInput(['subject' => 'New third case']))->assertHasNoActionErrors()->assertSet('replyBody', 'Only for first case')
            ->call('restoreReplyConversation')->assertSet('record', $first->public_id)->call('sendReply')->assertSet('replyBody', '');
        $this->assertSame('Only for first case', $first->messages()->sole()->body);
        $this->assertSame(0, $second->messages()->count());
    }

    public function test_unrelated_conflict_recovery_preserves_an_uncertain_reply_receipt(): void
    {
        $actions = app(PortalActions::class);
        $case = $actions->createCase($this->portalCustomer, $this->caseInput(), (string) Str::uuid());
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($this->portalAdmin);
        $inbox = Livewire::withQueryParams(['record' => $case->public_id])->test(CustomerSupport::class)->set('replyBody', 'Committed reply with a lost response');
        $key = $inbox->get('operationIds.reply');
        $actions->reply($this->portalAdmin, $case->public_id, ['expected_revision' => 1, 'body' => 'Committed reply with a lost response'], $key, true);
        $inbox->callAction('manageCase', ['status' => 'in_progress', 'priority' => 'normal'])->assertSet('commandConflict', true)
            ->call('reviewLatestVersion')->assertSet('operationIds.reply', $key)->assertSet('replyExpectedRevision', 1)
            ->call('sendReply')->assertSet('replyBody', '');
        $this->assertDatabaseCount('support_messages', 1);
    }

    public function test_expired_activation_links_offer_safe_recovery_without_sending_mail(): void
    {
        $url = URL::temporarySignedRoute('account-activation.edit', now()->subMinute(), [
            'user' => $this->portalCustomer->id, 'hash' => sha1($this->portalCustomer->email),
        ]);
        $this->get($url)->assertForbidden()->assertSee('This account link is unavailable')->assertSee('Request account instructions')->assertSee('Return to sign in');
        $this->assertDatabaseCount('jobs', 0);
    }
}
