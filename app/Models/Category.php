<?php
namespace App\Models;

use App\Models\Traits\BelongsToStore;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Category extends Model
{
    use BelongsToStore, SoftDeletes;

    protected $fillable = [
        'store_id',
        'name',
        'products_count',
    ];

    protected $casts = [
        'products_count' => 'integer',
    ];

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /** هل يمكن حذف هذا التصنيف؟ */
    public function canBeDeleted(): bool
    {
        return $this->products()->count() === 0;
    }

    public function activeProductsCount(): int
    {
        return $this->products()->where('is_active', true)->count();
    }
}
