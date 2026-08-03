<?php

namespace App\Actions\Customers;

use App\Enums\UserRole;
use App\Models\User;
use App\Notifications\CustomerAccountActivation;
use LogicException;

class SendCustomerAccountActivation
{
    public function handle(User $user): bool
    {
        if (! $user->hasRole(UserRole::Customer->value)) {
            throw new LogicException('Only customer accounts may receive customer activation links.');
        }

        if ($user->hasVerifiedEmail()) {
            return false;
        }

        $user->notify(new CustomerAccountActivation);

        return true;
    }
}
