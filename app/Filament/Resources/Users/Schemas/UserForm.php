<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Enums\UserRole;
use App\Models\Role;
use App\Models\User;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

final class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Identity')
                    ->description('Update contact identity without handling or revealing a password.')
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('email')
                            ->email()
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true)
                            ->helperText('Changing the email requires the user to verify the new address.'),
                    ])
                    ->columns(2),
                Section::make('Access')
                    ->description('Only a super administrator can change role assignments.')
                    ->schema([
                        Select::make('roles')
                            ->options(fn (): array => Role::query()
                                ->where('guard_name', 'web')
                                ->orderBy('name')
                                ->pluck('name', 'name')
                                ->map(fn (string $role): string => Str::headline($role))
                                ->all())
                            ->multiple()
                            ->searchable()
                            ->preload()
                            ->required()
                            ->disabled(function (?User $record): bool {
                                $actor = auth()->user();

                                return ! $actor instanceof User
                                    || $record === null
                                    || $actor->is($record)
                                    || ! $actor->hasRole(UserRole::SuperAdmin->value);
                            }),
                    ]),
            ]);
    }
}
