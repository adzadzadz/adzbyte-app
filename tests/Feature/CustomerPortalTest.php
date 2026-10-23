<?php

namespace Tests\Feature;

use App\Actions\Portal\PortalActions;
use App\Enums\PortalCapability;
use App\Models\PortalEvent;
use App\Models\SupportCase;
use App\Models\WorkUpdate;
use App\Services\Portal\PortalRead;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Support\Portal\CreatesPortal;
use Tests\TestCase;

class CustomerPortalTest extends TestCase
{
    use CreatesPortal, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpPortal();
        config(['app.url' => 'https://app.example.test']);
    }

    public function test_staff_track_work_with_owned_milestones_progress_updates_and_idempotent_commands(): void
    {
        $a = app(PortalActions::class);
        $read = app(PortalRead::class);
        $key = (string) Str::uuid();
        $work = $a->createWork($this->portalAdmin, $this->workInput(), $key);
        $this->assertSame($work->id, $a->createWork($this->portalAdmin, $this->workInput(), strtoupper($key))->id);
        $this->assertDatabaseCount('work_items', 1);
        $milestoneKey = (string) Str::uuid();
        $input = ['expected_revision' => 1, 'title' => 'Design approved', 'completed' => true, 'target_date' => '2026-11-01'];
        $a->milestone($this->portalAdmin, $work->public_id, $input, $milestoneKey);
        $a->milestone($this->portalAdmin, $work->public_id, $input, $milestoneKey);
        $this->assertDatabaseCount('work_milestones', 1);
        $data = $read->work($this->portalCustomer, $work->public_id);
        $this->assertSame(1, $data['completed_milestone_count']);
        $this->assertSame(1, $data['milestone_count']);
        $this->assertSame('Synthetic Team', $data['assigned_to']);
        $this->assertTrue($data['unread']);
        $read->markWorkRead($this->portalCustomer, $work->public_id);
        $this->assertFalse($read->work($this->portalCustomer, $work->public_id)['unread']);
        $key = (string) Str::uuid();
        $a->publishUpdate($this->portalAdmin, $work->public_id, ['expected_revision' => 2, 'body' => 'Design is approved.'], $key);
        $a->publishUpdate($this->portalAdmin, $work->public_id, ['expected_revision' => 2, 'body' => 'Design is approved.'], $key);
        $this->assertDatabaseCount('work_updates', 1);
        $this->assertSame(3, $work->fresh()->revision);
        $this->assertTrue($read->work($this->portalCustomer, $work->public_id)['unread']);
        $this->assertSame('Design is approved.', $read->work($this->portalCustomer, $work->public_id)['updates'][0]['body']);
        $this->assertSame([], $read->work($this->portalCustomer, $work->public_id)['history']);
        $this->assertDatabaseCount('portal_events', 3);
        $this->assertDatabaseCount('portal_commands', 3);
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_support_works_without_purchase_and_messages_notes_triage_and_manual_reopening_are_separate(): void
    {
        $a = app(PortalActions::class);
        $read = app(PortalRead::class);
        $case = $a->createCase($this->portalCustomer, $this->caseInput(), (string) Str::uuid());
        $this->assertSame('normal', $case->fresh()->priority);
        $this->assertNull($case->work_item_id);
        $this->assertTrue($read->supportCase($this->portalAdmin, $case->public_id, true)['unread']);
        $read->markCaseRead($this->portalAdmin, $case->public_id, true);
        $this->assertFalse($read->supportCase($this->portalAdmin, $case->public_id, true)['unread']);
        $a->reply($this->portalAdmin, $case->public_id, ['expected_revision' => 1, 'body' => 'PRIVATE INTERNAL SECRET'], (string) Str::uuid(), true, true);
        $this->assertSame(1, $case->fresh()->revision, 'Notes must not invalidate a customer message.');
        $this->assertFalse($read->supportCase($this->portalCustomer, $case->public_id)['unread']);
        $this->assertArrayNotHasKey('notes', $read->supportCase($this->portalCustomer, $case->public_id));
        $this->assertSame('PRIVATE INTERNAL SECRET', $read->supportCase($this->portalAdmin, $case->public_id, true)['notes'][0]['body']);
        $key = (string) Str::uuid();
        $input = ['expected_revision' => 1, 'body' => 'We can help with that.'];
        $a->reply($this->portalAdmin, $case->public_id, $input, $key, true);
        $a->reply($this->portalAdmin, $case->public_id, $input, $key, true);
        $this->assertDatabaseCount('support_messages', 1);
        $this->assertDatabaseCount('support_notes', 1);
        $this->assertTrue($read->supportCase($this->portalCustomer, $case->public_id)['unread']);
        $a->manageCase($this->portalAdmin, $case->public_id, ['expected_revision' => 2, 'status' => 'resolved', 'priority' => 'high', 'assigned_to' => $this->portalAdmin->id], (string) Str::uuid());
        $this->denied(409, fn () => $a->reply($this->portalCustomer, $case->public_id, ['expected_revision' => 3, 'body' => 'More information.'], (string) Str::uuid()));
        $a->manageCase($this->portalAdmin, $case->public_id, ['expected_revision' => 3, 'status' => 'open', 'priority' => 'normal'], (string) Str::uuid());
        $a->reply($this->portalCustomer, $case->public_id, ['expected_revision' => 4, 'body' => 'Thanks, here are details.'], (string) Str::uuid());
        $this->assertDatabaseCount('support_messages', 2);
        $this->assertTrue($read->supportCase($this->portalAdmin, $case->public_id, true)['unread']);
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_customers_cannot_read_or_link_other_customer_work_or_support_even_with_staff_capability(): void
    {
        $a = app(PortalActions::class);
        $read = app(PortalRead::class);
        $work = $this->portalWork();
        $case = $a->createCase($this->portalCustomer, $this->caseInput(['work' => $work->public_id]), (string) Str::uuid());
        $this->actingAs($this->portalOther);
        foreach (['/api/v1/portal/works/'.$work->public_id, '/api/v1/portal/cases/'.$case->public_id] as $path) {
            $this->getJson($path)->assertNotFound();
        }
        $this->postJson('/api/v1/portal/cases', $this->caseInput(['work' => $work->public_id]), $this->headers())->assertNotFound();
        $this->postJson('/api/v1/portal/cases/'.$case->public_id.'/messages', ['expected_revision' => 1, 'body' => 'Intrusion'], $this->headers())->assertNotFound();
        $this->postJson('/api/v1/portal/works/'.$work->public_id.'/read', [], $this->headers())->assertNotFound();
        $this->postJson('/api/v1/portal/cases/'.$case->public_id.'/read', [], $this->headers())->assertNotFound();
        $this->portalOther->givePermissionTo(PortalCapability::ViewWork->value);
        $this->denied(403, fn () => $read->works($this->portalOther, null, true));
        $this->assertSame([], $read->works($this->portalOther)['items']);
        $this->assertSame([], $read->cases($this->portalOther)['items']);
        $this->assertDatabaseCount('support_cases', 1);
        $this->assertDatabaseCount('support_messages', 0);
    }

    public function test_stale_revisions_conflicting_replays_unknown_fields_and_invalid_input_are_rejected_atomically(): void
    {
        $a = app(PortalActions::class);
        $work = $this->portalWork();
        $key = (string) Str::uuid();
        $a->publishUpdate($this->portalAdmin, $work->public_id, ['expected_revision' => 1, 'body' => 'First update'], $key);
        $this->denied(409, fn () => $a->publishUpdate($this->portalAdmin, $work->public_id, ['expected_revision' => 2, 'body' => 'Different'], $key));
        $this->denied(409, fn () => $a->publishUpdate($this->portalAdmin, $work->public_id, ['expected_revision' => 1, 'body' => 'Stale'], (string) Str::uuid()));
        foreach ([['title' => ' '], ['type' => 'subscription'], ['status' => 'paid'], ['assigned_to' => $this->portalCustomer->id], ['customer' => $this->portalAdmin->id], ['user_id' => $this->portalOther->id]] as $change) {
            $this->invalid(fn () => $a->createWork($this->portalAdmin, $this->workInput($change), (string) Str::uuid()));
        }
        $this->invalid(fn () => $a->createCase($this->portalCustomer, $this->caseInput(['priority' => 'high']), (string) Str::uuid()));
        $this->invalid(fn () => $a->createCase($this->portalCustomer, $this->caseInput(['description' => str_repeat('x', 10001)]), (string) Str::uuid()));
        $this->assertDatabaseCount('work_items', 1);
        $this->invalid(fn () => $a->publishUpdate($this->portalAdmin, $work->public_id, ['expected_revision' => '2', 'body' => 'String revision'], (string) Str::uuid()));
        $this->assertDatabaseCount('work_updates', 1);
        $this->assertDatabaseCount('portal_commands', 2);
    }

    public function test_revoked_staff_cannot_replay_commands_read_records_or_see_notes_and_roles_alone_grant_no_management(): void
    {
        $a = app(PortalActions::class);
        $key = (string) Str::uuid();
        $work = $a->createWork($this->portalAdmin, $this->workInput(), $key);
        $this->portalAdmin->revokePermissionTo(PortalCapability::ManageWork->value);
        $this->denied(403, fn () => $a->createWork($this->portalAdmin, $this->workInput(), $key));
        $case = $a->createCase($this->portalCustomer, $this->caseInput(), (string) Str::uuid());
        $a->reply($this->portalAdmin, $case->public_id, ['expected_revision' => 1, 'body' => 'Private'], (string) Str::uuid(), true, true);
        $this->portalAdmin->revokePermissionTo(PortalCapability::NoteCases->value);
        $this->assertArrayNotHasKey('notes', app(PortalRead::class)->supportCase($this->portalAdmin, $case->public_id, true));
        $this->portalAdmin->revokePermissionTo(PortalCapability::ViewCases->value);
        $this->denied(403, fn () => app(PortalRead::class)->supportCase($this->portalAdmin, $case->public_id, true));
        $this->portalAdmin->syncPermissions([]);
        $this->denied(403, fn () => app(PortalRead::class)->works($this->portalAdmin, null, true));
        $this->actingAs($this->portalAdmin)->get('/admin/client-work')->assertForbidden();
        $this->get('/admin/customer-support')->assertForbidden();
    }

    public function test_staff_categories_are_optional_active_validated_and_audited(): void
    {
        $a = app(PortalActions::class);
        $read = app(PortalRead::class);
        $key = (string) Str::uuid();
        $category = $a->category($this->portalAdmin, ['name' => 'Account help', 'active' => true], $key);
        $a->category($this->portalAdmin, ['name' => 'Account help', 'active' => true], $key);
        $this->assertCount(1, $read->categories());
        $a->createCase($this->portalCustomer, $this->caseInput(['category' => $category->public_id]), (string) Str::uuid());
        $a->category($this->portalAdmin, ['category' => $category->public_id, 'name' => 'Account help', 'active' => false], (string) Str::uuid());
        $this->assertSame([], $read->categories());
        $this->assertCount(1, $read->categories(true));
        $this->assertSame('Account help', $read->cases($this->portalCustomer)['items'][0]['category']);
        $this->actingAs($this->portalCustomer)->postJson('/api/v1/portal/cases', $this->caseInput(['category' => $category->public_id]), $this->headers())->assertNotFound();
        $this->assertDatabaseCount('support_cases', 1);
        $this->assertSame(2, PortalEvent::whereNotNull('support_category_id')->count());
    }

    public function test_public_api_projects_services_messages_pagination_privacy_and_read_state(): void
    {
        $a = app(PortalActions::class);
        $work = $this->portalWork();
        $this->portalWork(['type' => 'service', 'title' => 'Monthly care']);
        $this->actingAs($this->portalCustomer)->getJson('/api/v1/portal/works?type=service')->assertOk()->assertJsonCount(1, 'data.items')->assertJsonPath('data.items.0.title', 'Monthly care');
        $this->getJson('/api/v1/portal/works/'.$work->public_id)->assertOk()->assertJsonPath('data.title', $work->title)->assertJsonMissingPath('data.user_id')->assertJsonMissingPath('data.customer');
        $key = (string) Str::uuid();
        $this->postJson('/api/v1/portal/cases', $this->caseInput(['work' => $work->public_id]), $this->headers($key))->assertOk()->assertJsonPath('data.work.public_id', $work->public_id);
        $this->postJson('/api/v1/portal/cases', $this->caseInput(['work' => $work->public_id]), $this->headers($key))->assertOk();
        $case = SupportCase::sole();
        $a->reply($this->portalAdmin, $case->public_id, ['expected_revision' => 1, 'body' => 'PRIVATE API SECRET'], (string) Str::uuid(), true, true);
        $this->postJson('/api/v1/portal/cases/'.$case->public_id.'/messages', ['expected_revision' => 1, 'body' => 'My message'], $this->headers())->assertOk()->assertJsonCount(1, 'data.messages')->assertJsonPath('data.messages.0.from_customer', true)->assertJsonMissingPath('data.notes')->assertDontSee('PRIVATE API SECRET');
        $a->reply($this->portalAdmin, $case->public_id, ['expected_revision' => 2, 'body' => 'Public reply'], (string) Str::uuid(), true);
        $this->getJson('/api/v1/portal/cases/'.$case->public_id)->assertOk()->assertJsonPath('data.unread', true)->assertJsonMissingPath('data.notes');
        $this->postJson('/api/v1/portal/cases/'.$case->public_id.'/read', [], $this->headers())->assertOk()->assertJsonPath('data.read', true);
        $this->getJson('/api/v1/portal/cases/'.$case->public_id)->assertJsonPath('data.unread', false);
        $this->getJson('/api/v1/portal/works?owner=1')->assertUnprocessable();
        $this->getJson('/api/v1/portal/works?page=0')->assertUnprocessable();
        $this->getJson('/api/v1/portal/cases?search[]=invalid')->assertUnprocessable();
        $this->postJson('/api/v1/portal/cases', $this->caseInput(), ['Origin' => 'https://evil.test'])->assertForbidden();
        $this->postJson('/api/v1/portal/cases', $this->caseInput(), ['Origin' => 'https://app.example.test'])->assertUnprocessable();
        $this->portalCustomer->email_verified_at = null;
        $this->portalCustomer->save();
        $this->getJson('/api/v1/portal/works')->assertForbidden();
        $this->postJson('/api/v1/portal/cases', $this->caseInput(), $this->headers())->assertForbidden();
    }

    public function test_history_cannot_be_changed_or_deleted_and_milestones_cannot_cross_work_records(): void
    {
        $a = app(PortalActions::class);
        $work = $this->portalWork();
        $other = $this->portalWork(['customer' => $this->portalOther->id]);
        $a->milestone($this->portalAdmin, $work->public_id, ['expected_revision' => 1, 'title' => 'Scope confirmed', 'completed' => false], (string) Str::uuid());
        $this->actingAs($this->portalAdmin);
        try {
            $a->milestone($this->portalAdmin, $other->public_id, ['expected_revision' => 1, 'title' => 'Hijack', 'completed' => true, 'milestone' => $work->milestones()->sole()->public_id], (string) Str::uuid());
            $this->fail('Wrong milestone accepted');
        } catch (ModelNotFoundException) {
            $this->assertTrue(true);
        }
        $event = PortalEvent::firstOrFail();
        $this->invalid(fn () => $event->update(['action' => 'changed']));
        $this->invalid(fn () => $event->delete());
        $a->publishUpdate($this->portalAdmin, $work->public_id, ['expected_revision' => 2, 'body' => 'Immutable update'], (string) Str::uuid());
        $this->invalid(fn () => WorkUpdate::sole()->delete());
    }

    private function headers(?string $key = null): array
    {
        return ['Origin' => 'https://app.example.test', 'Idempotency-Key' => $key ?? (string) Str::uuid()];
    }

    private function denied(int $status, \Closure $action): void
    {
        try {
            $action();
            $this->fail('Expected denial');
        } catch (HttpException $e) {
            $this->assertSame($status, $e->getStatusCode());
        }
    }

    private function invalid(\Closure $action): void
    {
        try {
            $action();
            $this->fail('Expected invalid input');
        } catch (ValidationException) {
            $this->assertTrue(true);
        }
    }
}
