<?php

namespace App\Filament\Resources\Orders;

use App\Filament\Resources\Orders\Pages\ListOrders;
use App\Filament\Resources\Orders\Pages\ViewOrder;
use App\Models\Order;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

final class OrderResource extends Resource
{
    protected static ?string $model = Order::class;

    protected static ?string $recordRouteKeyName = 'public_id';

    protected static ?string $recordTitleAttribute = 'public_id';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-document-text';

    protected static string|UnitEnum|null $navigationGroup = 'Commerce';

    protected static ?string $navigationLabel = 'Service requests';

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('public_id')->label('Reference')->searchable()->wrap(),
            TextColumn::make('approval_status')->label('Approval')->badge(),
            TextColumn::make('payment_status')->label('Payment')->badge(),
            TextColumn::make('revision')->label('Revision'),
            TextColumn::make('created_at')->dateTime()->sortable(),
        ])->filters([SelectFilter::make('approval_status')->options(['pending' => 'Pending', 'clarification' => 'Clarification', 'approved' => 'Approved', 'rejected' => 'Rejected', 'expired' => 'Expired'])])
            ->recordActions([ViewAction::make()])->defaultSort('id', 'desc')->emptyStateHeading('No service requests yet');
    }

    public static function getPages(): array
    {
        return ['index' => ListOrders::route('/'), 'view' => ViewOrder::route('/{record}')];
    }
}
