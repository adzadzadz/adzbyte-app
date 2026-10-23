<?php

namespace App\Models;

use App\Enums\UserRole;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role as PermissionRole;

final class Role extends PermissionRole
{
    protected static function booted(): void
    {
        self::updating(function (Role $role): void {
            $originalName = (string) $role->getOriginal('name');

            if (! self::isApplicationRole($originalName)) {
                return;
            }

            if ($role->isDirty(['name', 'guard_name'])) {
                throw ValidationException::withMessages([
                    'name' => 'Application access roles cannot be renamed or moved to another guard.',
                ]);
            }
        });

        self::deleting(function (Role $role): void {
            if (self::isApplicationRole((string) $role->getOriginal('name'))) {
                throw ValidationException::withMessages([
                    'name' => 'Application access roles cannot be deleted.',
                ]);
            }
        });
    }

    private static function isApplicationRole(string $name): bool
    {
        foreach (UserRole::cases() as $role) {
            if ($role->value === $name) {
                return true;
            }
        }

        return false;
    }
}
