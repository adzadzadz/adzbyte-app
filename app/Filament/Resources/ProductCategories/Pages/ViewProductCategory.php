<?php

namespace App\Filament\Resources\ProductCategories\Pages;

use App\Filament\Resources\ProductCategories\ProductCategoryResource;
use App\Support\Filament\RecordVisibilityActions;
use App\Support\Records\RecordVisibility;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewProductCategory extends ViewRecord
{
    public function getSubheading(): ?string
    {
        return RecordVisibility::isDeleted($this->getRecord()) ? 'Deleted — retained for history. Restore this record or its owning record in Data management before making changes.' : null;
    }

    protected static string $resource = ProductCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [EditAction::make()->visible(fn (): bool => ProductCategoryResource::canEdit($this->getRecord())), RecordVisibilityActions::delete(), RecordVisibilityActions::restore()];
    }
}
