<?php

namespace App\Filament\Resources\Products\RelationManagers;

use App\Models\ProductPrice;
use App\Support\Filament\CatalogInput;
use App\Support\Filament\RecordVisibilityActions;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

final class PricesRelationManager extends CatalogHistoryRelationManager
{
    protected static string $relationship = 'prices';

    protected static ?string $title = 'Price history';

    public function table(Table $table): Table
    {
        return $this->historyTable($table)->columns([RecordVisibilityActions::column(),
            TextColumn::make('version')->sortable(),
            TextColumn::make('amount_minor')->label('Price')->getStateUsing(fn (ProductPrice $record): string => CatalogInput::formattedPrice($record->amount_minor)),
            TextColumn::make('created_at')->dateTime(),
        ])->description('Delete changes list visibility only. Current offers and captured purchases retain their price versions.')->defaultSort('version', 'desc');
    }
}
