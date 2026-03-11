<?php

namespace App\Models;

use App\Models\Traits\BelongsToStore;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SalesReturn extends Model
{
    use BelongsToStore;

    protected $fillable = [
        'store_id',
        'customer_id',
        'sales_invoice_id',
        'return_number',
        'total_amount',
        'refund_amount',
        'remaining_amount',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'total_amount' => 'float',
        'refund_amount' => 'float',
        'remaining_amount' => 'float',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(SalesInvoice::class, 'sales_invoice_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(\App\Models\SalesReturnItem::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public static function generateNumber(int $storeId): string
    {
        $year = now()->year;
        $last = static::withoutGlobalScopes()
            ->where('store_id', $storeId)
            ->whereYear('created_at', $year)
            ->lockForUpdate()
            ->count();

        return sprintf('SR-%d-%04d', $year, $last + 1);
    }
}
