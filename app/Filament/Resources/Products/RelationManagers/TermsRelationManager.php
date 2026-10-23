<?php

namespace App\Filament\Resources\Products\RelationManagers;

use App\Models\ProductTerms;
use App\Support\Filament\CatalogInput;
use App\Support\Filament\RecordVisibilityActions;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

final class TermsRelationManager extends CatalogHistoryRelationManager
{
    protected static string $relationship = 'termsVersions';

    protected static ?string $title = 'Terms history';

    public function table(Table $table): Table
    {
        return $this->historyTable($table)->columns([RecordVisibilityActions::column(),
            TextColumn::make('version')->sortable(),
            TextColumn::make('content')->getStateUsing(fn (ProductTerms $record): string => CatalogInput::document($record->content))->formatStateUsing(fn (string $state) => CatalogInput::renderDocument($state))->wrap()->extraAttributes(['class' => 'break-words']),
            TextColumn::make('created_at')->dateTime(),
        ])->description('Delete changes list visibility only. Current offers and captured purchases retain their terms versions.')->defaultSort('version', 'desc');
    }
}
