<?php
namespace App\Models;

use App\Domain\Store\Enums\PartyType;
use App\Models\Traits\BelongsToStore;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    use BelongsToStore;

    protected $fillable = [
        'store_id',
        'party_type',
        'party_id',
        'amount',
        'payment_number',
        'payment_date',
        'description',
        'receipt_number',
        'created_by',
    ];

    protected $casts = [
        'amount' => 'float',
        'payment_date' => 'date',
        'party_type' => PartyType::class,
    ];
}
