<?php

namespace App\Filament\Resources\PackageCheckouts;

use App\Filament\Pages\DataRecords;
use App\Filament\Resources\PackageCheckouts\Pages\ListPackageCheckouts;
use App\Filament\Resources\PackageCheckouts\Pages\ViewPackageCheckout;
use App\Models\PackageCheckout;
use App\Services\Carts\MinorUnits;
use App\Support\Filament\HasLockedCommerceNavigation;
use App\Support\Filament\RecordVisibilityActions;
use App\Support\Records\RecordVisibility;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PackageCheckoutResource extends Resource
{
    use HasLockedCommerceNavigation;

    protected static string|\UnitEnum|null $navigationGroup = 'Commerce';

    protected static ?string $model = PackageCheckout::class;

    public static function getEloquentQuery(): Builder
    {
        return RecordVisibility::query(parent::getEloquentQuery(), 'all')->with([
            'captureDelivery' => fn ($relation) => RecordVisibility::query($relation->getQuery(), 'all'),
            'purchaseDelivery' => fn ($relation) => RecordVisibility::query($relation->getQuery(), 'all'),
        ]);
    }

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
                self::deliveryState('captureDelivery', 'Form capture state'),
                self::deliveryDiagnostic('captureDelivery.attempts', 'Form capture attempts'),
                self::deliveryDiagnostic('captureDelivery.last_error_code', 'Last form capture result'),
                TextEntry::make('fulfillment_status')->state(fn (PackageCheckout $record) => $record->fulfillmentStatus()),
                self::deliveryState('purchaseDelivery', 'Delivery state'),
                self::deliveryDiagnostic('purchaseDelivery.attempts', 'Delivery attempts'),
                self::deliveryDiagnostic('purchaseDelivery.last_error_code', 'Last delivery result'),
                self::deliveryDiagnostic('purchaseDelivery.next_attempt_at', 'Next delivery attempt')->dateTime(),
                self::deliveryDiagnostic('purchaseDelivery.delivered_at', 'Notifications delivered')->dateTime(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            RecordVisibilityActions::column(),
            TextColumn::make('id')->label('Reference')->searchable()->copyable(),
            TextColumn::make('status')->badge(),
            TextColumn::make('package_name'),
            TextColumn::make('full_name')->label('Full name')->state(fn (PackageCheckout $record) => $record->contactName()),
            TextColumn::make('purchaseDelivery.status')->label('Delivery')->badge()
                ->state(fn (PackageCheckout $record): ?string => self::deliveryStatus($record, 'purchaseDelivery')),
            TextColumn::make('amount_minor')->label('Amount')->formatStateUsing(fn (string $state): string => MinorUnits::formatPhp($state)),
            TextColumn::make('currency'), TextColumn::make('provider_mode'),
            TextColumn::make('paid_at')->dateTime()->sortable(),
            TextColumn::make('created_at')->dateTime()->sortable(),
        ])->filters([RecordVisibilityActions::filter(),
            SelectFilter::make('status')->options(array_combine(
                ['creating', 'pending', 'unknown', 'paid', 'failed', 'expired'],
                ['Creating', 'Pending', 'Needs review', 'Paid', 'Failed', 'Expired'],
            )),
        ])->defaultSort('created_at', 'desc')->recordActions([RecordVisibilityActions::delete(), RecordVisibilityActions::restore(), ViewAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ListPackageCheckouts::route('/'), 'view' => ViewPackageCheckout::route('/{record}')];
    }

    private static function deliveryStatus(PackageCheckout $record, string $relationship): ?string
    {
        $delivery = $record->{$relationship};

        return $delivery === null ? null : (RecordVisibility::isDeleted($delivery) ? 'Deleted' : $delivery->status);
    }

    private static function deliveryState(string $relationship, string $label): TextEntry
    {
        return TextEntry::make($relationship.'.status')->label($label)
            ->state(fn (PackageCheckout $record): ?string => self::deliveryStatus($record, $relationship))
            ->helperText(fn (PackageCheckout $record): ?string => self::deliveryStatus($record, $relationship) === 'Deleted'
                ? 'Diagnostics hidden. The delivery record is retained in Data management.' : null)
            ->url(fn (PackageCheckout $record): ?string => self::deliveryStatus($record, $relationship) === 'Deleted' && DataRecords::canAccess()
                ? DataRecords::getUrl(['type' => 'package-deliveries', 'record' => $record->{$relationship}->getKey()]) : null);
    }

    private static function deliveryDiagnostic(string $name, string $label): TextEntry
    {
        $relationship = explode('.', $name)[0];

        return TextEntry::make($name)->label($label)->visible(fn (PackageCheckout $record): bool => $record->{$relationship} !== null
            && ! RecordVisibility::isDeleted($record->{$relationship}));
    }
}
