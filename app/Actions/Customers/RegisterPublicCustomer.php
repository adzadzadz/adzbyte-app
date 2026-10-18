<?php

namespace App\Actions\Customers;

use App\Enums\UserRole;
use App\Exceptions\ExistingAccountRequiresAuthentication;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class RegisterPublicCustomer
{
    public function handle(string $name, string $email): void
    {
        $user = DB::transaction(function () use ($name, $email): ?User {
            try {
                $user = app(CreateProvisionalCustomer::class)->handle($name, $email);
            } catch (ExistingAccountRequiresAuthentication) {
                $user = User::query()->whereRaw('lower(email) = ?', [mb_strtolower(trim($email))])->lockForUpdate()->first();
            }

            // Public activation must never bootstrap staff or alter an existing identity.
            return $user !== null && ! $user->hasVerifiedEmail()
                && $user->roles()->count() === 1 && $user->hasRole(UserRole::Customer->value)
                && ! $user->permissions()->exists() ? $user : null;
        }, attempts: 5);

        if ($user !== null) {
            app(SendCustomerAccountActivation::class)->handle($user);
        }
    }
}
