<?php
namespace App\Services;

use App\Domain\Store\Enums\CashTransactionType;
use App\Models\CashTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CashService
{
    
    public function setOpeningBalance(int $storeId, float $amount, int $createdBy): void
    {
        $exists = CashTransaction::where('store_id', $storeId)
                                 ->where('type', CashTransactionType::OPENING_BALANCE)
                                 ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'opening_balance' => 'تم تسجيل الرصيد الافتتاحي مسبقاً.',
            ]);
        }

        CashTransaction::create([
            'store_id'         => $storeId,
            'type'             => CashTransactionType::OPENING_BALANCE,
            'amount'           => $amount,
            'description'      => 'الرصيد الافتتاحي',
            'transaction_date' => today(),
            'created_by'       => $createdBy,
        ]);
    }
    public function getDailyReport(int $storeId, string $date, int $perPage = 20, bool $fast = false): array
    {
        $totals = DB::table('cash_transactions')
            ->selectRaw("COALESCE(SUM(CASE WHEN type = 'in' THEN amount ELSE 0 END), 0) as total_in")
            ->selectRaw("COALESCE(SUM(CASE WHEN type = 'out' THEN amount ELSE 0 END), 0) as total_out")
            ->where('store_id', $storeId)
            ->where('transaction_date', $date)
            ->first();

        $totalIn = (float) ($totals->total_in ?? 0);
        $totalOut = (float) ($totals->total_out ?? 0);

        $transactionsQuery = DB::table('cash_transactions')
            ->select(['type', 'amount', 'description', 'created_at'])
            ->where('store_id', $storeId)
            ->where('transaction_date', $date)
            ->orderBy('created_at');

        $transactions = ($fast
            ? $transactionsQuery->simplePaginate($perPage)
            : $transactionsQuery->paginate($perPage))
            ->through(fn($t) => [
                'type'        => CashTransactionType::from($t->type)->label(),
                'amount'      => $t->amount,
                'description' => $t->description,
                'time'        => \Carbon\Carbon::parse($t->created_at)->format('H:i'),
            ])
            ->withQueryString();

        return [
            'date'            => $date,
            'total_in'        => $totalIn,
            'total_out'       => $totalOut,
            'net'             => $totalIn - $totalOut,
            'current_balance' => CashTransaction::getCurrentBalance($storeId),
            'transactions'    => $transactions,
        ];
    }
    public function getCurrentBalance(int $storeId): float
    {
        return CashTransaction::getCurrentBalance($storeId);
    }
}