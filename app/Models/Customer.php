<?php

// ══════════════════════════════════════════════════════════════════
// Customer.php
// ══════════════════════════════════════════════════════════════════

namespace App\Models;

use App\Models\Traits\BelongsToStore;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends Model
{
    use BelongsToStore, SoftDeletes;

    protected $fillable = [
        'store_id',
        'name',
        'phone',
        'address',
        'notes',
    ];

    // ── Relationships ────────────────────────────────────────────

    public function salesInvoices(): HasMany
    {
        return $this->hasMany(SalesInvoice::class);
    }

    public function financialTransactions(): HasMany
    {
        return $this->hasMany(FinancialTransaction::class, 'party_id')
                    ->where('party_type', 'customer');
    }


    public function getBalanceAttribute($value): float
    {
        if ($value !== null) {
            return round((float) $value, 2);
        }

        $debit  = $this->financialTransactions()->where('type', 'debit')->sum('amount');
        $credit = $this->financialTransactions()->where('type', 'credit')->sum('amount');
        return round($debit - $credit, 2);
    }
}
    


