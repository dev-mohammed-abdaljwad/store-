<?php

// ══════════════════════════════════════════════════════════════════
// Customer.php
// ══════════════════════════════════════════════════════════════════

namespace App\Models;

use App\Services\CacheService;
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

    public function salesReturns(): HasMany
    {
        return $this->hasMany(SalesReturn::class);
    }

    public function financialTransactions(): HasMany
    {
        return $this->hasMany(FinancialTransaction::class, 'party_id')
                    ->where('party_type', 'customer');
    }


    public function getBalanceAttribute($value): float
    {
        return app(CacheService::class)->getCustomerBalance($this->id);
    }
}
    


