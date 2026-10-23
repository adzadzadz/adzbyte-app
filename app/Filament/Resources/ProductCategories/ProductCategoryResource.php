<?php

namespace App\Filament\Resources\ProductCategories;

use App\Filament\Resources\ProductCategories\Pages\CreateProductCategory;
use App\Filament\Resources\ProductCategories\Pages\EditProductCategory;
use App\Filament\Resources\ProductCategories\Pages\ListProductCategories;
use App\Filament\Resources\ProductCategories\Pages\ViewProductCategory;
use App\Filament\Resources\ProductCategories\RelationManagers\ChangesRelationManager;
use App\Models\ProductCategory;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class ProductCategoryResource extends Resource
{
    protected static ?string $model = ProductCategory::class;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $navigationLabel = 'Product categories';

    protected static string|UnitEnum|null $navigationGroup = 'Commerce';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-tag';

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            TextInput::make('name')->required()->maxLength(255),
            Toggle::make('limit_one_per_cart')->label('Only one service from this category per cart')->default(false)
                ->helperText('Services from other categories can still be added. Saved carts are revalidated when the rule changes.'),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->columns(['default' => 1, 'md' => 2])->components([
            TextEntry::make('name'), TextEntry::make('public_id')->label('Reference'),
            IconEntry::make('limit_one_per_cart')->label('One service per cart')->boolean(),
            TextEntry::make('updated_at')->dateTime(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name')->searchable()->sortable(),
            IconColumn::make('limit_one_per_cart')->label('One service per cart')->boolean(),
            TextColumn::make('products_count')->counts('products')->label('Products'),
        ])->recordActions([ViewAction::make(), EditAction::make()])->toolbarActions([])
            ->recordUrl(fn (ProductCategory $record): ?string => static::canView($record) ? static::getUrl('view', ['record' => $record]) : null);
    }

    public static function getRelations(): array
    {
        return [ChangesRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProductCategories::route('/'), 'create' => CreateProductCategory::route('/create'),
            'view' => ViewProductCategory::route('/{record}'), 'edit' => EditProductCategory::route('/{record}/edit'),
        ];
    }
}
