<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use App\Support\Filament\RecordVisibilityActions;
use App\Support\Records\RecordVisibility;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

final class ViewUser extends ViewRecord
{
    protected static string $resource = UserResource::class;

    public function getSubheading(): ?string
    {
        return RecordVisibility::isDeleted($this->getRecord()) ? 'Deleted — retained for history. Restore this record or its owning record in Data management before editing.' : null;
    }

    protected function getHeaderActions(): array
    {
        return [EditAction::make()->visible(fn (): bool => UserResource::canEdit($this->getRecord())), RecordVisibilityActions::delete(), RecordVisibilityActions::restore()];
    }
}
