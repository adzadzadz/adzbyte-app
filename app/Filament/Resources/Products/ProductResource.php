<?php

namespace App\Filament\Resources\Products;

use App\Filament\Resources\Products\Pages\CreateProduct;
use App\Filament\Resources\Products\Pages\EditProduct;
use App\Filament\Resources\Products\Pages\ListProducts;
use App\Filament\Resources\Products\Pages\ViewProduct;
use App\Filament\Resources\Products\RelationManagers\ChangesRelationManager;
use App\Filament\Resources\Products\RelationManagers\PricesRelationManager;
use App\Filament\Resources\Products\RelationManagers\TermsRelationManager;
use App\Filament\Resources\Products\Schemas\ProductForm;
use App\Models\Product;
use App\Support\Filament\CatalogInput;
use App\Support\Filament\RecordVisibilityActions;
use App\Support\Records\RecordVisibility;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

final class ProductResource extends Resource
{
    protected static ?string $model = Product::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShoppingBag;

    protected static string|UnitEnum|null $navigationGroup = 'Commerce';

    protected static ?string $recordTitleAttribute = 'name';

    public static function getEloquentQuery(): Builder
    {
        return RecordVisibility::query(parent::getEloquentQuery(), 'all');
    }

    public static function canEdit(Model $record): bool
    {
        return ! RecordVisibility::isDeleted($record) && parent::canEdit($record);
    }

    public static function form(Schema $schema): Schema
    {
        return ProductForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make('Product')->schema([
                TextEntry::make('name')->placeholder('Untitled draft'),
                TextEntry::make('public_id')->label('Reference')->copyable()->extraAttributes(['class' => 'break-all']),
                TextEntry::make('status')->badge(),
                TextEntry::make('is_available')->label('Availability')->formatStateUsing(fn (bool $state): string => $state ? 'Enabled' : 'Disabled'),
                TextEntry::make('slug')->placeholder('No slug'),
                TextEntry::make('summary')->placeholder('No summary')->columnSpanFull()->wrap(),
                TextEntry::make('description')->placeholder('No description')->columnSpanFull()->wrap(),
                TextEntry::make('media')->getStateUsing(fn (Product $record): string => CatalogInput::document($record->media))->formatStateUsing(fn (string $state) => CatalogInput::renderDocument($state))->placeholder('No image references')->columnSpanFull()->extraAttributes(['class' => 'break-all']),
            ])->columns(['default' => 1, 'md' => 2]),
            Section::make('Current offer')->description('Publication and availability are separate. Changes apply to future offers.')->schema([
                TextEntry::make('currentPrice.amount_minor')->label('Price')->getStateUsing(fn (Product $record): string => CatalogInput::formattedPrice($record->currentPrice?->amount_minor)),
                TextEntry::make('currentTerms.content')->label('Terms')->getStateUsing(fn (Product $record): string => CatalogInput::document($record->currentTerms?->content))->formatStateUsing(fn (string $state) => CatalogInput::renderDocument($state))->placeholder('No terms configured')->extraAttributes(['class' => 'break-words']),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            RecordVisibilityActions::column(),
            TextColumn::make('name')->placeholder('Untitled draft')->searchable()->sortable()->wrap(),
            TextColumn::make('status')->badge()->sortable(),
            IconColumn::make('is_available')->label('Available')->boolean(),
            TextColumn::make('currentPrice.amount_minor')->label('Price')->getStateUsing(fn (Product $record): string => CatalogInput::formattedPrice($record->currentPrice?->amount_minor)),
            TextColumn::make('updated_at')->dateTime()->sortable()->toggleable(),
        ])->filters([RecordVisibilityActions::filter(),
            SelectFilter::make('status')->options(['draft' => 'Draft', 'published' => 'Published', 'archived' => 'Archived']),
            TernaryFilter::make('is_available')->label('Availability'),
        ])->recordActions([RecordVisibilityActions::delete(), RecordVisibilityActions::restore(), ViewAction::make()])->defaultSort('id', 'desc')
            ->recordUrl(fn (Product $record): ?string => self::canView($record) ? self::getUrl('view', ['record' => $record]) : null)
            ->emptyStateHeading('No products yet')->emptyStateDescription('Create a draft when service details are ready.');
    }

    public static function getRelations(): array
    {
        return [PricesRelationManager::class, TermsRelationManager::class, ChangesRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProducts::route('/'),
            'create' => CreateProduct::route('/create'),
            'view' => ViewProduct::route('/{record}'),
            'edit' => EditProduct::route('/{record}/edit'),
        ];
    }
}
