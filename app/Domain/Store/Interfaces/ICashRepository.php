<?php

namespace App\Domain\Store\Interfaces;

use App\Domain\Store\Enums\CashTransactionType;

interface ICashRepository
{
    /**
     * تسجيل حركة نقدية.
     */
    public function addTransaction(
        string              $storeId,
        CashTransactionType $type,
        float               $amount,
        string              $description,
        string              $createdBy,
        ?string             $referenceType = null,
        ?string             $referenceId   = null,
        ?string             $date          = null,
    ): void;

    /**
     * حساب الرصيد النقدي الحالي.
     * = opening_balance + SUM(in) - SUM(out)
     */
    public function getCurrentBalance(string $storeId): float;

    /**
     * تقرير نقدي بفترة زمنية.
     */
    public function getDailyReport(string $storeId, string $date): array;

    /**
     * عكس حركة نقدية (عند إلغاء فاتورة).
     */
    public function reverseTransaction(
        string $referenceType,
        string $referenceId,
        string $storeId,
        string $createdBy,
    ): void;

    /**
     * التحقق مما إذا كان تم تسجيل رصيد افتتاحي مسبقاً
     */
    public function hasOpeningBalance(string $storeId): bool;
}
