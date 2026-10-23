<?php

namespace App\Support\Filament;

use App\Support\Records\RecordVisibility;
use Filament\Actions\Action;
use Filament\Forms\Components\Hidden;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class RecordVisibilityActions
{
    public static function delete(?string $name = 'deleteRecord'): Action
    {
        return self::action($name, true);
    }

    public static function restore(?string $name = 'restoreRecord'): Action
    {
        return self::action($name, false);
    }

    private static function action(string $name, bool $delete): Action
    {
        return Action::make($name)->label($delete ? 'Delete' : 'Restore')->color($delete ? 'danger' : 'success')
            ->icon($delete ? 'heroicon-o-trash' : 'heroicon-o-arrow-uturn-left')->requiresConfirmation()
            ->modalDescription($delete
                ? 'This removes the record from normal screens and marks it Deleted. Its data and history remain stored. Deleting an order or payment does not cancel, refund or stop provider processing.'
                : 'This returns the record to normal screens with its previous business status. Previously deleted children remain deleted.')
            ->schema([Hidden::make('visibility_version'), Hidden::make('record_type'), Hidden::make('record_id')])->fillForm(fn (Model $record) => ['visibility_version' => RecordVisibility::version($record), 'record_type' => $record::class, 'record_id' => (string) $record->getKey()])
            ->visible(fn (Model $record): bool => RecordVisibility::allowed(auth()->user()) && ($delete ? ! RecordVisibility::isDeleted($record) : $record->getAttribute('ui_deleted_at') !== null))
            ->action(function (Model $record, array $data, Action $action) use ($delete): void {
                try {
                    abort_unless(($data['record_type'] ?? null) === $record::class && ($data['record_id'] ?? null) === (string) $record->getKey(), 409, 'The selected record changed. Open the action again.');
                    app(RecordVisibility::class)->{$delete ? 'delete' : 'restore'}(auth()->user(), $record, (int) $data['visibility_version']);
                } catch (ValidationException|HttpException $exception) {
                    if ($exception instanceof HttpException && $exception->getStatusCode() !== 409) {
                        throw $exception;
                    }
                    Notification::make()->danger()->title('Record unchanged')->body($exception instanceof ValidationException ? implode(' ', $exception->validator->errors()->all()) : $exception->getMessage())->send();

                    $action->halt();
                }
                $current = RecordVisibility::query($record->newQuery()->whereKey($record->getKey()), 'all')->firstOrFail();
                $record->setRawAttributes($current->getAttributes(), sync: true);
                Notification::make()->success()->title($delete ? 'Record deleted · data retained' : 'Record restored')->send();
            });
    }

    public static function filter(): SelectFilter
    {
        return SelectFilter::make('record_visibility')->label('Visibility')->options(['active' => 'Active', 'deleted' => 'Deleted', 'all' => 'All records'])
            ->default('active')->selectablePlaceholder(false)
            ->query(fn (Builder $query, array $data) => RecordVisibility::query($query, $data['value'] ?? 'active'));
    }

    public static function column(): TextColumn
    {
        return TextColumn::make('record_visibility')->label('Visibility')->state(fn (Model $record) => RecordVisibility::isDeleted($record) ? 'Deleted' : 'Active')
            ->description(fn (Model $record) => RecordVisibility::isDeleted($record) && $record->getAttribute('ui_deleted_at') === null ? 'Owning record deleted · restore it in Data management' : null)
            ->badge()->color(fn (string $state) => $state === 'Deleted' ? 'danger' : 'success');
    }
}
