<?php

namespace App\Support\Filament;

use App\Enums\PortalCapability;
use App\Models\SupportCategory;
use App\Models\User;
use App\Support\PortalAccess;
use App\Support\PortalOptions;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;

final class PortalForms
{
    public static function work(): array
    {
        return [TextInput::make('title')->required()->maxLength(255), Textarea::make('summary')->required()->maxLength(10000)->rows(4),
            Select::make('status')->options(PortalOptions::WORK_STATUSES)->required()->default('not_started'),
            Textarea::make('next_step')->label('Next step for the customer')->maxLength(2000)->rows(2),
            DatePicker::make('target_date')->label('Target date')->native()->helperText('An optional planning date, not a response-time promise.'),
            Select::make('assigned_to')->label('Assigned staff')->options(fn () => self::staff(PortalCapability::ViewWork))->searchable()];
    }

    public static function staff(PortalCapability $capability): array
    {
        return User::role(['administrator', 'super_admin'])->whereNotNull('email_verified_at')->orderBy('name')->get()
            ->filter(fn (User $user) => PortalAccess::staff($user, $capability))->pluck('name', 'id')->all();
    }

    public static function categories(bool $active = true): array
    {
        return SupportCategory::query()->when($active, fn ($q) => $q->where('active', true))->orderBy('name')->pluck('name', 'public_id')->all();
    }
}
