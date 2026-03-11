<?php
namespace App\Models;

use App\Services\CacheService;
use App\Models\Traits\BelongsToStore;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Supplier extends Model
{
    use BelongsToStore, SoftDeletes;

    protected $fillable = [
        'store_id',
        'name',
        'phone',
        'address',
        'notes',
    ];

    public function purchaseInvoices(): HasMany
    {
        return $this->hasMany(PurchaseInvoice::class);
    }

    public function purchaseReturns(): HasMany
    {
        return $this->hasMany(PurchaseReturn::class);
    }

    public function financialTransactions(): HasMany
    {
        return $this->hasMany(FinancialTransaction::class, 'party_id')
                    ->where('party_type', 'supplier');
    }

    public function getBalanceAttribute($value): float
    {
        return app(CacheService::class)->getSupplierBalance($this->id);
    }
}
