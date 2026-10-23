<?php

namespace App\Filament\Resources\Roles\Pages;

use App\Filament\Resources\Roles\RoleResource;
use App\Support\Filament\RecordVisibilityActions;
use App\Support\Records\RecordVisibility;
use BezhanSalleh\FilamentShield\Resources\Roles\Pages\EditRole as ShieldEditRole;
use Illuminate\Database\Eloquent\Model;

class EditRole extends ShieldEditRole
{
    protected static string $resource = RoleResource::class;

    // Keep the role root lock through Shield's permission-sync afterSave hook.
    protected ?bool $hasDatabaseTransactions = true;

    protected function getActions(): array
    {
        return [RecordVisibilityActions::delete(), RecordVisibilityActions::restore()];
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $locked = $record->newQuery()->whereKey($record->getKey())->lockForUpdate()->firstOrFail();
        RecordVisibility::assertVisible($locked);
        $this->record = $locked;

        return parent::handleRecordUpdate($locked, $data);
    }

    protected function afterSave(): void
    {
        RecordVisibility::assertVisible($this->getRecord()->fresh());
        parent::afterSave();
    }
}
