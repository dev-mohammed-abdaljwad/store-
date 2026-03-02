<?php

namespace App\Domain\Store\Enums;

enum UserRole: string
{
    case SUPER_ADMIN  = 'super_admin';
    case STORE_OWNER  = 'store_owner';

    public function label(): string
    {
        return match ($this) {
            self::SUPER_ADMIN => 'مدير النظام',
            self::STORE_OWNER => 'صاحب المتجر',
        };
    }

    public function isSuperAdmin(): bool
    {
        return $this === self::SUPER_ADMIN;
    }

    public function isStoreOwner(): bool
    {
        return $this === self::STORE_OWNER;
    }
}
