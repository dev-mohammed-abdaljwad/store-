<?php
namespace App\Models;

use App\Domain\Store\Enums\PartyType;
use App\Domain\Store\Enums\TransactionType;
use App\Models\Traits\BelongsToStore;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FinancialTransaction extends Model
{
    use BelongsToStore;

    protected $fillable = [
        'store_id',
        'party_type',
        'party_id',
        'type',
        'amount',
        'reference_type',
        'reference_id',
        'description',
        'created_by',
    ];

    protected $casts = [
        'amount'     => 'float',
        'type'       => TransactionType::class,
        'party_type' => PartyType::class,
    ];

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // ── Scopes ───────────────────────────────────────────────────

    public function scopeForParty($query, string $partyType, int $partyId)
    {
        return $query->where('party_type', $partyType)
                     ->where('party_id', $partyId);
    }

    public function scopeDebit($query)
    {
        return $query->where('type', TransactionType::DEBIT);
    }

    public function scopeCredit($query)
    {
        return $query->where('type', TransactionType::CREDIT);
    }

    // ── Static Helpers ───────────────────────────────────────────

    /**
     * حساب رصيد طرف معين.
     * موجب = مدين | سالب = دائن
     */
    public static function calculateBalance(
        int    $storeId,
        string $partyType,
        int    $partyId
    ): float {
        $query = static::withoutGlobalScopes()
                       ->where('store_id', $storeId)
                       ->where('party_type', $partyType)
                       ->where('party_id', $partyId);

        $debit  = (clone $query)->where('type', 'debit')->sum('amount');
        $credit = (clone $query)->where('type', 'credit')->sum('amount');

        return round($debit - $credit, 2);
    }
}
