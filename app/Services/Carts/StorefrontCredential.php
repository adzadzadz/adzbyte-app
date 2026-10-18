<?php

namespace App\Services\Carts;

use App\Enums\StorefrontAbility;
use App\Models\User;
use Laravel\Sanctum\PersonalAccessToken;

final class StorefrontCredential
{
    public static function abilities(): array
    {
        return array_column(StorefrontAbility::cases(), 'value');
    }

    public static function allowed(User $user, mixed $token): bool
    {
        return $token instanceof PersonalAccessToken
            && $token->tokenable_id === $user->id
            && $token->tokenable_type === $user->getMorphClass()
            && $user->hasVerifiedEmail()
            && ! $user->roles()->exists() && ! $user->permissions()->exists()
            && is_array($token->abilities) && $token->abilities !== []
            && count(array_filter($token->abilities, is_string(...))) === count($token->abilities)
            && array_diff($token->abilities, self::abilities()) === []
            && ! ($token->expires_at?->isPast() ?? false);
    }
}
