<?php

namespace App\Enums;

enum TenantRole: string
{
    case Owner = 'owner';
    case Administrator = 'administrator';
    case User = 'user';

    public function label(): string
    {
        return match ($this) {
            self::Owner => 'Proprietário',
            self::Administrator => 'Administrador',
            self::User => 'Usuário',
        };
    }

    /**
     * Owner + Administrator share most privileges.
     */
    public function isPrivileged(): bool
    {
        return match ($this) {
            self::Owner, self::Administrator => true,
            self::User => false,
        };
    }

    /** Roles that can be assigned through the invitation UI. */
    public static function assignable(): array
    {
        return [self::Administrator, self::User];
    }
}
