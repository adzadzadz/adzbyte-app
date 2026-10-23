<?php

namespace App\Filament\Resources\Users\Pages;

use App\Actions\Users\UpdateManagedUser;
use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;

final class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['roles'] = $this->getRecord()->roles()->pluck('name')->all();

        return Arr::only($data, ['name', 'email', 'roles']);
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $actor = auth()->user();

        abort_unless($actor instanceof User && $record instanceof User, 403);

        $roles = Arr::pull($data, 'roles', $record->getRoleNames()->all());

        return app(UpdateManagedUser::class)->handle(
            $actor,
            $record,
            $data,
            is_array($roles) ? $roles : [],
        );
    }
}
