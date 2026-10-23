<?php

namespace App\Filament\Resources\Products\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

abstract class CatalogHistoryRelationManager extends RelationManager
{
    public function mount(): void
    {
        abort_unless(Gate::allows('view', $this->getOwnerRecord()), 403);
        parent::mount();
    }

    public function hydrate(): void
    {
        abort_unless(Gate::allows('view', $this->getOwnerRecord()), 403);
    }

    public function isReadOnly(): bool
    {
        return true;
    }

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return Gate::allows('view', $ownerRecord);
    }
}
