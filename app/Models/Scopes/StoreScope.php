<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * StoreScope — Global Scope للـ Multi-Tenancy
 *
 * يُطبَّق تلقائياً على كل Model يستخدم BelongsToStore Trait.
 * يضمن إن كل Query مقيّدة بـ store_id المستخدم الحالي.
 */
class StoreScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        // Super Admin لا يُقيَّد بمتجر
        if (auth()->check() && auth()->user()->isSuperAdmin()) {
            return;
        }

        // Store Owner يرى بيانات متجره فقط
        if (auth()->check() && auth()->user()->isStoreOwner()) {
            $builder->where($model->getTable() . '.store_id', auth()->user()->getStoreId());
            return;
        }

        // لا يوجد مستخدم مسجل — لا نتائج
        $builder->whereRaw('1 = 0');
    }
}