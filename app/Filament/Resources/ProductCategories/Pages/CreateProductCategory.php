<?php

namespace App\Filament\Resources\ProductCategories\Pages;

use App\Actions\Catalog\CategoryActions;
use App\Filament\Resources\ProductCategories\ProductCategoryResource;
use App\Models\User;
use App\Support\Filament\CategoryInput;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;

class CreateProductCategory extends CreateRecord
{
    protected static string $resource = ProductCategoryResource::class;

    protected static bool $canCreateAnother = false;

    #[Locked]
    public string $operationId;

    public function mount(): void
    {
        $this->operationId = (string) Str::uuid();
        parent::mount();
    }

    protected function handleRecordCreation(array $data): Model
    {
        $actor = auth()->user();
        abort_unless($actor instanceof User, 403);
        try {
            return app(CategoryActions::class)->create($actor, CategoryInput::content($data), $this->operationId);
        } catch (ValidationException $exception) {
            throw ValidationException::withMessages(collect($exception->errors())->mapWithKeys(
                fn ($messages, $field) => ['data.'.($field === 'operation_id' ? 'name' : $field) => $field === 'operation_id' ? ['This category was already created. Reload before creating another.'] : $messages],
            )->all());
        }
    }

    protected function getRedirectUrl(): string
    {
        return ProductCategoryResource::canView($this->getRecord()) ? ProductCategoryResource::getUrl('view', ['record' => $this->getRecord()]) : ProductCategoryResource::getUrl('index');
    }
}
