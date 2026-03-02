<?php

namespace App\Domain\Store\Repositories;

use App\Domain\Store\Enums\CashTransactionType;
use App\Domain\Store\Interfaces\ICashRepository;
use App\Models\CashTransaction;

class CashRepository implements ICashRepository
{
    public function addTransaction(
        string $storeId,
        CashTransactionType $type,
        float $amount,
        string $description,
        string $createdBy,
        ?string $referenceType = null,
        ?string $referenceId = null,
        ?string $date = null
    ): void {
        CashTransaction::create([
            'store_id'         => $storeId,
            'type'             => $type,
            'amount'           => $amount,
            'description'      => $description,
            'created_by'       => $createdBy,
            'reference_type'   => $referenceType,
            'reference_id'     => $referenceId,
            'transaction_date' => $date ?? today(),
        ]);
    }

    public function getCurrentBalance(string $storeId): float
    {
        $opening = CashTransaction::withoutGlobalScopes()
            ->where('store_id', $storeId)
            ->where('type', CashTransactionType::OPENING_BALANCE)
            ->sum('amount');
        $in = CashTransaction::withoutGlobalScopes()
            ->where('store_id', $storeId)
            ->where('type', CashTransactionType::IN)
            ->sum('amount');
        $out = CashTransaction::withoutGlobalScopes()
            ->where('store_id', $storeId)
            ->where('type', CashTransactionType::OUT)
            ->sum('amount');

        return round($opening + $in - $out, 2);
    }

    public function getDailyReport(string $storeId, string $date): array
    {
        $transactions = CashTransaction::where('store_id', $storeId)
            ->whereDate('transaction_date', $date)
            ->orderBy('created_at')
            ->get();

        $totalIn  = $transactions->where('type', CashTransactionType::IN)->sum('amount');
        $totalOut = $transactions->where('type', CashTransactionType::OUT)->sum('amount');

        return [
            'date'            => $date,
            'total_in'        => $totalIn,
            'total_out'       => $totalOut,
            'net'             => $totalIn - $totalOut,
            'current_balance' => $this->getCurrentBalance($storeId),
            'transactions'    => $transactions->map(fn($t) => [
                'type'        => $t->type->label(),
                'amount'      => $t->amount,
                'description' => $t->description,
                'time'        => $t->created_at->format('H:i'),
            ])->toArray(),
        ];
    }

    public function reverseTransaction(
        string $referenceType,
        string $referenceId,
        string $storeId,
        string $createdBy,
    ): void {
        // البحث عن الحركة الأصلية وعكسها
        $original = CashTransaction::withoutGlobalScopes()
            ->where('store_id', $storeId)
            ->where('reference_type', $referenceType)
            ->where('reference_id', $referenceId)
            ->first();

        if (!$original) {
            return;
        }

        // عكس الحركة: IN ← OUT والعكس
        $reversedType = $original->type === CashTransactionType::IN
            ? CashTransactionType::OUT
            : CashTransactionType::IN;

        CashTransaction::create([
            'store_id'         => $storeId,
            'type'             => $reversedType,
            'amount'           => $original->amount,
            'description'      => "عكس: {$original->description}",
            'created_by'       => $createdBy,
            'reference_type'   => "{$referenceType}_cancel",
            'reference_id'     => $referenceId,
            'transaction_date' => today(),
        ]);
    }

    public function hasOpeningBalance(string $storeId): bool
    {
        return CashTransaction::withoutGlobalScopes()
            ->where('store_id', $storeId)
            ->where('type', CashTransactionType::OPENING_BALANCE)
            ->exists();
    }
}
