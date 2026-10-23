<?php

namespace App\Filament\Resources\Users\Tables;

use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use App\Support\Filament\RecordVisibilityActions;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

final class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([RecordVisibilityActions::column(),
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('email')
                    ->searchable()
                    ->sortable()
                    ->copyable(),
                TextColumn::make('roles.name')
                    ->label('Access')
                    ->badge()
                    ->separator(', '),
                TextColumn::make('email_verified_at')
                    ->label('Verified')
                    ->dateTime()
                    ->placeholder('Unverified')
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([RecordVisibilityActions::filter(),
                SelectFilter::make('role')
                    ->relationship('roles', 'name')
                    ->preload(),
            ])
            ->recordActions([RecordVisibilityActions::delete(), RecordVisibilityActions::restore(), ViewAction::make(),
                EditAction::make()->visible(fn (User $record): bool => UserResource::canEdit($record)),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
