<?php

namespace App\Support\Filament;

use App\Actions\Portal\PortalActions;
use App\Enums\PortalCapability;
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
use Filament\Pages\Page;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;

abstract class SupportPage extends Page
{
    use HandlesPortalCommands;

    protected static bool $staffMode = false;

    protected string $view = 'filament.portal.support';

    #[Url]
    public ?string $record = null;

    #[Url]
    public ?string $linkedWork = null;

    #[Url]
    public int $page = 1;

    #[Url]
    public string $search = '';

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
            $read->markCaseRead(auth()->user(), $this->record, static::$staffMode);
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

        return ['staff' => static::$staffMode,
            'case' => $this->record ? $read->supportCase(auth()->user(), $this->record, static::$staffMode, $this->page) : null,
            'listing' => $this->record ? null : $read->cases(auth()->user(), static::$staffMode, $this->page, $this->search),
            'linkedTitle' => $this->linkedWork && ! static::$staffMode ? WorkItem::where('user_id', auth()->id())->where('public_id', $this->linkedWork)->value('title') : null];
    }

    protected function getHeaderActions(): array
    {
        if (! $this->record && ! static::$staffMode) {
            return [Action::make('createCase')->label('New request or report')->icon('heroicon-o-plus')->schema([
                Select::make('type')->options(PortalOptions::CASE_TYPES)->required()->default('request')
                    ->helperText('Choose Request for help or a change, or Report an issue for something that went wrong.'),
                TextInput::make('subject')->required()->maxLength(255),
                Select::make('category')->options(fn () => PortalForms::categories())->placeholder('General / not sure'),
                Select::make('work')->label('Related project or service (optional)')->default($this->linkedWork)->searchable()
                    ->getSearchResultsUsing(fn (string $search) => WorkItem::where('user_id', auth()->id())->where('title', 'like', '%'.addcslashes($search, '%_\\').'%')->limit(50)->pluck('title', 'public_id'))
                    ->getOptionLabelUsing(fn ($value) => WorkItem::where('user_id', auth()->id())->where('public_id', $value)->value('title'))
                    ->helperText('You can report account, payment, privacy or security issues without a project or purchase.'),
                Textarea::make('description')->required()->maxLength(10000)->rows(6)
                    ->helperText('Include helpful details and any Google Drive file or folder link shared with our team. Do not send passwords or payment details.'),
            ])->action(function (array $data): void {
                $case = $this->runCommand('create', fn ($key) => app(PortalActions::class)->createCase(auth()->user(), $data, $key), 'Request received');
                $this->redirect(static::getUrl(['record' => $case->public_id]));
            })];
        }
        if (! $this->record) {
            return $this->categoryActions();
        }
        $actions = [];
        $case = app(PortalActions::class)->caseFor(auth()->user(), $this->record, static::$staffMode);
        $active = ! in_array($case->status, ['resolved', 'closed'], true);
        if ($active && (! static::$staffMode || PortalAccess::staff(auth()->user(), PortalCapability::ReplyCases))) {
            $actions[] = Action::make('reply')->label('Send a reply')->icon('heroicon-o-chat-bubble-left-right')->schema([
                Textarea::make('body')->label('Message')->required()->maxLength(10000)->rows(5)
                    ->helperText('Google Drive links should be shared with the people who need access. Public sharing is not required.'),
            ])->action(fn (array $data) => $this->save('reply', fn ($key) => app(PortalActions::class)->reply(auth()->user(), $this->record,
                ['expected_revision' => $this->expectedRevision, ...$data], $key, static::$staffMode)));
        }
        if (static::$staffMode && PortalAccess::staff(auth()->user(), PortalCapability::ManageCases)) {
            $actions[] = Action::make('manageCase')->label('Triage / update status')->fillForm(fn () => [
                'status' => $case->status, 'priority' => $case->priority, 'assigned_to' => $case->assigned_to, 'category' => $case->category?->public_id,
            ])->schema([
                Select::make('status')->options(PortalOptions::CASE_STATUSES)->required(),
                Select::make('priority')->options(PortalOptions::PRIORITIES)->required(),
                Select::make('assigned_to')->label('Assigned staff')->options(fn () => PortalForms::staff(PortalCapability::ViewCases))->searchable(),
                Select::make('category')->options(fn () => PortalForms::categories(false)),
            ])->action(fn (array $data) => $this->save('manage', fn ($key) => app(PortalActions::class)->manageCase(auth()->user(), $this->record,
                ['expected_revision' => $this->expectedRevision, ...$data], $key)));
        }
        if (static::$staffMode && PortalAccess::staff(auth()->user(), PortalCapability::NoteCases)) {
            $actions[] = Action::make('note')->label('Internal note')->color('gray')->schema([
                Textarea::make('body')->label('Staff only — never shown to the customer')->required()->maxLength(10000)->rows(4),
            ])->action(fn (array $data) => $this->save('note', fn ($key) => app(PortalActions::class)->reply(auth()->user(), $this->record,
                ['expected_revision' => $this->expectedRevision, ...$data], $key, true, true)));
        }

        return $actions;
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
        $case = $this->runCommand($command, $action, $command === 'reply' ? 'Message sent' : 'Case updated');
        $this->expectedRevision = $case->revision;
        app(PortalRead::class)->markCaseRead(auth()->user(), $this->record, static::$staffMode);
        $this->page = 1;
    }
}
