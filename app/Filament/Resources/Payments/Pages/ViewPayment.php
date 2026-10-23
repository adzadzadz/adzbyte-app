<?php

namespace App\Filament\Resources\Payments\Pages;

use App\Actions\Payments\PaymentControlActions;
use App\Actions\Payments\PaymentOperations;
use App\Filament\Resources\Payments\PaymentResource;
use App\Http\Resources\Api\V1\PaymentOperationsResource;
use App\Models\Order;
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
        $actions = [Action::make('reconcile')->label('Reconcile signed payment')
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
        foreach (['expire' => 'Close payment session', 'recover' => 'Recover unknown session', 'verify' => 'Verify prior closure'] as $command => $label) {
            $ability = $command === 'verify' ? 'reconcile' : $command;
            $visible = $command === 'verify' ? 'can_verify_control' : 'can_'.$command;
            $fields = [Textarea::make('note')->label('Reason (Admin only)')->required()->maxLength(1000)->rows(3)];
            if ($command === 'recover') {
                $fields[] = TextInput::make('session')->label('Provider session ID')->required()->maxLength(100);
            }
            if ($command === 'verify') {
                $fields[] = TextInput::make('control')->label('Original control reference')->required()->maxLength(26);
            }
            if ($command === 'expire') {
                $fields[] = TextInput::make('control')->label('Original cancellation or closure reference (optional)')->maxLength(26)
                    ->helperText('Supply the original reference to complete its cancellation intent after fresh provider verification.');
            }
            $actions[] = Action::make($command === 'verify' ? 'verify_control' : $command)->label($label)
                ->visible(fn (): bool => app(PaymentOperations::class)->read(auth()->user(), $this->getRecord()->public_id)[$visible])
                ->authorize(fn (): bool => app(PaymentAttemptPolicy::class)->{$ability}(User::findOrFail(auth()->id()), $this->getRecord()))
                ->requiresConfirmation()->modalDescription(match ($command) {
                    'expire' => 'Close the captured session with PayMongo and verify it is expired before allowing another payment request. Paid or processing payments stay protected.',
                    'recover' => 'Verify the supplied provider session against the captured order before binding its identity. This cannot record payment or create another session.',
                    'verify' => 'Read the existing provider session to resolve an uncertain closure or recovery. This sends no expiry request and preserves the original command.',
                })->schema($fields)->action(function (array $data) use ($command): void {
                    try {
                        $result = app(PaymentControlActions::class)->start(auth()->user(), Order::findOrFail($this->getRecord()->order_id)->public_id, $command,
                            ['expected_revision' => $this->expectedRevision, 'note' => $data['note'] ?? '',
                                ...$command === 'recover' ? ['session' => $data['session'] ?? ''] : [],
                                ...$command === 'verify' ? ['control' => $data['control'] ?? ''] : [],
                                ...$command === 'expire' && filled($data['control'] ?? null) ? ['control' => $data['control']] : []], $this->operationId, $this->getRecord()->public_id);
                    } catch (ValidationException|HttpException $exception) {
                        if ($exception instanceof HttpException && ! in_array($exception->getStatusCode(), [409, 503], true)) {
                            throw $exception;
                        }
                        Notification::make()->danger()->title('Payment command not applied')->body('Review the current order, provider identity and permissions before trying again.')->send();
                        throw new Halt;
                    }
                    $this->getRecord()->refresh();
                    $this->expectedRevision = app(PaymentOperations::class)->read(auth()->user(), $this->getRecord()->public_id)['order']['revision'];
                    $this->operationId = (string) Str::uuid();
                    Notification::make()->title($result->state === 'confirmed' ? 'Payment command confirmed' : 'Payment command needs verification')
                        ->body($result->state === 'confirmed' ? 'Provider state and the captured request have been verified.' : 'The captured payment remains protected. Review the recorded command and provider state.')->send();
                });
        }

        return $actions;
    }
}
