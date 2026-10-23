<?php

namespace App\Filament\Pages\Portal;

use App\Actions\Customers\ManagePortalCustomer;
use App\Actions\Portal\PortalActions;
use App\Enums\PortalCapability;
use App\Models\WorkItem;
use App\Support\Filament\HandlesPortalCommands;
use App\Support\Filament\PortalForms;
use App\Support\PortalAccess;
use App\Support\PortalOptions;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Livewire\Attributes\Url;

final class Customers extends Page
{
    use HandlesPortalCommands;

    protected static ?string $title = 'Customers';

    protected static string|\UnitEnum|null $navigationGroup = 'Customer portal';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-users';

    protected static ?int $navigationSort = 0;

    protected string $view = 'filament.portal.customers';

    #[Url]
    public ?int $record = null;

    #[Url]
    public string $search = '';

    #[Url]
    public int $page = 1;

    public function mount(): void
    {
        abort_unless(self::canAccess(), 403);
        $this->operationId('assign');
    }

    public static function canAccess(): bool
    {
        return auth()->user() && PortalAccess::staff(auth()->user(), PortalCapability::ViewCustomers);
    }

    public function updatedSearch(): void
    {
        $this->page = 1;
    }

    protected function getHeaderActions(): array
    {
        return [Action::make('createCustomer')->label('Add customer')->icon('heroicon-o-user-plus')
            ->visible(fn () => PortalAccess::staff(auth()->user(), PortalCapability::ManageCustomers))
            ->schema([TextInput::make('name')->required()->maxLength(255), TextInput::make('email')->email()->required()->maxLength(255)->helperText('We will queue a secure activation link so the customer can set their own password.')])
            ->action(function (array $data): void {
                $customer = app(ManagePortalCustomer::class)->create(auth()->user(), $data['name'], $data['email']);
                Notification::make()->success()->title('Customer created · activation invitation queued')->send();
                $this->redirect(self::getUrl(['record' => $customer->id]));
            }),
            Action::make('inviteCustomer')->label('Resend activation invitation')->requiresConfirmation()
                ->visible(fn () => $this->record && PortalAccess::staff(auth()->user(), PortalCapability::ManageCustomers)
                    && ManagePortalCustomer::query()->whereKey($this->record)->whereNull('email_verified_at')->exists())
                ->action(function (): void {
                    app(ManagePortalCustomer::class)->resend(auth()->user(), $this->record);
                    Notification::make()->success()->title('Activation invitation queued')->send();
                }),
            Action::make('assignWork')->label('Assign project or service')->slideOver()
                ->visible(fn () => $this->record && PortalAccess::staff(auth()->user(), PortalCapability::ViewWork) && PortalAccess::staff(auth()->user(), PortalCapability::ManageWork))
                ->modalDescription(fn () => 'Assign work to '.ManagePortalCustomer::query()->findOrFail($this->record)->name.'. The customer will see this in their portal.')
                ->schema([Select::make('type')->options(PortalOptions::WORK_TYPES)->default('project')->required(), ...PortalForms::work()])
                ->action(function (array $data): void {
                    $customer = ManagePortalCustomer::query()->findOrFail($this->record);
                    $this->runCommand('assign', fn ($key) => app(PortalActions::class)->createWork(auth()->user(), [...$data, 'customer' => $customer->id], $key), 'Work assigned to '.$customer->name);
                }),
        ];
    }

    public function getCachedHeaderActions(): array
    {
        return array_values(array_filter(parent::getCachedHeaderActions(), fn ($action) => $action->getName() !== 'assignWork'));
    }

    protected function getViewData(): array
    {
        abort_unless(self::canAccess(), 403);
        $customer = $this->record ? ManagePortalCustomer::query()->findOrFail($this->record) : null;
        $items = $customer ? null : ManagePortalCustomer::query()->when($this->search !== '', fn ($q) => $q->where(fn ($q) => $q
            ->where('name', 'like', '%'.addcslashes(mb_substr($this->search, 0, 100), '%_\\').'%')
            ->orWhere('email', 'like', '%'.addcslashes(mb_substr($this->search, 0, 100), '%_\\').'%')))->latest('id')->paginate(15, ['*'], 'page', max(1, $this->page));

        return ['customer' => $customer, 'customers' => $items,
            'works' => $customer && PortalAccess::staff(auth()->user(), PortalCapability::ViewWork) ? WorkItem::where('user_id', $customer->id)->latest('updated_at')->limit(50)->get() : collect(),
        ];
    }
}
