<?php

namespace App\Filament\Resources\Products\Pages;

use App\Actions\Catalog\CatalogActions;
use App\Filament\Resources\Products\ProductResource;
use App\Models\Product;
use App\Models\User;
use App\Support\Filament\CatalogInput;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;

final class EditProduct extends EditRecord
{
    protected static string $resource = ProductResource::class;

    #[Locked]
    public string $operationId;

    public function mount(int|string $record): void
    {
        $this->operationId = (string) Str::uuid();
        parent::mount($record);
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['media'] = array_filter($data['media'] ?? [], fn (mixed $value): bool => is_string($value));

        return Arr::only($data, ['name', 'slug', 'summary', 'description', 'media', 'category_id']);
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $actor = auth()->user();
        abort_unless($actor instanceof User && $record instanceof Product, 403);
        try {
            $result = app(CatalogActions::class)->update($actor, $record, CatalogInput::content($data, $record), $this->operationId);
        } catch (ValidationException $exception) {
            if (isset($exception->errors()['operation_id'])) {
                throw ValidationException::withMessages(['data.name' => 'This change was already saved. Reload before making further changes.']);
            }
            Notification::make()->danger()->title('Could not save content')->body(implode(' ', $exception->validator->errors()->all()))->send();
            throw ValidationException::withMessages(collect($exception->errors())
                ->mapWithKeys(fn (array $messages, string $field): array => ['data.'.$field => $messages])->all());
        }
        $record->refresh();

        return $result;
    }

    protected function afterSave(): void
    {
        $this->operationId = (string) Str::uuid();
    }

    protected function getHeaderActions(): array
    {
        return [Action::make('viewProduct')->label('View product')->url(fn (): string => ProductResource::getUrl('view', ['record' => $this->getRecord()]))->visible(fn (): bool => ProductResource::canView($this->getRecord()))];
    }
}
