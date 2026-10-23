<?php

namespace App\Filament\Resources\Products\Pages;

use App\Actions\Catalog\CatalogActions;
use App\Enums\ProductStatus;
use App\Filament\Resources\Products\ProductResource;
use App\Models\User;
use App\Support\Filament\CatalogInput;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Exceptions\Halt;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;

final class ViewProduct extends ViewRecord
{
    protected static string $resource = ProductResource::class;

    #[Locked]
    public array $operationIds = [];

    public function mount(int|string $record): void
    {
        foreach (['publish', 'unpublish', 'archive', 'restore', 'enableAvailability', 'disableAvailability', 'revisePrice', 'reviseTerms'] as $name) {
            $this->operationIds[$name] = (string) Str::uuid();
        }
        parent::mount($record);
    }

    protected function getHeaderActions(): array
    {
        $record = $this->getRecord();

        return [
            Action::make('editContent')->label('Edit content')->url(ProductResource::getUrl('edit', ['record' => $record]))->visible(fn (): bool => Gate::allows('update', $record)),
            $this->command('revisePrice', 'New price')->schema([
                TextInput::make('amount')->label('Price (PHP)')->required()->inputMode('decimal')->rules([fn () => CatalogInput::moneyRule()])
                    ->helperText('A new version applies to future offers. Zero disables availability.'),
            ])->fillForm(function (): array {
                $minor = $this->getRecord()->currentPrice()->first()?->amount_minor;

                return ['amount' => $minor === null ? '' : intdiv($minor, 100).'.'.str_pad((string) ($minor % 100), 2, '0', STR_PAD_LEFT)];
            }),
            $this->command('reviseTerms', 'New terms')->modalDescription('Create a version for future offers. Earlier versions remain unchanged. Existing structured sections are preserved unless you enter replacement text.')
                ->schema(collect(CatalogInput::TERMS_FIELDS)->map(fn (string $label, string $key) => Textarea::make($key)->label($label)->rows(3))->all())
                ->fillForm(function (): array {
                    $document = $this->getRecord()->currentTerms()->first()?->content ?? [];

                    return collect(CatalogInput::TERMS_FIELDS)->mapWithKeys(fn (string $label, string $key): array => [$key => is_string($document[$key] ?? null) ? $document[$key] : ''])->all();
                }),
            ActionGroup::make([
                $this->command('publish', 'Publish')->visible(fn (): bool => $record->status === ProductStatus::Draft),
                $this->command('unpublish', 'Unpublish')->visible(fn (): bool => $record->status === ProductStatus::Published),
                $this->command('enableAvailability', 'Enable availability')->visible(fn (): bool => $record->status === ProductStatus::Published && ! $record->is_available),
                $this->command('disableAvailability', 'Disable availability')->visible(fn (): bool => $record->is_available),
                $this->command('archive', 'Archive')->color('danger')->visible(fn (): bool => $record->status !== ProductStatus::Archived),
                $this->command('restore', 'Restore to draft')->visible(fn (): bool => $record->status === ProductStatus::Archived),
            ])->label('Publication and availability')->button(),
        ];
    }

    private function command(string $name, string $label): Action
    {
        $ability = in_array($name, ['enableAvailability', 'disableAvailability'], true) ? 'setAvailability' : $name;

        return Action::make($name)->label($label)->authorize(fn (): bool => Gate::allows($ability, $this->getRecord()))
            ->requiresConfirmation()->action(function (array $data) use ($name): void {
                $actor = auth()->user();
                abort_unless($actor instanceof User, 403);
                $record = $this->getRecord();
                $actions = app(CatalogActions::class);
                $id = $this->operationIds[$name];
                try {
                    match ($name) {
                        'revisePrice' => $actions->revisePrice($actor, $record, CatalogInput::price($data), $id),
                        'reviseTerms' => $actions->reviseTerms($actor, $record, CatalogInput::terms($record, $data), $id),
                        'enableAvailability', 'disableAvailability' => $actions->setAvailability($actor, $record, $name === 'enableAvailability', $id),
                        default => $actions->$name($actor, $record, $id),
                    };
                } catch (ValidationException $exception) {
                    $message = isset($exception->errors()['operation_id'])
                        ? 'This change was already saved. Reload before making further changes.'
                        : implode(' ', $exception->validator->errors()->all());
                    Notification::make()->danger()->title('Could not update product')->body($message)->send();
                    throw new Halt;
                }
                $record->refresh();
                $this->operationIds[$name] = (string) Str::uuid();
                Notification::make()->success()->title('Product updated')->send();
            });
    }
}
