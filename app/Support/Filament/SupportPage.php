<?php

namespace App\Support\Filament;

use App\Actions\Portal\PortalActions;
use App\Enums\PortalCapability;
use App\Models\SupportCase;
use App\Models\SupportCategory;
use App\Models\WorkItem;
use App\Services\Portal\PortalRead;
use App\Support\PortalAccess;
use App\Support\PortalOptions;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Exceptions\Halt;
use Livewire\Attributes\Locked;

abstract class SupportPage extends Page
{
    use HandlesPortalCommands;
    use PortalListFilters;

    protected static bool $staffMode = false;

    protected bool $embedded = false;

    protected function queryString(): array
    {
        return $this->embedded ? [] : ['record' => ['except' => null], 'linkedWork' => ['except' => null], 'page' => ['except' => 1], 'threadPage' => ['except' => 1], 'search' => ['except' => ''], ...$this->filterQueryString()];
    }

    protected string $view = 'filament.portal.support';

    #[Locked]
    public ?string $contextWork = null;

    public ?string $record = null;

    public ?string $linkedWork = null;

    public int $page = 1;

    public string $search = '';

    public int $threadPage = 1;

    public string $replyBody = '';

    #[Locked]
    public ?string $replyReference = null;

    #[Locked]
    public ?int $replyExpectedRevision = null;

    #[Locked]
    public ?string $revisionReference = null;

    #[Locked]
    public ?int $expectedRevision = null;

    public static function canAccess(): bool
    {
        return auth()->user() && (static::$staffMode ? PortalAccess::staff(auth()->user(), PortalCapability::ViewCases) : PortalAccess::customer(auth()->user()));
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);
        foreach (['create', 'reply', 'manage', 'note', 'category'] as $command) {
            $this->operationId($command);
        }
        if ($this->record) {
            $read = app(PortalRead::class);
            $this->expectedRevision = $read->supportCase(auth()->user(), $this->record, static::$staffMode)['revision'];
            $this->replyReference = $this->revisionReference = $this->record;
            $this->replyExpectedRevision = $this->expectedRevision;
            $read->markCaseRead(auth()->user(), $this->record, static::$staffMode, $this->expectedRevision);
        }
        if ($this->linkedWork && ! static::$staffMode) {
            WorkItem::where('user_id', auth()->id())->where('public_id', $this->linkedWork)->firstOrFail();
        }
    }

    public function getTitle(): string
    {
        return $this->record ? 'Support conversation' : (static::$staffMode ? 'Customer support' : 'Requests & reports');
    }

    public function updatedSearch(): void
    {
        $this->page = 1;
    }

    protected function getViewData(): array
    {
        abort_unless(static::canAccess(), 403);
        $read = app(PortalRead::class);
        if ($this->contextWork) {
            $read->authorizeWork(auth()->user(), $this->contextWork, static::$staffMode);
            if ($this->record) {
                abort_unless(app(PortalActions::class)->caseFor(auth()->user(), $this->record, static::$staffMode)->workItem?->public_id === $this->contextWork, 404);
            }
        }

        return ['staff' => static::$staffMode,
            'case' => $this->record ? $read->supportCase(auth()->user(), $this->record, static::$staffMode, $this->threadPage) : null,
            'listing' => $read->cases(auth()->user(), static::$staffMode, $this->page, $this->search, $this->contextWork, $this->listFilters()),
            'linkedTitle' => $this->linkedWork && ! static::$staffMode ? WorkItem::where('user_id', auth()->id())->where('public_id', $this->linkedWork)->value('title') : null];
    }

    protected function getHeaderActions(): array
    {
        if (! static::$staffMode) {
            $create = [Action::make('createCase')->label('New request or report')->icon('heroicon-o-plus')->schema([
                Select::make('type')->options(PortalOptions::CASE_TYPES)->required()->default('request')
                    ->helperText('Choose Request for help or a change, or Report an issue for something that went wrong.'),
                TextInput::make('subject')->required()->maxLength(255),
                Select::make('category')->options(fn () => PortalForms::categories())->placeholder('General / not sure'),
                Select::make('work')->label('Related project or service (optional)')->default($this->contextWork ?? $this->linkedWork)->disabled(fn () => $this->contextWork !== null)->dehydrated()->searchable()
                    ->getSearchResultsUsing(fn (string $search) => WorkItem::where('user_id', auth()->id())->where('title', 'like', '%'.addcslashes($search, '%_\\').'%')->limit(50)->pluck('title', 'public_id'))
                    ->getOptionLabelUsing(fn ($value) => WorkItem::where('user_id', auth()->id())->where('public_id', $value)->value('title'))
                    ->helperText('You can report account, payment, privacy or security issues without a project or purchase.'),
                Textarea::make('description')->required()->maxLength(10000)->rows(6)
                    ->helperText('Include helpful details and any Google Drive file or folder link shared with our team. Do not send passwords or payment details.'),
            ])->action(function (array $data): void {
                $case = $this->runCommand('create', fn ($key) => app(PortalActions::class)->createCase(auth()->user(), [...$data, ...$this->contextWork ? ['work' => $this->contextWork] : []], $key), 'Request received');
                $this->openConversation($case->public_id);
            })];
        }
        if (! $this->record) {
            return static::$staffMode ? ($this->contextWork ? [] : $this->categoryActions()) : $create;
        }
        $actions = static::$staffMode ? [] : $create;
        $case = $this->contextCase();
        $active = ! in_array($case->status, ['resolved', 'closed'], true);
        if ($active && (! static::$staffMode || PortalAccess::staff(auth()->user(), PortalCapability::ReplyCases))) {
            $actions[] = Action::make('reply')->label('Send a reply')->icon('heroicon-o-chat-bubble-left-right')->schema([
                $this->conflictNotice(), Textarea::make('body')->label('Message')->required()->maxLength(10000)->rows(5)
                    ->helperText('Google Drive links should be shared with the people who need access. Public sharing is not required.'),
            ])->action(fn (array $data) => $this->save('reply', fn ($key) => app(PortalActions::class)->reply(auth()->user(), $this->record,
                ['expected_revision' => $this->expectedRevision, ...$data], $key, static::$staffMode)));
        }
        if (static::$staffMode && PortalAccess::staff(auth()->user(), PortalCapability::ManageCases)) {
            $actions[] = Action::make('manageCase')->label('Triage / update status')->slideOver()->fillForm(fn () => [
                'status' => $case->status, 'priority' => $case->priority, 'assigned_to' => $case->assigned_to, 'category' => $case->category?->public_id,
            ])->schema([
                $this->conflictNotice(), Select::make('status')->options(PortalOptions::CASE_STATUSES)->required(),
                Select::make('priority')->options(PortalOptions::PRIORITIES)->required(),
                Select::make('assigned_to')->label('Assigned staff')->options(fn () => PortalForms::staff(PortalCapability::ViewCases))->searchable(),
                Select::make('category')->options(fn () => PortalForms::categories(false)),
            ])->action(fn (array $data) => $this->save('manage', fn ($key) => app(PortalActions::class)->manageCase(auth()->user(), $this->record,
                ['expected_revision' => $this->expectedRevision, ...$data], $key)));
        }
        if (static::$staffMode && PortalAccess::staff(auth()->user(), PortalCapability::NoteCases)) {
            $actions[] = Action::make('note')->label('Internal note')->color('gray')->schema([
                $this->conflictNotice(), Textarea::make('body')->label('Staff only — never shown to the customer')->required()->maxLength(10000)->rows(4),
            ])->action(fn (array $data) => $this->save('note', fn ($key) => app(PortalActions::class)->reply(auth()->user(), $this->record,
                ['expected_revision' => $this->expectedRevision, ...$data], $key, true, true)));
        }

        return $actions;
    }

    public function getCachedHeaderActions(): array
    {
        return array_values(array_filter(parent::getCachedHeaderActions(), fn ($action) => $action->getName() !== 'reply'));
    }

    public function openConversation(string $reference): void
    {
        $this->reset('commandConflict', 'conflictSnapshot');
        $this->record = $reference;
        $case = $this->contextCase();
        $this->expectedRevision = $case->revision;
        $this->revisionReference = $reference;
        if (trim($this->replyBody) === '') {
            $this->replyReference = $reference;
            $this->replyExpectedRevision = $case->revision;
        }
        $this->threadPage = 1;
        $this->refreshConversationActions();
        app(PortalRead::class)->markCaseRead(auth()->user(), $reference, static::$staffMode, $case->revision);
    }

    public function closeConversation(): void
    {
        $this->reset('commandConflict', 'conflictSnapshot');
        $this->record = null;
        $this->expectedRevision = null;
        $this->revisionReference = null;
        $this->threadPage = 1;
        $this->refreshConversationActions();
    }

    private function refreshConversationActions(): void
    {
        $this->cachedHeaderActions = [];
        $this->cachedActions = [];
        $this->cacheInteractsWithHeaderActions();
    }

    private function contextCase(): SupportCase
    {
        $case = app(PortalActions::class)->caseFor(auth()->user(), $this->record, static::$staffMode);
        if ($this->contextWork) {
            app(PortalRead::class)->authorizeWork(auth()->user(), $this->contextWork, static::$staffMode);
            abort_unless($case->workItem?->public_id === $this->contextWork, 404);
        }

        return $case;
    }

    private function categoryActions(): array
    {
        if (! PortalAccess::staff(auth()->user(), PortalCapability::Categories)) {
            return [];
        }

        return [Action::make('category')->label('Manage categories')->color('gray')->schema([
            Select::make('category')->label('Existing category')->placeholder('Create a new category')->options(fn () => PortalForms::categories(false))->live()
                ->afterStateUpdated(function ($state, $set): void {
                    $category = SupportCategory::where('public_id', $state)->first();
                    $set('name', $category?->name ?? '');
                    $set('active', $category?->active ?? true);
                }),
            TextInput::make('name')->required()->maxLength(100), Toggle::make('active')->default(true)->helperText('Inactive categories stay in existing history but cannot be selected for new cases.'),
        ])->action(fn (array $data) => $this->runCommand('category', fn ($key) => app(PortalActions::class)->category(auth()->user(), $data, $key), 'Category saved'))];
    }

    private function save(string $command, \Closure $action): void
    {
        abort_unless($this->revisionReference === $this->record, 409, 'Open this conversation before making changes.');
        $this->contextCase();
        $case = $this->runCommand($command, $action, $command === 'reply' ? 'Message sent' : 'Case updated');
        $this->expectedRevision = $case->revision;
        app(PortalRead::class)->markCaseRead(auth()->user(), $this->record, static::$staffMode, $this->expectedRevision);
        $this->threadPage = 1;
        $this->refreshConversationActions();
    }

    public function sendReply(): void
    {
        $this->contextCase();
        if ($this->replyReference !== $this->record) {
            Notification::make()->warning()->title('Your draft belongs to another conversation')->body('Restore that conversation or discard the draft before replying here.')->send();

            return;
        }
        $this->validate(['replyBody' => ['required', 'string', 'max:10000']], attributes: ['replyBody' => 'message']);
        try {
            $this->save('reply', fn ($key) => app(PortalActions::class)->reply(auth()->user(), $this->record,
                ['expected_revision' => $this->replyExpectedRevision, 'body' => $this->replyBody], $key, static::$staffMode));
        } catch (Halt) {
            return;
        }
        $this->reset('replyBody');
        $this->replyExpectedRevision = $this->expectedRevision;
    }

    public function updatedRecord(): void
    {
        // History restores URL properties without calling openConversation().
        // Never let an existing action form silently move to another record.
        if ($this->mountedActions !== []) {
            $this->record = $this->revisionReference;

            return;
        }
        if ($this->record) {
            $this->openConversation($this->record);
        } else {
            $this->closeConversation();
        }
    }

    public function restoreReplyConversation(): void
    {
        abort_unless($this->replyReference, 404);
        $this->openConversation($this->replyReference);
    }

    public function discardReplyDraft(): void
    {
        if ($this->record) {
            $this->contextCase();
        }
        $this->reset('replyBody');
        $this->replyReference = $this->record;
        $this->replyExpectedRevision = $this->expectedRevision;
    }

    protected function conflictRecord(): array
    {
        $this->contextCase();
        $case = app(PortalRead::class)->supportCase(auth()->user(), $this->record, static::$staffMode);

        return ['revision' => $case['revision'], 'title' => $case['subject'], 'status' => $case['status'],
            'details' => ['Priority' => $case['priority'], 'Assigned staff' => $case['assigned_to'] ?? 'Unassigned',
                'Category' => $case['category'] ?? 'General',
                'Latest message' => collect($case['messages'])->last()['body'] ?? $case['description']]];
    }
}
