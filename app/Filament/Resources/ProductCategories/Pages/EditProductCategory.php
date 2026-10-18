<?php

namespace App\Filament\Resources\ProductCategories\Pages;

use App\Actions\Catalog\CategoryActions;
use App\Filament\Resources\ProductCategories\ProductCategoryResource;
use App\Models\ProductCategory;
use App\Models\User;
use App\Support\Filament\CategoryInput;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;

class EditProductCategory extends EditRecord
{
    protected static string $resource = ProductCategoryResource::class;

    #[Locked]
    public string $operationId;

    public function mount(int|string $record): void
    {
        $this->operationId = (string) Str::uuid();
        parent::mount($record);
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $actor = auth()->user();
        abort_unless($actor instanceof User && $record instanceof ProductCategory, 403);
        try {
            return app(CategoryActions::class)->update($actor, $record, CategoryInput::content($data), $this->operationId);
        } catch (ValidationException $exception) {
            throw ValidationException::withMessages(collect($exception->errors())->mapWithKeys(
                fn ($messages, $field) => ['data.'.($field === 'operation_id' ? 'name' : $field) => $field === 'operation_id' ? ['This change was already saved. Reload before making another change.'] : $messages],
            )->all());
        }
    }

    protected function afterSave(): void
    {
        $this->operationId = (string) Str::uuid();
    }
}
