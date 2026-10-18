<?php

namespace App\Filament\Resources\Payments\Pages;

use App\Actions\Payments\PaymentOperations;
use App\Filament\Resources\Payments\PaymentResource;
use App\Http\Resources\Api\V1\PaymentOperationsResource;
use App\Models\User;
use App\Policies\PaymentAttemptPolicy;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Exceptions\Halt;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class ViewPayment extends ViewRecord
{
    protected static string $resource = PaymentResource::class;

    protected string $view = 'filament.payments.view';

    #[Locked]
    public string $operationId;

    #[Locked]
    public int $expectedRevision;

    public function getTitle(): string
    {
        return 'Payment and provider events';
    }

    public function mount(int|string $record): void
    {
        parent::mount($record);
        $data = app(PaymentOperations::class)->read(auth()->user(), $this->getRecord()->public_id);
        $this->expectedRevision = $data['order']['revision'];
        $this->operationId = (string) Str::uuid();
    }

    public function hydrate(): void
    {
        parent::hydrate();
        app(PaymentOperations::class)->read(auth()->user(), $this->getRecord()->public_id);
    }

    protected function getViewData(): array
    {
        $data = app(PaymentOperations::class)->read(auth()->user(), $this->getRecord()->public_id, max(1, (int) request()->query('event_page', 1)));

        return ['payment' => (new PaymentOperationsResource($data))->resolve(request())];
    }

    protected function getHeaderActions(): array
    {
        return [Action::make('reconcile')->label('Reconcile signed payment')
            ->visible(fn (): bool => app(PaymentOperations::class)->read(auth()->user(), $this->getRecord()->public_id)['can_reconcile'])
            ->authorize(fn (): bool => app(PaymentAttemptPolicy::class)->reconcile(User::findOrFail(auth()->id()), $this->getRecord()))
            ->requiresConfirmation()->modalDescription('Verify this signed receipt against the current PayMongo test session. This preserves the original receipt and records an Admin resolution. It cannot create a payment or override mismatched evidence.')
            ->schema([TextInput::make('event')->label('Provider event ID')->required()->maxLength(100),
                Textarea::make('note')->label('Reason for reconciliation (Admin only)')->required()->maxLength(1000)->rows(3)])
            ->action(function (array $data): void {
                try {
                    app(PaymentOperations::class)->reconcile(auth()->user(), $this->getRecord()->public_id, $data['event'] ?? '',
                        ['expected_revision' => $this->expectedRevision, 'note' => $data['note'] ?? ''], $this->operationId);
                } catch (ValidationException|HttpException $exception) {
                    if ($exception instanceof HttpException && ! in_array($exception->getStatusCode(), [409, 503], true)) {
                        throw $exception;
                    }
                    Notification::make()->danger()->title('Reconciliation not applied')
                        ->body('Review the current order, signed event and provider availability before retrying.')->send();
                    throw new Halt;
                }
                $this->getRecord()->refresh();
                $this->expectedRevision = app(PaymentOperations::class)->read(auth()->user(), $this->getRecord()->public_id)['order']['revision'];
                $this->operationId = (string) Str::uuid();
                Notification::make()->success()->title('Signed payment reconciled')->send();
            })];
    }
}
