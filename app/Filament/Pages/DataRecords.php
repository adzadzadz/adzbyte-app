<?php

namespace App\Filament\Pages;

use App\Enums\PortalCapability;
use App\Filament\Pages\Portal\ClientWork;
use App\Filament\Pages\Portal\Customers;
use App\Filament\Pages\Portal\CustomerSupport;
use App\Filament\Resources\ProductCategories\ProductCategoryResource;
use App\Filament\Resources\Products\ProductResource;
use App\Filament\Resources\Roles\RoleResource;
use App\Filament\Resources\Users\UserResource;
use App\Models\RecordDeletion;
use App\Models\RecordDeletionEvent;
use App\Support\Filament\RecordVisibilityActions;
use App\Support\PortalAccess;
use App\Support\Records\RecordTypes;
use App\Support\Records\RecordVisibility;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Livewire\Attributes\Url;

final class DataRecords extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $title = 'Data management';

    protected static string|\UnitEnum|null $navigationGroup = 'Administration';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-archive-box';

    protected string $view = 'filament.pages.data-records';

    #[Url]
    public string $type = 'work';

    #[Url]
    public ?string $record = null;

    public static function canAccess(): bool
    {
        return RecordVisibility::allowed(auth()->user());
    }

    public function mount(): void
    {
        abort_unless(self::canAccess(), 403);
        RecordTypes::definition($this->type);
    }

    public function updatingType(): void
    {
        $this->unmountAction();
        $this->record = null;
    }

    public function updatedType(): void
    {
        RecordTypes::definition($this->type);
        $this->resetTable();
    }

    public function table(Table $table): Table
    {
        return $table->query(function (): Builder {
            abort_unless(self::canAccess(), 403);
            [$class] = RecordTypes::definition($this->type);

            return RecordVisibility::query($class::query(), 'all');
        })->columns([
            TextColumn::make('id')->label('Database reference')->searchable()->sortable(),
            TextColumn::make('record_label')->label('Record')->state(fn (Model $record) => RecordTypes::label($record))->wrap()->searchable(query: function (Builder $query, string $search): Builder {
                $columns = match ($this->type) {
                    'users' => ['name', 'email'], 'roles', 'support-categories', 'products', 'product-categories' => ['name'],
                    'work', 'milestones' => ['title'], 'cases' => ['subject'], 'messages', 'notes', 'updates' => ['body'],
                    'orders', 'payments' => ['public_id'], 'package-checkouts' => ['package_name'], default => ['id'],
                };

                return $query->where(function ($nested) use ($columns, $search): void {
                    foreach ($columns as $column) {
                        $nested->orWhere($column, 'like', '%'.addcslashes(mb_substr($search, 0, 100), '%_\\').'%');
                    }
                });
            }),
            RecordVisibilityActions::column(),
            TextColumn::make('ui_deleted_at')->label('Deleted on')->dateTime()->placeholder('—'),
        ])->filters([RecordVisibilityActions::filter()])->recordActions([
            Action::make('inspect')->label('View')->url(fn (Model $record) => self::getUrl(['type' => $this->type, 'record' => $record->getKey()])),
            Action::make('editRecord')->label('Edit')->visible(fn (Model $record) => ! RecordVisibility::isDeleted($record) && $this->editUrl($record) !== null)->url(fn (Model $record) => $this->editUrl($record)),
            RecordVisibilityActions::delete(), RecordVisibilityActions::restore(),
        ])->defaultSort('id', 'desc')->paginated([15, 30, 50])->emptyStateHeading('No records in this view')
            ->emptyStateDescription('Change Visibility to Deleted or All records to inspect retained data. Search by name, title, message or reference.');
    }

    protected function getHeaderActions(): array
    {
        $create = match ($this->type) {
            'users' => Customers::getUrl(['action' => 'createCustomer']),
            'roles' => RoleResource::getUrl('create'),
            'work' => ClientWork::getUrl(['action' => 'createWork']),
            'milestones' => ClientWork::getUrl(),
            'cases' => CustomerSupport::getUrl(['action' => 'createStaffCase']),
            'support-categories' => CustomerSupport::getUrl(['action' => 'category']),
            'products' => ProductResource::getUrl('create'),
            'product-categories' => ProductCategoryResource::getUrl('create'),
            'commerce-policies' => CommerceSettings::getUrl(),
            default => null,
        };
        $allowed = match ($this->type) {
            'users' => PortalAccess::staff(auth()->user(), PortalCapability::ManageCustomers) && Customers::canAccess(),
            'roles' => RoleResource::canCreate(),
            'work', 'milestones' => PortalAccess::staff(auth()->user(), PortalCapability::ManageWork) && ClientWork::canAccess(),
            'cases' => PortalAccess::staff(auth()->user(), PortalCapability::ManageCases) && CustomerSupport::canAccess(),
            'support-categories' => PortalAccess::staff(auth()->user(), PortalCapability::Categories) && CustomerSupport::canAccess(),
            'products' => ProductResource::canCreate(), 'product-categories' => ProductCategoryResource::canCreate(),
            'commerce-policies' => CommerceSettings::canAccess(), default => false,
        };

        $actions = [Action::make('createRecord')->label($this->type === 'milestones' ? 'Open projects to add a milestone' : 'Create record')->url($create)->visible($create !== null && $allowed)];
        if ($this->record !== null) {
            [$class] = RecordTypes::definition($this->type);
            $selected = RecordVisibility::query($class::whereKey($this->record), 'all')->firstOrFail();
            $actions[] = RecordVisibilityActions::delete()->record($selected)->successRedirectUrl(fn () => self::getUrl(['type' => $this->type, 'record' => $this->record]));
            $actions[] = RecordVisibilityActions::restore()->record($selected)->successRedirectUrl(fn () => self::getUrl(['type' => $this->type, 'record' => $this->record]));
        }

        return $actions;
    }

    public function editUrl(Model $record): ?string
    {
        $allowed = match ($this->type) {
            'users' => UserResource::canEdit($record), 'roles' => RoleResource::canEdit($record),
            'work', 'milestones' => PortalAccess::staff(auth()->user(), PortalCapability::ManageWork) && ClientWork::canAccess(),
            'cases' => PortalAccess::staff(auth()->user(), PortalCapability::ManageCases) && CustomerSupport::canAccess(),
            'support-categories' => PortalAccess::staff(auth()->user(), PortalCapability::Categories) && CustomerSupport::canAccess(),
            'products' => ProductResource::canEdit($record), 'product-categories' => ProductCategoryResource::canEdit($record), default => false,
        };
        if (! $allowed) {
            return null;
        }

        return match ($this->type) {
            'users' => UserResource::getUrl('edit', ['record' => $record]),
            'roles' => RoleResource::getUrl('edit', ['record' => $record]),
            'work' => ClientWork::getUrl(['record' => $record->public_id, 'action' => 'editWork']),
            'milestones' => ($work = $record->workItem) ? ClientWork::getUrl(['record' => $work->public_id, 'action' => 'milestone', 'actionArguments' => ['milestone' => $record->public_id]]) : null,
            'cases' => CustomerSupport::getUrl(['record' => $record->public_id, 'action' => 'editCaseDetails']),
            'support-categories' => CustomerSupport::getUrl(['action' => 'category', 'actionArguments' => ['category' => $record->public_id]]),
            'products' => ProductResource::getUrl('edit', ['record' => $record]),
            'product-categories' => ProductCategoryResource::getUrl('edit', ['record' => $record]),
            default => null,
        };
    }

    protected function getViewData(): array
    {
        abort_unless(self::canAccess(), 403);
        [$class, $label, $fields] = RecordTypes::definition($this->type);
        $selected = $this->record === null ? null : RecordVisibility::query($class::whereKey($this->record), 'all')->firstOrFail();
        $parents = [];
        if ($selected && RecordVisibility::isDeleted($selected)) {
            foreach (RecordTypes::PARENTS[$selected::class] ?? [] as $column => $parent) {
                if (($id = $selected->getAttribute($column)) !== null && ($owner = $parent::find($id)) && RecordVisibility::isDeleted($owner)) {
                    $parents[] = ['label' => RecordTypes::label($owner), 'url' => self::getUrl(['type' => RecordTypes::type($owner), 'record' => $owner->getKey()])];
                }
            }
        }

        $marker = $selected ? RecordDeletion::where('record_type', $this->type)->where('record_id', (string) $selected->getKey())->first() : null;
        $lifecycle = $marker ? RecordDeletionEvent::where('record_deletion_id', $marker->id)->with('actor')->latest('id')->limit(20)->get() : collect();

        return compact('selected', 'label', 'fields', 'parents', 'lifecycle');
    }
}
