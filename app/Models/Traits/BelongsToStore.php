<?php

namespace App\Models\Traits;

use App\Models\Scopes\StoreScope;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Trait BelongsToStore
 *
 * يُضاف لكل Model تابع لمتجر.
 * يطبّق Global Scope يقيّد كل Query بـ store_id تلقائياً.
 */
trait BelongsToStore
{
    protected static function bootBelongsToStore(): void
    {
        // ── تطبيق الـ Scope تلقائياً على كل Query ────────────────
        static::addGlobalScope(new StoreScope());

        // ── حقن store_id تلقائياً عند الإنشاء ───────────────────
        static::creating(function ($model) {
            if (auth()->check() && auth()->user()->isStoreOwner()) {
                $model->store_id = auth()->user()->getStoreId();
            }
        });
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Store::class);
    }
}
