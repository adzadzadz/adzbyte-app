<?php

namespace App\Actions\Customers;

use App\Enums\UserRole;
use App\Events\CustomerAccountProvisioned;
use App\Exceptions\ExistingAccountRequiresAuthentication;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class CreateProvisionalCustomer
{
    public function handle(string $name, string $email): User
    {
        $validated = Validator::make([
            'name' => trim($name),
            'email' => Str::lower(trim($email)),
        ], [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255'],
        ])->validate();

        if (User::query()->whereRaw('lower(email) = ?', [$validated['email']])->exists()) {
            throw new ExistingAccountRequiresAuthentication;
        }

        try {
            $user = DB::transaction(function () use ($validated): User {
                $user = User::query()->create([
                    'name' => $validated['name'],
                    'email' => $validated['email'],
                    'password' => Str::random(64),
                ]);

                $user->assignRole(UserRole::Customer->value);

                return $user;
            });
        } catch (UniqueConstraintViolationException) {
            throw new ExistingAccountRequiresAuthentication;
        }

        CustomerAccountProvisioned::dispatch($user);

        return $user;
    }
}
