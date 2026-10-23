<?php

namespace App\Filament\Resources\Payments;

use App\Filament\Resources\Payments\Pages\ListPayments;
use App\Filament\Resources\Payments\Pages\ViewPayment;
use App\Models\PaymentAttempt;
use App\Models\User;
use App\Policies\PaymentAttemptPolicy;
use App\Services\Carts\MinorUnits;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

final class PaymentResource extends Resource
{
    protected static ?string $model = PaymentAttempt::class;

    protected static ?string $slug = 'payments';

    protected static ?string $recordRouteKeyName = 'public_id';

    protected static ?string $recordTitleAttribute = 'public_id';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-credit-card';

    protected static string|UnitEnum|null $navigationGroup = 'Commerce';

    protected static ?string $navigationLabel = 'Payments';

    public static function canViewAny(): bool
    {
        $actor = auth()->id() === null ? null : User::find(auth()->id());

        return $actor !== null && app(PaymentAttemptPolicy::class)->viewAny($actor);
    }

    public static function canView(Model $record): bool
    {
        $actor = auth()->id() === null ? null : User::find(auth()->id());

        return $actor !== null && app(PaymentAttemptPolicy::class)->inspect($actor, $record);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('public_id')->label('Payment reference')->searchable()->wrap(),
            TextColumn::make('state')->badge(),
            TextColumn::make('amount_minor')->label('Amount')->formatStateUsing(fn (string $state): string => MinorUnits::formatPhp($state)),
            TextColumn::make('livemode')->label('Mode')->formatStateUsing(fn (bool $state): string => $state ? 'Live' : 'Test'),
            TextColumn::make('created_at')->dateTime()->sortable(),
        ])->filters([SelectFilter::make('state')->options(['creating' => 'Creating', 'unknown' => 'Needs reconciliation', 'active' => 'Active', 'closing' => 'Closure pending', 'paid' => 'Paid', 'expired' => 'Expired', 'rejected' => 'Rejected'])])
            ->recordActions([ViewAction::make()])->recordUrl(fn (PaymentAttempt $record): ?string => self::canView($record) ? self::getUrl('view', ['record' => $record]) : null)
            ->defaultSort('id', 'desc')->emptyStateHeading('No payment attempts yet');
    }

    public static function getPages(): array
    {
        return ['index' => ListPayments::route('/'), 'view' => ViewPayment::route('/{record}')];
    }
}
