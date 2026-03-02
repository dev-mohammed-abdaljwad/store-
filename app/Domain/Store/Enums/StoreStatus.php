<?php

namespace App\Domain\Store\Enums;

enum StoreStatus: string
{
    case ACTIVE   = 'active';
    case INACTIVE = 'inactive';

    public function label(): string
    {
        return match ($this) {
            self::ACTIVE   => 'مفعّل',
            self::INACTIVE => 'موقوف',
        };
    }

    public function isActive(): bool
    {
        return $this === self::ACTIVE;
    }
}
