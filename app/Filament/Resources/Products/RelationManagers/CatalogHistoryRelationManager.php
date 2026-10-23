<?php

namespace App\Filament\Resources\Products\RelationManagers;

use App\Support\Filament\RecordVisibilityActions;
use App\Support\Records\RecordVisibility;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

abstract class CatalogHistoryRelationManager extends RelationManager
{
    protected function historyTable(Table $table): Table
    {
        return $table->modifyQueryUsing(fn (Builder $query): Builder => RecordVisibility::query($query, 'all'))
            ->filters([RecordVisibilityActions::filter()])
            ->recordActions([RecordVisibilityActions::delete(), RecordVisibilityActions::restore()])
            ->toolbarActions([]);
    }

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
