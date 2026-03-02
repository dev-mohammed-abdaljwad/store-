<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseInvoiceItem extends Model
{
    protected $fillable = [
        'invoice_id',
        'product_id',
        'product_name',
        'ordered_quantity',
        'received_quantity',
        'unit_price',
        'total_price',
    ];

    protected $casts = [
        'ordered_quantity'  => 'float',
        'received_quantity' => 'float',
        'unit_price'        => 'float',
        'total_price'       => 'float',
    ];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(PurchaseInvoice::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}