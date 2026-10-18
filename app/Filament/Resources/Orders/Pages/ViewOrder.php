<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Actions\Orders\OrderReviewActions;
use App\Actions\Orders\OrderReviewRead;
use App\Filament\Resources\Orders\OrderResource;
use App\Http\Resources\Api\V1\OrderReviewResource;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Exceptions\Halt;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class ViewOrder extends ViewRecord
{
    protected static string $resource = OrderResource::class;

    protected string $view = 'filament.orders.view';

    public function getTitle(): string
    {
        return 'Review service request';
    }

    #[Locked]
    public array $operationIds = [];

    #[Locked]
    public int $expectedRevision;

    public function mount(int|string $record): void
    {
        parent::mount($record);
        $this->expectedRevision = $this->getRecord()->revision;
        foreach (array_keys(OrderReviewActions::STAFF) as $command) {
            $this->operationIds[$command] = (string) Str::uuid();
        }
    }

    public function hydrate(): void
    {
        parent::hydrate();
        app(OrderReviewRead::class)->read(auth()->user(), $this->getRecord()->public_id, true);
    }

    protected function getViewData(): array
    {
        $review = app(OrderReviewRead::class)->read(auth()->user(), $this->getRecord()->public_id, true, max(1, (int) request()->query('page', 1)));

        return ['review' => (new OrderReviewResource($review))->resolve(request())];
    }

    protected function getHeaderActions(): array
    {
        $labels = ['clarify' => 'Request clarification', 'message' => 'Send message', 'approve' => 'Approve', 'propose' => 'Send updated offer', 'note' => 'Add internal note', 'reject' => 'Reject'];
        $actions = [];
        foreach ($labels as $command => $label) {
            $action = Action::make($command)->label($label)->authorize(fn () => Gate::allows($command, $this->getRecord()))->requiresConfirmation();
            $action->visible(function () use ($command): bool {
                $record = $this->getRecord();

                return $record->status === 'open' && $record->payment_status === 'unpaid' && match ($command) {
                    'approve' => $record->approval_status === 'pending' && $record->pending_proposal_id === null,
                    'reject' => in_array($record->approval_status, ['pending', 'clarification'], true),
                    'clarify', 'propose' => $record->approval_status !== 'rejected',
                    default => true,
                };
            });
            if ($command !== 'approve') {
                $action->schema([Textarea::make('message')->label($command === 'note' ? 'Internal note (Admin only)' : 'Message to customer')->required()->maxLength(10000)->rows(4)]);
            }
            if ($command === 'propose') {
                $action->modalDescription('Send the current service prices, scope and commerce terms for fresh customer acceptance. Approval is removed until the customer accepts and Admin approves again.');
            }
            if ($command === 'approve') {
                $action->modalDescription('Approve the accepted request for its captured approval period. This does not record payment or start fulfillment.');
            }
            if ($command === 'reject') {
                $action->color('danger');
            }
            $actions[] = $action->action(function (array $data) use ($command): void {
                try {
                    app(OrderReviewRead::class)->read(auth()->user(), $this->getRecord()->public_id, true);
                    app(OrderReviewActions::class)->staff(auth()->user(), $this->getRecord()->public_id, $command,
                        ['expected_revision' => $this->expectedRevision, ...$command === 'approve' ? [] : ['message' => $data['message'] ?? null]], $this->operationIds[$command]);
                } catch (ValidationException|HttpException $exception) {
                    if ($exception instanceof HttpException && $exception->getStatusCode() !== 409) {
                        throw $exception;
                    }
                    Notification::make()->danger()->title('Update not saved')->body($exception instanceof ValidationException ? implode(' ', $exception->validator->errors()->all()) : 'This request changed or the action is unavailable. Reload and review it before trying again.')->send();
                    throw new Halt;
                }
                $this->getRecord()->refresh();
                $this->expectedRevision = $this->getRecord()->revision;
                $this->operationIds[$command] = (string) Str::uuid();
                Notification::make()->success()->title('Request updated')->send();
            });
        }

        return $actions;
    }
}
