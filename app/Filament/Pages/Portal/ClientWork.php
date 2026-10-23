<?php

namespace App\Filament\Pages\Portal;

use App\Actions\Portal\PortalActions;
use App\Enums\PortalCapability;
use App\Models\User;
use App\Models\WorkItem;
use App\Services\Portal\PortalRead;
use App\Support\Filament\HandlesPortalCommands;
use App\Support\Filament\PortalForms;
use App\Support\Filament\PortalListFilters;
use App\Support\PortalAccess;
use App\Support\PortalOptions;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Pages\Page;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;

final class ClientWork extends Page
{
    use HandlesPortalCommands;
    use PortalListFilters;

    protected static ?string $title = 'Projects & services';

    protected static ?int $navigationSort = 1;

    protected static string|\UnitEnum|null $navigationGroup = 'Customer portal';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-rectangle-stack';

    protected string $view = 'filament.portal.work';

    protected function queryString(): array
    {
        return $this->filterQueryString();
    }

    #[Url]
    public ?int $customer = null;

    #[Url]
    public ?string $record = null;

    #[Url]
    public int $page = 1;

    #[Url]
    public int $listPage = 1;

    #[Url]
    public string $search = '';

    #[Locked]
    public ?int $expectedRevision = null;

    public static function canAccess(): bool
    {
        return auth()->user() && PortalAccess::staff(auth()->user(), PortalCapability::ViewWork);
    }

    public function mount(): void
    {
        abort_unless(self::canAccess(), 403);
        foreach (['create', 'edit', 'milestone', 'milestone-edit', 'publish'] as $command) {
            $this->operationId($command);
        }
        if ($this->record) {
            $this->expectedRevision = app(PortalRead::class)->work(auth()->user(), $this->record, true)['revision'];
        }
    }

    public function updatedSearch(): void
    {
        $this->page = 1;
    }

    protected function getViewData(): array
    {
        abort_unless(self::canAccess(), 403);
        $read = app(PortalRead::class);

        return ['staff' => true, 'workType' => null, 'work' => $this->record ? $read->work(auth()->user(), $this->record, true, $this->page) : null,
            'listing' => $this->record ? null : $read->works(auth()->user(), null, true, $this->page, $this->search, $this->listFilters())];
    }

    protected function getHeaderActions(): array
    {
        $manage = fn (): bool => PortalAccess::staff(auth()->user(), PortalCapability::ManageWork);
        if (! $this->record) {
            return [Action::make('createWork')->label('Add project or service')->slideOver()->visible($manage)->schema([
                Select::make('customer')->label('Customer')->default(fn () => $this->customer)->required()->searchable()
                    ->getSearchResultsUsing(fn (string $search) => User::role('customer')->where(fn ($q) => $q->where('email', 'like', '%'.addcslashes($search, '%_\\').'%')->orWhere('name', 'like', '%'.addcslashes($search, '%_\\').'%'))->limit(50)->get()->mapWithKeys(fn ($user) => [$user->id => $user->name.' · '.$user->email]))
                    ->getOptionLabelUsing(fn ($value) => User::role('customer')->find($value)?->email),
                Select::make('type')->options(PortalOptions::WORK_TYPES)->required()->default('project'), ...PortalForms::work(),
            ])->action(function (array $data): void {
                $work = $this->runCommand('create', fn ($key) => app(PortalActions::class)->createWork(auth()->user(), $data, $key), 'Work added');
                $this->redirect(self::getUrl(['record' => $work->public_id]));
            })];
        }

        return [
            Action::make('editWork')->label('Update details')->visible($manage)->fillForm(fn () => WorkItem::where('public_id', $this->record)->firstOrFail()->only(['title', 'summary', 'status', 'next_step', 'target_date', 'assigned_to']))
                ->slideOver()->schema([...PortalForms::work(), $this->conflictNotice()])->action(fn (array $data) => $this->save('edit', fn ($key) => app(PortalActions::class)->updateWork(auth()->user(), $this->record, ['expected_revision' => $this->expectedRevision, ...$data], $key))),
            Action::make('addMilestone')->label('Add milestone')->visible($manage)->schema([
                TextInput::make('title')->required()->maxLength(255), DatePicker::make('target_date')->native(), Toggle::make('completed')->default(false), $this->conflictNotice(),
            ])->action(fn (array $data) => $this->save('milestone', fn ($key) => app(PortalActions::class)->milestone(auth()->user(), $this->record, ['expected_revision' => $this->expectedRevision, ...$data], $key))),
            Action::make('milestone')->label('Update milestone')->visible(fn (): bool => $manage() && WorkItem::where('public_id', $this->record)->firstOrFail()->milestones()->exists())
                ->fillForm(function (array $arguments): array {
                    if (empty($arguments['milestone'])) {
                        return [];
                    }
                    $milestone = app(PortalRead::class)->authorizeWork(auth()->user(), $this->record, true)->milestones()->where('public_id', $arguments['milestone'])->firstOrFail();

                    return ['milestone' => $milestone->public_id, 'title' => $milestone->title, 'target_date' => $milestone->target_date?->toDateString(), 'completed' => $milestone->completed_at !== null];
                })->schema([
                    Select::make('milestone')->required()->options(fn () => WorkItem::where('public_id', $this->record)->firstOrFail()->milestones()->pluck('title', 'public_id'))->live()
                        ->afterStateUpdated(function ($state, $set): void {
                            $milestone = WorkItem::where('public_id', $this->record)->firstOrFail()->milestones()->where('public_id', $state)->first();
                            if ($milestone) {
                                $set('title', $milestone->title);
                                $set('target_date', $milestone->target_date?->toDateString());
                                $set('completed', $milestone->completed_at !== null);
                            }
                        }),
                    $this->conflictNotice(), TextInput::make('title')->required()->maxLength(255), DatePicker::make('target_date')->native(), Toggle::make('completed')->default(false),
                ])->action(fn (array $data) => $this->save('milestone-edit', fn ($key) => app(PortalActions::class)->milestone(auth()->user(), $this->record, ['expected_revision' => $this->expectedRevision, ...$data], $key))),
            Action::make('publishUpdate')->label('Post customer update')->visible($manage)->schema([
                $this->conflictNotice(), Textarea::make('body')->label('Update visible to the customer')->required()->rows(5)->maxLength(10000)
                    ->helperText('You can include a Google Drive file or folder link shared with this customer.'),
            ])->action(fn (array $data) => $this->save('publish', fn ($key) => app(PortalActions::class)->publishUpdate(auth()->user(), $this->record, ['expected_revision' => $this->expectedRevision, ...$data], $key))),
        ];
    }

    private function save(string $command, \Closure $action): void
    {
        $work = $this->runCommand($command, $action, 'Work updated');
        $this->expectedRevision = $work->revision;
    }

    protected function conflictRecord(): array
    {
        $work = app(PortalRead::class)->work(auth()->user(), $this->record, true);

        return ['revision' => $work['revision'], 'title' => $work['title'], 'status' => $work['status'],
            'details' => ['Summary' => $work['summary'], 'Next step' => $work['next_step'] ?? '',
                'Target date' => $work['target_date'] ?? '', 'Assigned staff' => $work['assigned_to'] ?? 'Unassigned',
                'Milestones' => collect($work['milestones'])->map(fn ($m) => $m['title'].' · '.($m['completed_at'] ? 'Completed' : 'Upcoming').' · Target: '.($m['target_date'] ?? 'Not set').' · '.$m['public_id'])->implode("\n"),
                'Latest published update' => $work['updates'][0]['body'] ?? 'No updates yet']];
    }
}
