<?php

namespace App\Filament\Auth;

use Filament\Auth\Pages\PasswordReset\ResetPassword as BaseReset;

class ResetPassword extends BaseReset
{
    use ScopesPanelPasswordResets;
}
