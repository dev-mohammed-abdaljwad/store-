<?php

namespace App\Models;

use App\Domain\Store\Enums\InvoiceStatus;
use App\Models\Traits\BelongsToStore;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PurchaseInvoice extends Model
{
    use BelongsToStore, SoftDeletes;

    protected $fillable = [
        'store_id',
        'invoice_number',
        'supplier_id',
        'total_amount',
        'paid_amount',
        'remaining_amount',
        'status',
        'notes',
        'attachment_path',
        'attachment_original_name',
        'cancel_reason',
        'cancelled_by',
        'cancelled_at',
        'created_by',
    ];

    protected $casts = [
        'total_amount'     => 'float',
        'paid_amount'      => 'float',
        'remaining_amount' => 'float',
        'status'           => InvoiceStatus::class,
        'cancelled_at'     => 'datetime',
    ];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseInvoiceItem::class, 'invoice_id');
    }

    public function returns(): HasMany
    {
        return $this->hasMany(PurchaseReturn::class, 'purchase_invoice_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeConfirmed($query)
    {
        return $query->where('status', InvoiceStatus::CONFIRMED);
    }

    public function isConfirmed(): bool
    {
        return $this->status === InvoiceStatus::CONFIRMED;
    }

    public function isCancelled(): bool
    {
        return $this->status === InvoiceStatus::CANCELLED;
    }

    public static function generateNumber(int $storeId): string
    {
        $year = now()->year;
        $last = static::withoutGlobalScopes()
            ->where('store_id', $storeId)
            ->whereYear('created_at', $year)
            ->lockForUpdate()
            ->count();

        return sprintf('PUR-%d-%04d', $year, $last + 1);
    }
}

