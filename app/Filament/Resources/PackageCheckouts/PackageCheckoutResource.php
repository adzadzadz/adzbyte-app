<?php

namespace App\Filament\Resources\PackageCheckouts;

use App\Filament\Resources\PackageCheckouts\Pages\ListPackageCheckouts;
use App\Filament\Resources\PackageCheckouts\Pages\ViewPackageCheckout;
use App\Models\PackageCheckout;
use App\Services\Carts\MinorUnits;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
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
            Section::make('Purchase')->columns(2)->schema([
                TextEntry::make('id')->label('Checkout reference')->copyable(),
                TextEntry::make('status')->badge(),
                TextEntry::make('package_name'), TextEntry::make('package_code'),
                TextEntry::make('amount_minor')->label('Amount')->formatStateUsing(fn (string $state): string => MinorUnits::formatPhp($state)),
                TextEntry::make('currency'), TextEntry::make('quantity'), TextEntry::make('offer_version'),
                TextEntry::make('provider_mode'), TextEntry::make('provider_intent_id')->label('PayMongo intent'),
                TextEntry::make('provider_payment_id')->label('PayMongo payment'),
                TextEntry::make('paid_at')->dateTime(), TextEntry::make('created_at')->dateTime(),
                TextEntry::make('creation_phase'),
            ]),
            Section::make('Contact')->columns(2)->schema([
                TextEntry::make('full_name')->label('Full name')->state(fn (PackageCheckout $record) => $record->contactName()),
                TextEntry::make('email'), TextEntry::make('phone'),
            ]),
            Section::make('Delivery diagnostics')->columns(2)->schema([
                TextEntry::make('captureDelivery.status')->label('Form capture state'),
                TextEntry::make('captureDelivery.attempts')->label('Form capture attempts'),
                TextEntry::make('captureDelivery.last_error_code')->label('Last form capture result'),
                TextEntry::make('fulfillment_status')->state(fn (PackageCheckout $record) => $record->fulfillmentStatus()),
                TextEntry::make('purchaseDelivery.status')->label('Delivery state'),
                TextEntry::make('purchaseDelivery.attempts')->label('Delivery attempts'),
                TextEntry::make('purchaseDelivery.last_error_code')->label('Last delivery result'),
                TextEntry::make('purchaseDelivery.next_attempt_at')->label('Next delivery attempt')->dateTime(),
                TextEntry::make('purchaseDelivery.delivered_at')->label('Notifications delivered')->dateTime(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->modifyQueryUsing(fn ($query) => $query->with(['purchaseDelivery', 'captureDelivery']))->columns([
            TextColumn::make('id')->label('Reference')->searchable()->copyable(),
            TextColumn::make('status')->badge(),
            TextColumn::make('package_name'),
            TextColumn::make('full_name')->label('Full name')->state(fn (PackageCheckout $record) => $record->contactName()),
            TextColumn::make('purchaseDelivery.status')->label('Delivery')->badge(),
            TextColumn::make('amount_minor')->label('Amount')->formatStateUsing(fn (string $state): string => MinorUnits::formatPhp($state)),
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
