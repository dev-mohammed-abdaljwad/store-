<?php

namespace App\Domain\Store\Enums;

enum InvoiceStatus: string
{
    case DRAFT      = 'draft';
    case CONFIRMED  = 'confirmed';
    case CANCELLED  = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::DRAFT      => 'مسودة',
            self::CONFIRMED  => 'مؤكدة',
            self::CANCELLED  => 'ملغاة',
        };
    }

    public function isDraft(): bool
    {
        return $this === self::DRAFT;
    }

    public function isConfirmed(): bool
    {
        return $this === self::CONFIRMED;
    }

    public function isCancelled(): bool
    {
        return $this === self::CANCELLED;
    }
}
