<?php

namespace App\Filament\Pages\Portal;

use App\Actions\Customers\ManagePortalCustomer;
use App\Enums\PortalCapability;
use App\Models\WorkItem;
use App\Support\PortalAccess;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Livewire\Attributes\Url;

final class Customers extends Page
{
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
        ];
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
