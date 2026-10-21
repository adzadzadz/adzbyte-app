<?php

namespace App\Filament\Resources\PackageCheckouts;

use App\Filament\Resources\PackageCheckouts\Pages\ListPackageCheckouts;
use App\Filament\Resources\PackageCheckouts\Pages\ViewPackageCheckout;
use App\Models\PackageCheckout;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class PackageCheckoutResource extends Resource
{
    protected static ?string $model = PackageCheckout::class;

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            TextEntry::make('id')->label('Checkout reference')->copyable(),
            TextEntry::make('status')->badge(),
            TextEntry::make('full_name')->label('Full name')->state(fn (PackageCheckout $record) => $record->contactName()),
            TextEntry::make('email'), TextEntry::make('phone'),
            TextEntry::make('package_name'), TextEntry::make('package_code'),
            TextEntry::make('amount_minor')->label('Amount (centavos)'),
            TextEntry::make('currency'), TextEntry::make('quantity'), TextEntry::make('offer_version'),
            TextEntry::make('provider_mode'), TextEntry::make('provider_intent_id')->label('PayMongo intent'),
            TextEntry::make('provider_payment_id')->label('PayMongo payment'),
            TextEntry::make('paid_at')->dateTime(), TextEntry::make('created_at')->dateTime(),
            TextEntry::make('creation_phase'),
            TextEntry::make('captureDelivery.status')->label('Form capture state'),
            TextEntry::make('captureDelivery.attempts')->label('Form capture attempts'),
            TextEntry::make('captureDelivery.last_error_code')->label('Last form capture result'),
            TextEntry::make('fulfillment_status')->state(fn (PackageCheckout $record) => $record->fulfillmentStatus()),
            TextEntry::make('purchaseDelivery.status')->label('Delivery state'),
            TextEntry::make('purchaseDelivery.attempts')->label('Delivery attempts'),
            TextEntry::make('purchaseDelivery.last_error_code')->label('Last delivery result'),
            TextEntry::make('purchaseDelivery.next_attempt_at')->label('Next delivery attempt')->dateTime(),
            TextEntry::make('purchaseDelivery.delivered_at')->label('Notifications delivered')->dateTime(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('id')->label('Reference')->searchable()->copyable(),
            TextColumn::make('status')->badge(),
            TextColumn::make('package_name'),
            TextColumn::make('full_name')->label('Full name')->state(fn (PackageCheckout $record) => $record->contactName()),
            TextColumn::make('purchaseDelivery.status')->label('Delivery')->badge(),
            TextColumn::make('amount_minor')->label('Centavos'),
            TextColumn::make('currency'), TextColumn::make('provider_mode'),
            TextColumn::make('paid_at')->dateTime()->sortable(),
            TextColumn::make('created_at')->dateTime()->sortable(),
        ])->filters([
            SelectFilter::make('status')->options(array_combine(
                ['creating', 'pending', 'unknown', 'paid', 'failed', 'expired'],
                ['Creating', 'Pending', 'Needs review', 'Paid', 'Failed', 'Expired'],
            )),
        ])->defaultSort('created_at', 'desc')->recordActions([ViewAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ListPackageCheckouts::route('/'), 'view' => ViewPackageCheckout::route('/{record}')];
    }
}
