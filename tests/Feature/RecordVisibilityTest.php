<?php

namespace Tests\Feature;

use App\Actions\Portal\PortalActions;
use App\Actions\Users\UpdateManagedUser;
use App\Filament\Pages\DataRecords;
use App\Filament\Pages\Portal\ClientWork;
use App\Filament\Pages\Portal\CustomerSupport;
use App\Models\RecordDeletion;
use App\Models\RecordDeletionEvent;
use App\Models\Role;
use App\Models\SupportCase;
use App\Models\SupportMessage;
use App\Models\User;
use App\Models\WorkMilestone;
use App\Services\Portal\PortalRead;
use App\Support\Records\RecordTypes;
use App\Support\Records\RecordVisibility;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Support\Portal\CreatesPortal;
use Tests\TestCase;

class RecordVisibilityTest extends TestCase
{
    use CreatesPortal, RefreshDatabase;

    private User $super;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpPortal();
        $this->super = User::factory()->create();
        $this->super->assignRole('super_admin');
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($this->super);
    }

    public function test_soft_deletion_preserves_original_row_and_audits_only_real_transitions(): void
    {
        $work = $this->portalWork();
        $before = $work->fresh()->getAttributes();
        $visibility = app(RecordVisibility::class);
        $visibility->delete($this->super, $work, 0);
        $visibility->delete($this->super, $work, 0);
        $this->assertSame($before, $work->fresh()->getAttributes());
        $this->assertTrue(RecordVisibility::isDeleted($work));
        $this->assertSame(1, RecordDeletionEvent::count());
        $this->assertSame([], app(PortalRead::class)->works($this->portalCustomer)['items']);
        $visibility->restore($this->super, $work, 1);
        $this->assertSame($before, $work->fresh()->getAttributes());
        $this->assertFalse(RecordVisibility::isDeleted($work));
        $this->assertSame(2, RecordDeletionEvent::count());
        $this->assertSame(2, RecordDeletion::sole()->version);
        $this->assertSame($work->public_id, app(PortalRead::class)->works($this->portalCustomer)['items'][0]['public_id']);
        $this->expectException(HttpException::class);
        $visibility->delete($this->super, $work, 0);
    }

    public function test_parent_restore_does_not_restore_individually_deleted_children(): void
    {
        $work = $this->portalWork();
        app(PortalActions::class)->milestone($this->portalAdmin, $work->public_id, ['expected_revision' => 1, 'title' => 'Retained milestone', 'completed' => false], (string) Str::uuid());
        $milestone = WorkMilestone::sole();
        $case = app(PortalActions::class)->createCase($this->portalCustomer, $this->caseInput(['work' => $work->public_id]), (string) Str::uuid());
        $message = SupportMessage::create(['support_case_id' => $case->id, 'actor_id' => $this->portalCustomer->id, 'body' => 'Retained message']);
        $visibility = app(RecordVisibility::class);
        $visibility->delete($this->super, $milestone);
        $visibility->delete($this->super, $work);
        $this->assertTrue(RecordVisibility::isDeleted($case));
        $this->assertTrue(RecordVisibility::isDeleted($message));
        $this->assertSame(0, RecordVisibility::query(SupportCase::query())->count());
        try {
            $visibility->restore($this->super, $case);
            $this->fail('A parent-hidden record was reported restored.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('Restore the owning', $exception->getMessage());
        }
        $visibility->restore($this->super, $work);
        $this->assertFalse(RecordVisibility::isDeleted($case));
        $this->assertTrue(RecordVisibility::isDeleted($milestone));
        $this->assertSame(0, app(PortalRead::class)->work($this->portalCustomer, $work->public_id)['milestone_count']);
        $this->assertDatabaseHas('support_messages', ['id' => $message->id, 'body' => 'Retained message']);
    }

    public function test_deleted_work_and_customer_are_not_mutable_through_existing_commands(): void
    {
        $work = $this->portalWork();
        app(RecordVisibility::class)->delete($this->super, $work);
        try {
            app(PortalActions::class)->updateWork($this->portalAdmin, $work->public_id, ['expected_revision' => 1, ...array_diff_key($this->workInput(), ['customer' => 1, 'type' => 1])], (string) Str::uuid());
            $this->fail('Deleted work was updated');
        } catch (HttpException $exception) {
            $this->assertSame(404, $exception->getStatusCode());
        }
        $this->assertSame(1, $work->fresh()->revision);
        app(RecordVisibility::class)->delete($this->super, $this->portalCustomer);
        $this->expectException(HttpException::class);
        app(PortalActions::class)->createWork($this->portalAdmin, $this->workInput(), (string) Str::uuid());
    }

    public function test_deleted_accounts_are_blocked_and_old_sessions_cannot_reach_the_portal(): void
    {
        app(RecordVisibility::class)->delete($this->super, $this->portalCustomer);
        $this->assertFalse($this->portalCustomer->canAccessPanel(Filament::getPanel('account')));
        $this->actingAs($this->portalCustomer)->get('/')->assertForbidden();
        $this->assertGuest('web');
        $this->get('/login')->assertOk();
        Notification::fake();
        $panel = Filament::getPanel('account');
        Filament::setCurrentPanel($panel);
        Livewire::test($panel->getRequestPasswordResetRouteAction())->fillForm(['email' => $this->portalCustomer->email])->call('request')->assertHasNoFormErrors();
        Notification::assertNothingSent();
        $this->assertDatabaseCount('password_reset_tokens', 0);
    }

    public function test_manage_permission_is_explicit_and_cannot_restore_a_higher_privilege_user(): void
    {
        $work = $this->portalWork();
        $this->actingAs($this->portalAdmin)->get(DataRecords::getUrl())->assertForbidden();
        $this->actingAs($this->portalCustomer)->get(DataRecords::getUrl())->assertForbidden();
        $this->portalAdmin->givePermissionTo('Manage:DataRecords');
        $otherSuper = User::factory()->create();
        $otherSuper->assignRole('super_admin');
        app(RecordVisibility::class)->delete($this->super, $otherSuper);
        try {
            app(RecordVisibility::class)->restore($this->portalAdmin, $otherSuper);
            $this->fail('A lower privilege user restored a super administrator');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }
        app(RecordVisibility::class)->delete($this->portalAdmin, $work);
        $this->assertTrue(RecordVisibility::isDeleted($work));
    }

    public function test_self_and_last_active_super_admin_protections_include_deleted_accounts(): void
    {
        try {
            app(RecordVisibility::class)->delete($this->super, $this->super);
            $this->fail('Self deletion succeeded');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('own signed-in', $exception->getMessage());
        }
        $otherSuper = User::factory()->create();
        $otherSuper->assignRole('super_admin');
        app(RecordVisibility::class)->delete($this->super, $otherSuper);
        $this->expectException(ValidationException::class);
        app(UpdateManagedUser::class)->handle($this->super, $this->super, ['name' => $this->super->name, 'email' => $this->super->email], ['administrator']);
    }

    public function test_deleted_roles_cannot_be_assigned_and_assigned_roles_cannot_be_deleted(): void
    {
        $role = Role::create(['name' => 'custom_support', 'guard_name' => 'web']);
        $this->portalCustomer->assignRole($role);
        try {
            app(RecordVisibility::class)->delete($this->super, $role);
            $this->fail('Assigned role deleted');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('Reassign users', $exception->getMessage());
        }
        $this->portalCustomer->removeRole($role);
        app(RecordVisibility::class)->delete($this->super, $role);
        $this->expectException(HttpException::class);
        app(UpdateManagedUser::class)->handle($this->super, $this->portalCustomer, ['name' => $this->portalCustomer->name, 'email' => $this->portalCustomer->email], ['customer', 'custom_support']);
    }

    public function test_data_management_crud_controls_and_parent_recovery_are_actionable(): void
    {
        $work = $this->portalWork();
        $case = app(PortalActions::class)->createCase($this->portalCustomer, $this->caseInput(['work' => $work->public_id]), (string) Str::uuid());
        Livewire::test(DataRecords::class)->assertSuccessful()->assertCanSeeTableRecords([$work])->assertSee('Create record')
            ->callAction(TestAction::make('deleteRecord')->table($work))->assertNotified('Record deleted · data retained');
        Livewire::test(DataRecords::class)->filterTable('record_visibility', 'deleted')->assertCanSeeTableRecords([$work])
            ->callAction(TestAction::make('restoreRecord')->table($work))->assertNotified('Record restored');
        app(RecordVisibility::class)->delete($this->super, $work);
        Livewire::withQueryParams(['type' => 'cases', 'record' => (string) $case->id])->test(DataRecords::class)->assertSuccessful()
            ->assertSee('Hidden because its owning record is deleted.')->assertSee('Review and restore '.$work->title);
    }

    public function test_all_registered_types_render_and_no_secrets_are_exposed_in_identity_details(): void
    {
        foreach (array_keys(RecordTypes::TYPES) as $type) {
            Livewire::withQueryParams(['type' => $type])->test(DataRecords::class)->assertSuccessful();
        }
        Livewire::withQueryParams(['type' => 'users', 'record' => (string) $this->super->id])->test(DataRecords::class)
            ->assertSee($this->super->email)->assertDontSee($this->super->password)->assertDontSee('remember_token');
        Livewire::withQueryParams(['type' => 'sessions'])->test(DataRecords::class)->assertNotFound();
    }

    public function test_staff_can_create_and_update_request_details_with_history_and_idempotency(): void
    {
        $key = (string) Str::uuid();
        $input = ['customer' => $this->portalCustomer->id, ...$this->caseInput()];
        $case = app(PortalActions::class)->createStaffCase($this->portalAdmin, $input, $key);
        $this->assertSame($case->id, app(PortalActions::class)->createStaffCase($this->portalAdmin, $input, $key)->id);
        $updated = app(PortalActions::class)->updateCaseDetails($this->portalAdmin, $case->public_id,
            ['expected_revision' => 1, 'type' => 'request', 'subject' => 'Corrected request', 'description' => 'Corrected initial details'], (string) Str::uuid());
        $this->assertSame(2, $updated->revision);
        $this->assertDatabaseHas('portal_events', ['support_case_id' => $case->id, 'action' => 'case_details_updated']);
        Livewire::withQueryParams(['record' => $case->public_id])->test(CustomerSupport::class)->assertActionExists('editCaseDetails')->assertActionExists('deleteRecord');
        Livewire::withQueryParams(['record' => $this->portalWork()->public_id])->test(ClientWork::class)->assertActionExists('deleteRecord');
    }

    public function test_minimal_record_manager_is_not_shown_unauthorized_create_or_edit_destinations(): void
    {
        $minimal = User::factory()->create();
        $minimal->assignRole('administrator');
        $minimal->givePermissionTo('Manage:DataRecords');
        $work = $this->portalWork();
        $this->actingAs($minimal);
        Livewire::test(DataRecords::class)->assertSuccessful()->assertActionHidden('createRecord')
            ->assertActionHidden(TestAction::make('editRecord')->table($work));
        Livewire::withQueryParams(['type' => 'roles'])->test(DataRecords::class)->assertActionHidden('createRecord');
    }

    public function test_confirmation_is_bound_to_record_and_type_and_cannot_follow_selection_changes(): void
    {
        $work = $this->portalWork();
        $other = $this->portalWork(['title' => 'Other work']);
        Livewire::test(DataRecords::class)->mountAction(TestAction::make('deleteRecord')->table($work))
            ->fillForm(['record_id' => (string) $other->id])->callMountedAction()->assertNotified('Record unchanged');
        $this->assertFalse(RecordVisibility::isDeleted($work));
        $this->assertFalse(RecordVisibility::isDeleted($other));
        $this->assertDatabaseCount('record_deletions', 0);
    }

    public function test_existing_bearer_token_and_signed_activation_cannot_bypass_deleted_identity(): void
    {
        $token = $this->portalCustomer->createToken('synthetic-visibility-test')->plainTextToken;
        $this->app['auth']->forgetGuards();
        $this->withHeader('Authorization', 'Bearer '.$token)->getJson('/api/v1/me')->assertOk();
        app(RecordVisibility::class)->delete($this->super, $this->portalCustomer);
        $this->app['auth']->forgetGuards();
        $this->withHeader('Authorization', 'Bearer '.$token)->getJson('/api/v1/me')->assertForbidden();
        $pending = User::factory()->unverified()->create();
        $pending->assignRole('customer');
        $url = URL::temporarySignedRoute('account-activation.edit', now()->addHour(), ['user' => $pending->id, 'hash' => sha1($pending->email)]);
        app(RecordVisibility::class)->delete($this->super, $pending);
        $this->app['auth']->forgetGuards();
        $this->app['auth']->shouldUse('web');
        $this->withoutHeader('Authorization')->get($url)->assertNotFound()->assertDontSee($pending->email);
        $this->assertDatabaseCount('personal_access_tokens', 1);
    }
}
