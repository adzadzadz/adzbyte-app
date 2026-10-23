<?php

namespace App\Filament\Resources\Payments;

use App\Filament\Resources\Payments\Pages\ListPayments;
use App\Filament\Resources\Payments\Pages\ViewPayment;
use App\Models\PaymentAttempt;
use App\Models\User;
use App\Policies\PaymentAttemptPolicy;
use App\Services\Carts\MinorUnits;
use App\Support\Filament\HasLockedCommerceNavigation;
use App\Support\Filament\RecordVisibilityActions;
use App\Support\Records\RecordVisibility;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

final class PaymentResource extends Resource
{
    use HasLockedCommerceNavigation;

    protected static ?string $model = PaymentAttempt::class;

    protected static ?string $slug = 'payments';

    protected static ?string $recordRouteKeyName = 'public_id';

    protected static ?string $recordTitleAttribute = 'public_id';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-credit-card';

    protected static string|UnitEnum|null $navigationGroup = 'Commerce';

    protected static ?string $navigationLabel = 'Payments';

    public static function getEloquentQuery(): Builder
    {
        return RecordVisibility::query(parent::getEloquentQuery(), 'all');
    }

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
        return $table->modifyQueryUsing(fn ($query) => $query->with(['order.currentRevision']))->columns([
            RecordVisibilityActions::column(),
            TextColumn::make('public_id')->label('Payment reference')->searchable()->wrap(),
            TextColumn::make('order.public_id')->label('Order reference')->searchable()->wrap(),
            TextColumn::make('customer')->label('Customer')->state(fn (PaymentAttempt $record): ?string => $record->order?->currentRevision?->billing['name'] ?? null)->placeholder('Not recorded')->wrap(),
            TextColumn::make('state')->badge(),
            TextColumn::make('amount_minor')->label('Amount')->formatStateUsing(fn (string $state): string => MinorUnits::formatPhp($state)),
            TextColumn::make('livemode')->label('Mode')->formatStateUsing(fn (bool $state): string => $state ? 'Live' : 'Test'),
            TextColumn::make('created_at')->dateTime()->sortable(),
        ])->filters([RecordVisibilityActions::filter(), SelectFilter::make('state')->options(['creating' => 'Creating', 'unknown' => 'Needs reconciliation', 'active' => 'Active', 'closing' => 'Closure pending', 'paid' => 'Paid', 'expired' => 'Expired', 'rejected' => 'Rejected'])])
            ->recordActions([RecordVisibilityActions::delete(), RecordVisibilityActions::restore(), ViewAction::make()])->recordUrl(fn (PaymentAttempt $record): ?string => self::canView($record) ? self::getUrl('view', ['record' => $record]) : null)
            ->defaultSort('id', 'desc')->emptyStateHeading('No payment attempts yet');
    }

    public static function getPages(): array
    {
        return ['index' => ListPayments::route('/'), 'view' => ViewPayment::route('/{record}')];
    }
}
