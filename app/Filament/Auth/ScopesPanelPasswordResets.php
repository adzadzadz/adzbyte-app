<?php

namespace App\Filament\Auth;

use App\Enums\UserRole;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Builder;
use SensitiveParameter;

trait ScopesPanelPasswordResets
{
    protected function getCredentialsFromFormData(#[SensitiveParameter] array $data): array
    {
        $roles = match (Filament::getCurrentOrDefaultPanel()->getId()) {
            'admin' => [UserRole::Administrator->value, UserRole::SuperAdmin->value],
            'account' => [UserRole::Customer->value],
            default => [],
        };

        return [...parent::getCredentialsFromFormData($data),
            'email' => mb_strtolower(trim($data['email'])),
            'panel_access' => fn (Builder $query) => $query->whereHas('roles', fn (Builder $rolesQuery) => $rolesQuery->where('guard_name', 'web')->whereIn('name', $roles)),
        ];
    }
}
