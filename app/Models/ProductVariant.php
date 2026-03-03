<?php

namespace App\Models;

use App\Models\Traits\BelongsToStore;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductVariant extends Model
{
    use BelongsToStore, SoftDeletes;

    protected $fillable = [
        'store_id',
        'product_id',
        'name',
        'sku',
        'purchase_price',
        'sale_price',
        'low_stock_threshold',
        'is_active',
    ];

    protected $casts = [
        'purchase_price' => 'float',
        'sale_price' => 'float',
        'low_stock_threshold' => 'integer',
        'is_active' => 'boolean',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class, 'variant_id');
    }

    public function salesInvoiceItems(): HasMany
    {
        return $this->hasMany(SalesInvoiceItem::class, 'variant_id');
    }

    public function purchaseInvoiceItems(): HasMany
    {
        return $this->hasMany(PurchaseInvoiceItem::class, 'variant_id');
    }

    public function getCurrentStockAttribute(): float
    {
        $in = $this->stockMovements()->where('type', 'in')->sum('quantity');
        $out = $this->stockMovements()->where('type', 'out')->sum('quantity');

        return round($in - $out, 3);
    }

    public function isLowStock(): bool
    {
        return $this->current_stock <= $this->low_stock_threshold;
    }

    public function canSell(float $quantity): bool
    {
        return $this->current_stock >= $quantity;
    }
}
