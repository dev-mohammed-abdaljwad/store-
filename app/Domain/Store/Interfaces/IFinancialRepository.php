<?php

namespace App\Domain\Store\Interfaces;

use App\Domain\Store\Enums\PartyType;
use App\Domain\Store\Enums\TransactionType;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface IFinancialRepository
{
    public function getBalance(string $partyId, PartyType $partyType, string $storeId): float;
    public function recordTransaction(
        string $storeId,
        TransactionType $transactionType,
        PartyType $partyType,
        string $partyId,
        float $amount,
        string $description = ''
    ): void;
    public function addTransaction(
        string          $storeId,
        PartyType       $partyType,
        string          $partyId,
        TransactionType $type,
        float           $amount,
        string          $referenceType,
        string          $referenceId,
        string          $description,
        string          $createdBy,
    ): void;
    public function reverseTransactions(
        string $referenceType,
        string $referenceId,
        string $storeId,
        string $createdBy,
    ): void;

    /**
     * كشف حساب طرف مع التفاصيل.
     */
    public function getStatement(
        string    $partyId,
        PartyType $partyType,
        string    $storeId,
        ?string   $dateFrom = null,
        ?string   $dateTo   = null,
        int       $perPage  = 10,
    ): LengthAwarePaginator;
}
