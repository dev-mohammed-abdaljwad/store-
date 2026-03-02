<?php

namespace App\Domain\Store\Enums;

enum StockMovementType: string
{
    case IN  = 'in';   // إضافة للمخزون  (شراء، إلغاء بيع)
    case OUT = 'out';  // خصم من المخزون (بيع، إلغاء شراء)

    public function label(): string
    {
        return match ($this) {
            self::IN  => 'وارد',
            self::OUT => 'صادر',
        };
    }

    public function opposite(): self
    {
        return match ($this) {
            self::IN  => self::OUT,
            self::OUT => self::IN,
        };
    }
}
