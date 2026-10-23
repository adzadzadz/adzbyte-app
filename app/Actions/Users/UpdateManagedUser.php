<?php

namespace App\Actions\Users;

use App\Enums\UserRole;
use App\Models\Role;
use App\Models\User;
use App\Support\Database\RetryingTransaction;
use App\Support\Records\RecordVisibility;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class UpdateManagedUser
{
    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<int, mixed>  $roles
     */
    public function handle(User $actor, User $target, array $attributes, array $roles): User
    {
        Gate::forUser($actor)->authorize('update', $target);

        $roleNames = collect($roles)
            ->filter(fn (mixed $role): bool => is_string($role))
            ->map(fn (string $role): string => trim($role))
            ->filter()
            ->unique()
            ->values()
            ->all();

        $data = [
            'name' => trim((string) Arr::get($attributes, 'name')),
            'email' => Str::lower(trim((string) Arr::get($attributes, 'email'))),
            'roles' => $roleNames,
        ];

        Validator::make($data, [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'email:rfc',
                'max:255',
                Rule::unique(User::class, 'email')->ignore($target->getKey()),
            ],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => [
                'required',
                'string',
                'distinct',
                Rule::exists(config('permission.table_names.roles'), 'name')
                    ->where('guard_name', 'web'),
            ],
        ])->validate();

        $applicationRoles = array_map(
            static fn (UserRole $role): string => $role->value,
            UserRole::cases(),
        );

        if (array_intersect($roleNames, $applicationRoles) === []) {
            throw ValidationException::withMessages([
                'roles' => 'At least one application access role is required.',
            ]);
        }

        return RetryingTransaction::run(function () use ($actor, $target, $data, $roleNames): User {
            $ids = User::role(UserRole::SuperAdmin->value)->pluck('id')->push($actor->id)->push($target->id)->unique()->sort()->values();
            $users = User::whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get();
            $managedUser = $users->firstWhere('id', $target->id);
            $actor = $users->firstWhere('id', $actor->id);
            abort_unless($actor && $managedUser, 404);
            RecordVisibility::assertVisible($actor);
            RecordVisibility::assertVisible($managedUser);
            $selectedRoles = Role::where('guard_name', 'web')->whereIn('name', $roleNames)->orderBy('id')->lockForUpdate()->get();
            foreach ($selectedRoles as $role) {
                RecordVisibility::assertVisible($role);
            }
            abort_unless($selectedRoles->count() === count($roleNames), 422);
            Gate::forUser($actor)->authorize('update', $managedUser);
            $currentRoles = $managedUser->getRoleNames()->sort()->values()->all();
            $sortedRoles = collect($roleNames)->sort()->values()->all();
            $rolesChanged = $currentRoles !== $sortedRoles;

            if ($rolesChanged) {
                Gate::forUser($actor)->authorize('assignRoles', $managedUser);

                if ($actor->is($managedUser)) {
                    throw ValidationException::withMessages([
                        'roles' => 'You cannot change your own role assignments.',
                    ]);
                }

                $this->protectLastSuperAdministrator($managedUser, $roleNames);
            }

            if ($managedUser->email !== $data['email']) {
                $managedUser->email_verified_at = null;
            }

            $managedUser->fill(Arr::only($data, ['name', 'email']))->save();

            if ($rolesChanged) {
                $managedUser->syncRoles($roleNames);
            }

            return $managedUser->refresh()->load('roles');
        });
    }

    /**
     * @param  list<string>  $roleNames
     */
    private function protectLastSuperAdministrator(User $target, array $roleNames): void
    {
        $superAdministrator = UserRole::SuperAdmin->value;

        if (! $target->hasRole($superAdministrator) || in_array($superAdministrator, $roleNames, true)) {
            return;
        }

        $superAdministratorCount = RecordVisibility::activeSuperAdministratorCount();

        if ($superAdministratorCount <= 1) {
            throw ValidationException::withMessages([
                'roles' => 'The final super administrator cannot be demoted.',
            ]);
        }
    }
}
