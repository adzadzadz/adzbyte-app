<?php

namespace App\Actions\Portal;

use App\Enums\PortalCapability;
use App\Models\PortalCommand;
use App\Models\PortalEvent;
use App\Models\SupportCase;
use App\Models\SupportCategory;
use App\Models\SupportMessage;
use App\Models\SupportNote;
use App\Models\User;
use App\Models\WorkItem;
use App\Models\WorkMilestone;
use App\Models\WorkUpdate;
use App\Support\Database\RetryingTransaction;
use App\Support\PortalAccess;
use App\Support\PortalOptions;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class PortalActions
{
    public function createWork(User $actor, array $input, string $key): WorkItem
    {
        return $this->command($actor, 'work.create', $input, $key, function (User $actor, array $data): WorkItem {
            Gate::forUser($actor)->authorize('create', WorkItem::class);
            $data = $this->validate($data, [
                'customer' => ['required', 'integer'], 'type' => ['required', Rule::in(array_keys(PortalOptions::WORK_TYPES))],
                ...$this->workRules(),
            ]);
            $customer = User::query()->find($data['customer']);
            if (! $customer || ! $customer->hasRole('customer')) {
                $this->invalid('customer', 'Select a customer account.');
            }
            unset($data['customer']);
            $this->assignee($data['assigned_to'] ?? null, PortalCapability::ViewWork);
            $work = WorkItem::create(['public_id' => (string) Str::ulid(), 'user_id' => $customer->id, ...$data]);
            $this->event($actor, $work, 'work_created', $data);

            return $work;
        });
    }

    public function updateWork(User $actor, string $reference, array $input, string $key): WorkItem
    {
        return $this->command($actor, 'work.update:'.$reference, $input, $key, function (User $actor, array $data) use ($reference): WorkItem {
            $work = WorkItem::where('public_id', $reference)->lockForUpdate()->firstOrFail();
            Gate::forUser($actor)->authorize('update', $work);
            $data = $this->validate($data, ['expected_revision' => ['required', 'integer:strict', 'min:1'], ...$this->workRules()]);
            $this->revision($work, $data);
            $this->assignee($data['assigned_to'] ?? null, PortalCapability::ViewWork);
            $before = $work->only(array_keys($data));
            $work->fill($data);
            $work->customer_read_at = null;
            $work->revision++;
            $work->save();
            $this->event($actor, $work, 'work_updated', ['before' => $before, 'after' => $data]);

            return $work;
        });
    }

    public function milestone(User $actor, string $reference, array $input, string $key): WorkItem
    {
        return $this->command($actor, 'work.milestone:'.$reference, $input, $key, function (User $actor, array $data) use ($reference): WorkItem {
            $work = WorkItem::where('public_id', $reference)->lockForUpdate()->firstOrFail();
            Gate::forUser($actor)->authorize('update', $work);
            $data = $this->validate($data, ['expected_revision' => ['required', 'integer:strict', 'min:1'], 'milestone' => ['nullable', 'ulid'],
                'title' => ['required', 'string', 'max:255'], 'target_date' => ['nullable', 'date_format:Y-m-d'], 'completed' => ['required', 'boolean']]);
            $this->revision($work, $data);
            $milestone = isset($data['milestone'])
                ? $work->milestones()->where('public_id', $data['milestone'])->firstOrFail()
                : new WorkMilestone(['work_item_id' => $work->id, 'public_id' => (string) Str::ulid()]);
            if (! $milestone->exists && $work->milestones()->count() >= 100) {
                $this->invalid('title', 'A work record can have up to 100 milestones.');
            }
            $milestone->fill(['title' => $data['title'], 'target_date' => $data['target_date'] ?? null,
                'completed_at' => $data['completed'] ? ($milestone->completed_at ?? now()) : null]);
            $milestone->save();
            $work->customer_read_at = null;
            $work->revision++;
            $work->save();
            $this->event($actor, $work, 'milestone_updated', ['milestone' => $milestone->public_id, 'title' => $milestone->title, 'completed' => (bool) $data['completed']]);

            return $work;
        });
    }

    public function publishUpdate(User $actor, string $reference, array $input, string $key): WorkItem
    {
        return $this->command($actor, 'work.publish:'.$reference, $input, $key, function (User $actor, array $data) use ($reference): WorkItem {
            $work = WorkItem::where('public_id', $reference)->lockForUpdate()->firstOrFail();
            Gate::forUser($actor)->authorize('update', $work);
            $data = $this->validate($data, ['expected_revision' => ['required', 'integer:strict', 'min:1'], 'body' => ['required', 'string', 'max:10000']]);
            $this->revision($work, $data);
            WorkUpdate::create(['work_item_id' => $work->id, 'actor_id' => $actor->id, 'body' => $data['body']]);
            $work->customer_read_at = null;
            $work->revision++;
            $work->save();
            $this->event($actor, $work, 'update_published', []);

            return $work;
        });
    }

    public function createCase(User $actor, array $input, string $key): SupportCase
    {
        return $this->command($actor, 'case.create', $input, $key, function (User $actor, array $data): SupportCase {
            abort_unless(PortalAccess::customer($actor), 403);
            $data = $this->validate($data, ['type' => ['required', Rule::in(array_keys(PortalOptions::CASE_TYPES))],
                'subject' => ['required', 'string', 'max:255'], 'description' => ['required', 'string', 'max:10000'],
                'work' => ['nullable', 'ulid'], 'category' => ['nullable', 'ulid']]);
            $work = isset($data['work']) ? WorkItem::where('public_id', $data['work'])->where('user_id', $actor->id)->firstOrFail() : null;
            $category = isset($data['category']) ? SupportCategory::where('public_id', $data['category'])->where('active', true)->firstOrFail() : null;
            $case = SupportCase::create(['public_id' => (string) Str::ulid(), 'user_id' => $actor->id,
                'work_item_id' => $work?->id, 'support_category_id' => $category?->id, 'type' => $data['type'],
                'subject' => $data['subject'], 'description' => $data['description'], 'public_updated_at' => now(),
                'customer_read_at' => now(), 'last_message_at' => now()]);
            $this->event($actor, $case, 'case_created', ['type' => $case->type, 'status' => 'open']);

            return $case;
        });
    }

    public function reply(User $actor, string $reference, array $input, string $key, bool $staff = false, bool $note = false): SupportCase
    {
        return $this->command($actor, ($note ? 'case.note:' : 'case.reply:').($staff ? 'staff:' : 'customer:').$reference, $input, $key,
            function (User $actor, array $data) use ($reference, $staff, $note): SupportCase {
                $case = $this->caseFor($actor, $reference, $staff, true);
                if ($staff) {
                    Gate::forUser($actor)->authorize($note ? 'note' : 'reply', $case);
                } else {
                    abort_if($note, 403);
                }
                $data = $this->validate($data, ['expected_revision' => ['required', 'integer:strict', 'min:1'], 'body' => ['required', 'string', 'max:10000']]);
                $this->revision($case, $data);
                abort_if(! $note && in_array($case->status, ['resolved', 'closed'], true), 409, 'This case is resolved or closed. Staff must reopen it before a new reply.');
                ($note ? SupportNote::class : SupportMessage::class)::create(['support_case_id' => $case->id, 'actor_id' => $actor->id, 'body' => $data['body']]);
                if (! $note) {
                    $case->revision++;
                    $case->last_message_at = now();
                    $case->public_updated_at = now();
                    $case->{$staff ? 'customer_read_at' : 'staff_read_at'} = null;
                    $case->{$staff ? 'staff_read_at' : 'customer_read_at'} = now();
                }
                if (! $note) {
                    $case->save();
                }
                $this->event($actor, $case, $note ? 'internal_note_added' : 'message_sent', []);

                return $case;
            });
    }

    public function manageCase(User $actor, string $reference, array $input, string $key): SupportCase
    {
        return $this->command($actor, 'case.manage:'.$reference, $input, $key, function (User $actor, array $data) use ($reference): SupportCase {
            $case = $this->caseFor($actor, $reference, true, true);
            Gate::forUser($actor)->authorize('update', $case);
            $data = $this->validate($data, ['expected_revision' => ['required', 'integer:strict', 'min:1'],
                'status' => ['required', Rule::in(array_keys(PortalOptions::CASE_STATUSES))],
                'priority' => ['required', Rule::in(array_keys(PortalOptions::PRIORITIES))],
                'assigned_to' => ['nullable', 'integer'], 'category' => ['nullable', 'ulid']]);
            $this->revision($case, $data);
            $this->assignee($data['assigned_to'] ?? null, PortalCapability::ViewCases);
            $category = isset($data['category']) ? SupportCategory::where('public_id', $data['category'])->firstOrFail() : null;
            $before = $case->only(['status', 'priority', 'assigned_to', 'support_category_id']);
            $case->fill(['status' => $data['status'], 'priority' => $data['priority'], 'assigned_to' => $data['assigned_to'] ?? null, 'support_category_id' => $category?->id]);
            $case->revision++;
            $case->public_updated_at = now();
            $case->customer_read_at = null;
            $case->save();
            $this->event($actor, $case, 'case_updated', ['before' => $before, 'after' => $case->only(array_keys($before))]);

            return $case;
        });
    }

    public function category(User $actor, array $input, string $key): SupportCategory
    {
        return $this->command($actor, 'category.save', $input, $key, function (User $actor, array $data): SupportCategory {
            abort_unless(PortalAccess::staff($actor, PortalCapability::Categories), 403);
            $data = $this->validate($data, ['category' => ['nullable', 'ulid'], 'name' => ['required', 'string', 'max:100'], 'active' => ['required', 'boolean']]);
            $category = isset($data['category']) ? SupportCategory::where('public_id', $data['category'])->lockForUpdate()->firstOrFail()
                : new SupportCategory(['public_id' => (string) Str::ulid()]);
            Validator::make($data, ['name' => [Rule::unique('support_categories')->ignore($category->id)]])->validate();
            $before = $category->only(['name', 'active']);
            $category->fill(['name' => $data['name'], 'active' => $data['active']]);
            $category->save();
            $this->event($actor, $category, 'category_updated', ['before' => $before, 'after' => $category->only(['name', 'active'])]);

            return $category;
        });
    }

    public function caseFor(User $actor, string $reference, bool $staff = false, bool $lock = false): SupportCase
    {
        abort_unless($staff ? PortalAccess::staff($actor, PortalCapability::ViewCases) : PortalAccess::customer($actor), 403);
        $query = SupportCase::where('public_id', $reference)->when(! $staff, fn ($query) => $query->where('user_id', $actor->id));
        $case = ($lock ? $query->lockForUpdate() : $query)->firstOrFail();
        Gate::forUser($actor)->authorize('view', $case);

        return $case;
    }

    private function workRules(): array
    {
        return ['title' => ['required', 'string', 'max:255'], 'summary' => ['required', 'string', 'max:10000'],
            'status' => ['required', Rule::in(array_keys(PortalOptions::WORK_STATUSES))], 'next_step' => ['nullable', 'string', 'max:2000'],
            'target_date' => ['nullable', 'date_format:Y-m-d'], 'assigned_to' => ['nullable', 'integer']];
    }

    private function command(User $actor, string $operation, array $input, string $key, Closure $action): Model
    {
        Validator::make(['operation_id' => $key], ['operation_id' => ['required', 'uuid']])->validate();
        $key = strtolower($key);
        $fingerprint = hash('sha256', $operation.'|'.json_encode($this->canonical($input), JSON_THROW_ON_ERROR));

        return RetryingTransaction::run(function () use ($actor, $input, $operation, $key, $fingerprint, $action): Model {
            $fresh = User::whereKey($actor->id)->lockForUpdate()->firstOrFail();
            // Authorization runs before retry receipts too: a revoked actor cannot
            // retrieve a previously accessible record by replaying a command.
            $this->authorizeOperation($fresh, $operation);
            $receipt = PortalCommand::where('actor_id', $fresh->id)->where('operation_id', $key)->first();
            if ($receipt) {
                abort_unless(hash_equals($receipt->fingerprint, $fingerprint), 409, 'This operation key was already used for different details.');
                $model = match ($receipt->result_type) {
                    'work' => WorkItem::class, 'case' => SupportCase::class, 'category' => SupportCategory::class,
                };

                return $model::findOrFail($receipt->result_id);
            }
            $result = $action($fresh, array_map(fn ($value) => is_string($value) ? trim($value) : $value, $input));
            PortalCommand::create(['actor_id' => $fresh->id, 'operation_id' => $key, 'fingerprint' => $fingerprint,
                'result_type' => match (true) {
                    $result instanceof WorkItem => 'work', $result instanceof SupportCase => 'case', default => 'category'
                },
                'result_id' => $result->id]);

            return $result;
        });
    }

    private function authorizeOperation(User $actor, string $operation): void
    {
        if (str_starts_with($operation, 'work.')) {
            abort_unless(PortalAccess::staff($actor, PortalCapability::ViewWork) && PortalAccess::staff($actor, PortalCapability::ManageWork), 403);
        } elseif (str_starts_with($operation, 'category.')) {
            abort_unless(PortalAccess::staff($actor, PortalCapability::Categories), 403);
        } elseif (str_contains($operation, ':staff:') || str_starts_with($operation, 'case.manage:')) {
            $permission = str_starts_with($operation, 'case.note:') ? PortalCapability::NoteCases
                : (str_starts_with($operation, 'case.manage:') ? PortalCapability::ManageCases : PortalCapability::ReplyCases);
            abort_unless(PortalAccess::staff($actor, PortalCapability::ViewCases) && PortalAccess::staff($actor, $permission), 403);
        } else {
            abort_unless(PortalAccess::customer($actor), 403);
        }
    }

    private function revision(Model $model, array &$data): void
    {
        abort_unless($model->revision === (int) $data['expected_revision'], 409, 'This record changed. Reload it before saving again.');
        unset($data['expected_revision']);
    }

    private function assignee(?int $id, PortalCapability $capability): void
    {
        if ($id !== null) {
            $user = User::find($id);
            if (! $user || ! PortalAccess::staff($user, $capability)) {
                $this->invalid('assigned_to', 'Choose an authorized staff member.');
            }
        }
    }

    private function event(User $actor, Model $record, string $action, array $changes): void
    {
        PortalEvent::create([(match (true) {
            $record instanceof WorkItem => 'work_item_id', $record instanceof SupportCase => 'support_case_id', default => 'support_category_id'
        }) => $record->id,
            'actor_id' => $actor->id, 'action' => $action, 'changes' => $changes]);
    }

    private function validate(array $input, array $rules): array
    {
        if (array_diff(array_keys($input), array_keys($rules))) {
            $this->invalid('input', 'Unexpected fields were supplied.');
        }

        return Validator::make($input, $rules)->validate();
    }

    private function canonical(array $input): array
    {
        ksort($input);

        return $input;
    }

    private function invalid(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}
