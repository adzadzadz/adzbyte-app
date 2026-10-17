<?php

namespace App\Filament\Resources\Products\Pages;

use App\Actions\Catalog\CatalogActions;
use App\Filament\Resources\Products\ProductResource;
use App\Models\User;
use App\Support\Filament\CatalogInput;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;

final class CreateProduct extends CreateRecord
{
    protected static string $resource = ProductResource::class;

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
            return app(CatalogActions::class)->create($actor, CatalogInput::content($data), $this->operationId);
        } catch (ValidationException $exception) {
            if (isset($exception->errors()['operation_id'])) {
                throw ValidationException::withMessages(['data.name' => 'This draft was already created. Reload before creating another draft.']);
            }
            throw $exception;
        }
    }

    protected function getRedirectUrl(): string
    {
        return ProductResource::canView($this->getRecord())
            ? ProductResource::getUrl('view', ['record' => $this->getRecord()])
            : ProductResource::getUrl('index');
    }
}
