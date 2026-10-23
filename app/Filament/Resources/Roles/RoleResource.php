<?php

namespace App\Filament\Resources\Roles;

use App\Filament\Resources\Roles\Pages\CreateRole;
use App\Filament\Resources\Roles\Pages\EditRole;
use App\Filament\Resources\Roles\Pages\ListRoles;
use App\Filament\Resources\Roles\Pages\ViewRole;
use App\Support\Filament\RecordVisibilityActions;
use App\Support\Records\RecordVisibility;
use BezhanSalleh\FilamentShield\Resources\Roles\RoleResource as ShieldRoleResource;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class RoleResource extends ShieldRoleResource
{
    public static function getEloquentQuery(): Builder
    {
        return RecordVisibility::query(parent::getEloquentQuery(), 'all');
    }

    public static function canEdit(Model $record): bool
    {
        return ! RecordVisibility::isDeleted($record) && parent::canEdit($record);
    }

    public static function table(Table $table): Table
    {
        $table = parent::table($table);

        return $table->columns([RecordVisibilityActions::column(), ...$table->getColumns()])
            ->filters([RecordVisibilityActions::filter()])
            ->recordActions([
                ViewAction::make(),
                EditAction::make()->visible(fn (Model $record): bool => static::canEdit($record)),
                RecordVisibilityActions::delete(),
                RecordVisibilityActions::restore(),
            ])->toolbarActions([])
            ->recordUrl(fn (Model $record): ?string => static::canView($record) ? static::getUrl('view', ['record' => $record]) : null);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRoles::route('/'),
            'create' => CreateRole::route('/create'),
            'view' => ViewRole::route('/{record}'),
            'edit' => EditRole::route('/{record}/edit'),
        ];
    }
}
