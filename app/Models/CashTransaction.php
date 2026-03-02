<?php
namespace App\Models;

use App\Domain\Store\Enums\CashTransactionType;
use App\Models\Traits\BelongsToStore;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

class CashTransaction extends Model
{
    use BelongsToStore;

    protected $fillable = [
        'store_id',
        'type',
        'amount',
        'reference_type',
        'reference_id',
        'description',
        'transaction_date',
        'created_by',
    ];

    protected $casts = [
        'amount'           => 'float',
        'type'             => CashTransactionType::class,
        'transaction_date' => 'date',
    ];

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // ── Scopes ───────────────────────────────────────────────────

    public function scopeIn($query)
    {
        return $query->where('type', CashTransactionType::IN);
    }

    public function scopeOut($query)
    {
        return $query->where('type', CashTransactionType::OUT);
    }

    public function scopeOnDate($query, string $date)
    {
        return $query->where('transaction_date', $date);
    }

    // ── Static Helpers ───────────────────────────────────────────

    /**
     * الرصيد النقدي الحالي للمتجر.
     * = opening_balance + SUM(in) - SUM(out)
     */
    public static function getCurrentBalance(int $storeId): float
    {
        $totals = DB::table('cash_transactions')
            ->selectRaw("COALESCE(SUM(CASE WHEN type = 'opening_balance' THEN amount ELSE 0 END), 0) as opening_total")
            ->selectRaw("COALESCE(SUM(CASE WHEN type = 'in' THEN amount ELSE 0 END), 0) as in_total")
            ->selectRaw("COALESCE(SUM(CASE WHEN type = 'out' THEN amount ELSE 0 END), 0) as out_total")
            ->where('store_id', $storeId)
            ->first();

        return round(
            (float) ($totals->opening_total ?? 0)
            + (float) ($totals->in_total ?? 0)
            - (float) ($totals->out_total ?? 0),
            2
        );
    }
}
