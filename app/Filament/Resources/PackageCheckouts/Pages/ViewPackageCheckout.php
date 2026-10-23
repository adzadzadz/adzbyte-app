<?php

namespace App\Filament\Resources\PackageCheckouts\Pages;

use App\Filament\Resources\PackageCheckouts\PackageCheckoutResource;
use App\Support\Filament\RecordVisibilityActions;
use App\Support\Records\RecordVisibility;
use Filament\Resources\Pages\ViewRecord;

class ViewPackageCheckout extends ViewRecord
{
    public function getSubheading(): ?string
    {
        return RecordVisibility::isDeleted($this->getRecord()) ? 'Deleted — retained for history. Restore this record or its owning record in Data management before making changes.' : null;
    }

    protected static string $resource = PackageCheckoutResource::class;

    protected function getHeaderActions(): array
    {
        return [RecordVisibilityActions::delete(), RecordVisibilityActions::restore()];
    }
}
