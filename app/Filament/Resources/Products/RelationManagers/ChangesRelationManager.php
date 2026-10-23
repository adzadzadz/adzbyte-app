<?php

namespace App\Filament\Resources\Products\RelationManagers;

use App\Support\Filament\RecordVisibilityActions;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

final class ChangesRelationManager extends CatalogHistoryRelationManager
{
    protected static string $relationship = 'changes';

    protected static ?string $title = 'Change history';

    public function table(Table $table): Table
    {
        return $this->historyTable($table)->columns([RecordVisibilityActions::column(),
            TextColumn::make('action')->formatStateUsing(fn (string $state): string => str($state)->headline()->toString()),
            TextColumn::make('actor.name')->label('Changed by')->wrap(),
            TextColumn::make('created_at')->dateTime()->sortable(),
        ])->defaultSort('id', 'desc');
    }
}
