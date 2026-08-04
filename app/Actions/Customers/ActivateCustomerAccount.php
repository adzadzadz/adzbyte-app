<?php

namespace App\Actions\Customers;

use App\Enums\UserRole;
use App\Events\CustomerAccountActivated;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

final class ActivateCustomerAccount
{
    public function handle(User $user, string $password): bool
    {
        $validated = Validator::make(['password' => $password], [
            'password' => ['required', Password::defaults()],
        ])->validate();

        $activatedUser = DB::transaction(function () use ($user, $validated): ?User {
            $lockedUser = User::query()->lockForUpdate()->findOrFail($user->getKey());

            if (! $lockedUser->hasRole(UserRole::Customer->value)
                || $lockedUser->hasVerifiedEmail()) {
                return null;
            }

            $lockedUser->forceFill([
                'password' => $validated['password'],
                'remember_token' => Str::random(60),
            ])->save();

            $lockedUser->markEmailAsVerified();

            return $lockedUser;
        });

        if ($activatedUser === null) {
            return false;
        }

        event(new Verified($activatedUser));
        CustomerAccountActivated::dispatch($activatedUser);

        $user->refresh();

        return true;
    }
}
