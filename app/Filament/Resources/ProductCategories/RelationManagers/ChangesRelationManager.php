<?php

namespace App\Filament\Resources\ProductCategories\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

class ChangesRelationManager extends RelationManager
{
    protected static string $relationship = 'changes';

    protected static ?string $title = 'Category history';

    public function mount(): void
    {
        Gate::authorize('view', $this->getOwnerRecord());
        parent::mount();
    }

    public function hydrate(): void
    {
        Gate::authorize('view', $this->getOwnerRecord());
    }

    public function isReadOnly(): bool
    {
        return true;
    }

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return Gate::allows('view', $ownerRecord);
    }

    public function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('action'), TextColumn::make('actor.name')->label('Administrator'),
            TextColumn::make('created_at')->dateTime(),
        ])->defaultSort('id', 'desc')->recordActions([])->toolbarActions([]);
    }
}
