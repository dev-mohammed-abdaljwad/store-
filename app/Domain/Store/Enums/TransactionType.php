<?php

namespace App\Domain\Store\Enums;

enum TransactionType: string
{
    // ── Financial Ledger (financial_transactions) ─────────────────
    case DEBIT  = 'debit';   // مدين  — على العميل / على المتجر للمورد
    case CREDIT = 'credit';  // دائن  — للعميل / للمورد

    public function label(): string
    {
        return match ($this) {
            self::DEBIT  => 'مدين',
            self::CREDIT => 'دائن',
        };
    }

    public function opposite(): self
    {
        return match ($this) {
            self::DEBIT  => self::CREDIT,
            self::CREDIT => self::DEBIT,
        };
    }
}
