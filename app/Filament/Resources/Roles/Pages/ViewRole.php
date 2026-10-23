<?php

namespace App\Filament\Resources\Roles\Pages;

use App\Filament\Resources\Roles\RoleResource;
use App\Support\Filament\RecordVisibilityActions;
use App\Support\Records\RecordVisibility;
use BezhanSalleh\FilamentShield\Resources\Roles\Pages\ViewRole as ShieldViewRole;
use Filament\Actions\EditAction;

class ViewRole extends ShieldViewRole
{
    protected static string $resource = RoleResource::class;

    public function getSubheading(): ?string
    {
        return RecordVisibility::isDeleted($this->getRecord()) ? 'Deleted — retained for history. Restore this role before editing permissions.' : null;
    }

    protected function getActions(): array
    {
        return [EditAction::make()->visible(fn (): bool => RoleResource::canEdit($this->getRecord())), RecordVisibilityActions::delete(), RecordVisibilityActions::restore()];
    }
}
