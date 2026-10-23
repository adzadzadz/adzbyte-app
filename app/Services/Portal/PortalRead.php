<?php

namespace App\Services\Portal;

use App\Actions\Customers\ManagePortalCustomer;
use App\Actions\Portal\PortalActions;
use App\Enums\PortalCapability;
use App\Models\SupportCase;
use App\Models\SupportCategory;
use App\Models\User;
use App\Models\WorkItem;
use App\Support\PortalAccess;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

final class PortalRead
{
    public function works(User $actor, ?string $type = null, bool $staff = false, int $page = 1, string $search = ''): array
    {
        $query = $this->workQuery($actor, $staff)->when($type, fn ($q) => $q->where('type', $type))
            ->when($search !== '', fn ($q) => $q->where('title', 'like', '%'.addcslashes(mb_substr($search, 0, 100), '%_\\').'%'));
        $items = $query->with('owner')->withCount(['milestones', 'milestones as completed_milestones_count' => fn ($q) => $q->whereNotNull('completed_at')])
            ->orderByRaw("CASE WHEN status = 'waiting_for_customer' THEN 0 WHEN status IN ('completed', 'cancelled') THEN 2 ELSE 1 END")->orderByDesc('updated_at')->orderByDesc('id')->paginate(12, ['*'], 'page', max(1, $page));

        return ['items' => $items->map(fn (WorkItem $work) => $this->workSummary($work, $staff))->all(), 'pagination' => $this->pagination($items)];
    }

    public function work(User $actor, string $reference, bool $staff = false, int $page = 1): array
    {
        $work = $this->workQuery($actor, $staff)->where('public_id', $reference)->with(['owner', 'assignee', 'milestones'])
            ->withCount(['milestones', 'milestones as completed_milestones_count' => fn ($q) => $q->whereNotNull('completed_at')])->firstOrFail();
        $updates = $work->updates()->with('actor')->orderByDesc('id')->paginate(20, ['*'], 'page', max(1, $page));

        return [...$this->workSummary($work, $staff), 'summary' => $work->summary, 'assigned_to' => $work->assignee?->name,
            'milestones' => $work->milestones->map(fn ($m) => ['public_id' => $m->public_id, 'title' => $m->title,
                'target_date' => $m->target_date?->toDateString(), 'completed_at' => $m->completed_at?->toIso8601String()])->all(),
            'updates' => $updates->map(fn ($update) => ['body' => $update->body, 'author' => $update->actor->name, 'created_at' => $update->created_at->toIso8601String()])->all(),
            'pagination' => $this->pagination($updates),
            'history' => $staff ? $work->events()->with('actor')->latest('id')->limit(50)->get()->map(fn ($event) => [
                'action' => $event->action, 'actor' => $event->actor->name, 'created_at' => $event->created_at->toIso8601String(), 'changes' => $event->changes,
            ])->all() : [],
        ];
    }

    public function cases(User $actor, bool $staff = false, int $page = 1, string $search = '', ?string $work = null): array
    {
        if ($work) {
            $this->workQuery($actor, $staff)->where('public_id', $work)->firstOrFail();
        }
        $items = $this->caseQuery($actor, $staff)->with(['owner', 'category', 'workItem'])
            ->when($work, fn ($q) => $q->whereHas('workItem', fn ($q) => $q->where('public_id', $work)))
            ->when($search !== '', fn ($q) => $q->where('subject', 'like', '%'.addcslashes(mb_substr($search, 0, 100), '%_\\').'%'))
            ->orderByRaw("CASE WHEN status = 'waiting_for_customer' THEN 0 WHEN status IN ('resolved', 'closed') THEN 2 ELSE 1 END")->orderByDesc('public_updated_at')->orderByDesc('id')->paginate(12, ['*'], 'page', max(1, $page));

        return ['items' => $items->map(fn (SupportCase $case) => $this->caseSummary($case, $staff))->all(), 'pagination' => $this->pagination($items)];
    }

    public function supportCase(User $actor, string $reference, bool $staff = false, int $page = 1): array
    {
        $case = app(PortalActions::class)->caseFor($actor, $reference, $staff);
        $case->load(['owner', 'assignee', 'category', 'workItem']);
        $messages = $case->messages()->with('actor')->orderByDesc('id')->paginate(30, ['*'], 'page', max(1, $page));
        $canNote = $staff && PortalAccess::staff($actor, PortalCapability::NoteCases);

        return [...$this->caseSummary($case, $staff), 'description' => $case->description, 'assigned_to' => $case->assignee?->name,
            'can_reply' => ! in_array($case->status, ['resolved', 'closed'], true)
                && (! $staff || PortalAccess::staff($actor, PortalCapability::ReplyCases)),
            'messages' => $messages->getCollection()->reverse()->values()->map(fn ($m) => [
                'body' => $m->body, 'author' => $m->actor->name, 'from_customer' => $m->actor_id === $case->user_id,
                'created_at' => $m->created_at->toIso8601String(),
            ])->all(),
            'pagination' => $this->pagination($messages),
            ...$canNote ? ['notes' => $case->notes()->with('actor')->latest('id')->limit(30)->get()->map(fn ($m) => [
                'body' => $m->body, 'author' => $m->actor->name, 'created_at' => $m->created_at->toIso8601String(),
            ])->all()] : [],
            // A safe status timeline has no note bodies, assignments or internal IDs.
            'history' => $case->events()->whereIn('action', ['case_created', 'case_updated'])->latest('id')->limit(50)->get()->map(fn ($e) => [
                'action' => $e->action, 'status' => $e->changes['after']['status'] ?? $e->changes['status'] ?? null,
                'created_at' => $e->created_at->toIso8601String(),
            ])->all(),
        ];
    }

    public function home(User $actor): array
    {
        $works = $this->workQuery($actor, false);
        $cases = $this->caseQuery($actor, false);

        return [
            'projects' => (clone $works)->where('type', 'project')->whereNotIn('status', ['completed', 'cancelled'])->count(),
            'services' => (clone $works)->where('type', 'service')->whereNotIn('status', ['completed', 'cancelled'])->count(),
            'waiting' => (clone $works)->where('status', 'waiting_for_customer')->count() + (clone $cases)->where('status', 'waiting_for_customer')->count(),
            'open_cases' => (clone $cases)->whereNotIn('status', ['resolved', 'closed'])->count(),
            'works' => $this->works($actor)['items'], 'cases' => $this->cases($actor)['items'],
        ];
    }

    public function staffHome(User $actor): array
    {
        return [
            'customers' => PortalAccess::staff($actor, PortalCapability::ViewCustomers) ? [
                'total' => ManagePortalCustomer::query()->count(),
                'pending' => ManagePortalCustomer::query()->whereNull('email_verified_at')->count(),
            ] : null,
            'recent_cases' => PortalAccess::staff($actor, PortalCapability::ViewCases) ? array_slice($this->cases($actor, true)['items'], 0, 5) : [],
            'work' => PortalAccess::staff($actor, PortalCapability::ViewWork) ? [
                'active' => WorkItem::whereNotIn('status', ['completed', 'cancelled'])->count(),
                'waiting' => WorkItem::where('status', 'waiting_for_customer')->count(),
            ] : null,
            'support' => PortalAccess::staff($actor, PortalCapability::ViewCases) ? [
                'open' => SupportCase::whereNotIn('status', ['resolved', 'closed'])->count(),
                'unassigned' => SupportCase::whereNotIn('status', ['resolved', 'closed'])->whereNull('assigned_to')->count(),
            ] : null,
        ];
    }

    public function markWorkRead(User $actor, string $reference): void
    {
        $work = $this->workQuery($actor, false)->where('public_id', $reference)->firstOrFail();
        // Reading never changes public updated_at or invalidates a command revision.
        DB::table('work_items')->where('id', $work->id)->update(['customer_read_at' => now()]);
    }

    public function markCaseRead(User $actor, string $reference, bool $staff = false, ?int $revision = null): void
    {
        $case = app(PortalActions::class)->caseFor($actor, $reference, $staff);
        DB::table('support_cases')->where('id', $case->id)->where('revision', $revision ?? $case->revision)->update([$staff ? 'staff_read_at' : 'customer_read_at' => now()]);
    }

    public function categories(bool $all = false): array
    {
        return SupportCategory::query()->when(! $all, fn ($q) => $q->where('active', true))->orderBy('name')->get()
            ->map(fn ($c) => ['public_id' => $c->public_id, 'name' => $c->name, 'active' => $c->active])->all();
    }

    private function workQuery(User $actor, bool $staff): Builder
    {
        abort_unless($staff ? PortalAccess::staff($actor, PortalCapability::ViewWork) : PortalAccess::customer($actor), 403);

        return WorkItem::query()->when(! $staff, fn ($q) => $q->where('user_id', $actor->id));
    }

    private function caseQuery(User $actor, bool $staff): Builder
    {
        abort_unless($staff ? PortalAccess::staff($actor, PortalCapability::ViewCases) : PortalAccess::customer($actor), 403);

        return SupportCase::query()->when(! $staff, fn ($q) => $q->where('user_id', $actor->id));
    }

    private function workSummary(WorkItem $work, bool $staff): array
    {
        return ['public_id' => $work->public_id, 'type' => $work->type, 'title' => $work->title, 'status' => $work->status,
            'next_step' => $work->next_step, 'target_date' => $work->target_date?->toDateString(), 'revision' => $work->revision,
            'milestone_count' => $work->milestones_count, 'completed_milestone_count' => $work->completed_milestones_count,
            'unread' => $work->customer_read_at === null || $work->customer_read_at->lt($work->updated_at),
            'updated_at' => $work->updated_at->toIso8601String(), ...$staff ? ['customer' => $work->owner->name] : []];
    }

    private function caseSummary(SupportCase $case, bool $staff): array
    {
        $read = $staff ? $case->staff_read_at : $case->customer_read_at;

        return ['public_id' => $case->public_id, 'type' => $case->type, 'subject' => $case->subject, 'status' => $case->status,
            'priority' => $case->priority, 'category' => $case->category?->name, 'revision' => $case->revision,
            'work' => $case->workItem ? ['public_id' => $case->workItem->public_id, 'title' => $case->workItem->title, 'type' => $case->workItem->type] : null,
            'unread' => $read === null || $read->lt($case->public_updated_at), 'created_at' => $case->created_at->toIso8601String(),
            'updated_at' => $case->public_updated_at->toIso8601String(), ...$staff ? ['customer' => $case->owner->name] : []];
    }

    private function pagination(LengthAwarePaginator $items): array
    {
        return ['page' => $items->currentPage(), 'last_page' => $items->lastPage(), 'total' => $items->total()];
    }
}
