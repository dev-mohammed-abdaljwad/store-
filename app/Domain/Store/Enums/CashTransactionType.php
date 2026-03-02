<?php

namespace App\Domain\Store\Enums;

enum CashTransactionType: string
{
    case IN              = 'in';               // نقدية واردة
    case OUT             = 'out';              // نقدية صادرة
    case OPENING_BALANCE = 'opening_balance';  // رصيد افتتاحي

    public function label(): string
    {
        return match ($this) {
            self::IN              => 'وارد',
            self::OUT             => 'صادر',
            self::OPENING_BALANCE => 'رصيد افتتاحي',
        };
    }
}
