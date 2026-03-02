<?php

namespace App\Domain\Store\Enums;

enum PartyType: string
{
    case CUSTOMER = 'customer';
    case SUPPLIER = 'supplier';

    public function label(): string
    {
        return match ($this) {
            self::CUSTOMER => 'عميل',
            self::SUPPLIER => 'مورد',
        };
    }
}
