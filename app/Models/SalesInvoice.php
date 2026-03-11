<?php

namespace App\Models;

use App\Domain\Store\Enums\InvoiceStatus;
use App\Models\Traits\BelongsToStore;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class SalesInvoice extends Model
{
    use BelongsToStore, SoftDeletes;

    protected $fillable = [
        'store_id',
        'invoice_number',
        'customer_id',
        'total_amount',
        'discount_amount',
        'net_amount',
        'paid_amount',
        'remaining_amount',
        'status',
        'notes',
        'cancel_reason',
        'cancelled_by',
        'cancelled_at',
        'created_by',
    ];

    protected $casts = [
        'total_amount'     => 'float',
        'discount_amount'  => 'float',
        'net_amount'       => 'float',
        'paid_amount'      => 'float',
        'remaining_amount' => 'float',
        'status'           => InvoiceStatus::class,
        'cancelled_at'     => 'datetime',
    ];

    // ── Relationships ────────────────────────────────────────────

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(SalesInvoiceItem::class, 'invoice_id');
    }

    public function returns(): HasMany
    {
        return $this->hasMany(SalesReturn::class, 'sales_invoice_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    // ── Scopes ───────────────────────────────────────────────────

    public function scopeConfirmed($query)
    {
        return $query->where('status', InvoiceStatus::CONFIRMED);
    }

    public function scopeCancelled($query)
    {
        return $query->where('status', InvoiceStatus::CANCELLED);
    }

    public function scopeForCustomer($query, int $customerId)
    {
        return $query->where('customer_id', $customerId);
    }

    public function scopeDateBetween($query, string $from, string $to)
    {
        return $query->whereBetween('created_at', [$from, $to]);
    }

    public function isConfirmed(): bool
    {
        return $this->status === InvoiceStatus::CONFIRMED;
    }

    public function isCancelled(): bool
    {
        return $this->status === InvoiceStatus::CANCELLED;
    }

    public function isPartiallyPaid(): bool
    {
        return $this->paid_amount > 0 && $this->remaining_amount > 0;
    }

    public function isFullyPaid(): bool
    {
        return $this->remaining_amount == 0;
    }

    public static function generateNumber(int $storeId): string
    {
        $year  = now()->year;
        $last  = static::withoutGlobalScopes()
                       ->where('store_id', $storeId)
                       ->whereYear('created_at', $year)
                       ->lockForUpdate()
                       ->count();

        return sprintf('INV-%d-%04d', $year, $last + 1);
    }
}


