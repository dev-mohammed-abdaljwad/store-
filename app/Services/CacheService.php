<?php

namespace App\Services;

use App\Models\Category;
use App\Models\FinancialTransaction;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StockMovement;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class CacheService
{
    private const COMPUTED_TTL_SECONDS = 3600;
    private const STATIC_TTL_SECONDS = 86400;

    public function getStock(int $storeId, int $productId): float
    {
        $key = "stock:{$storeId}:product:{$productId}";

        return (float) Cache::remember($key, self::COMPUTED_TTL_SECONDS, function () use ($storeId, $productId) {
            $totals = StockMovement::withoutGlobalScopes()
                ->selectRaw("COALESCE(SUM(CASE WHEN type = 'in' THEN quantity ELSE 0 END), 0) as stock_in")
                ->selectRaw("COALESCE(SUM(CASE WHEN type = 'out' THEN quantity ELSE 0 END), 0) as stock_out")
                ->where('store_id', $storeId)
                ->where('product_id', $productId)
                ->first();

            return round((float) ($totals->stock_in ?? 0) - (float) ($totals->stock_out ?? 0), 3);
        });
    }

    public function invalidateStock(int $storeId, array $productIds): void
    {
        foreach (array_unique(array_map('intval', $productIds)) as $productId) {
            if ($productId > 0) {
                Cache::forget("stock:{$storeId}:product:{$productId}");
            }
        }
    }

    public function getCashBalance(int $storeId): float
    {
        $key = "cash:balance:{$storeId}";

        return (float) Cache::remember($key, self::COMPUTED_TTL_SECONDS, function () use ($storeId) {
            $totals = DB::table('cash_transactions')
                ->selectRaw("COALESCE(SUM(CASE WHEN type = 'opening_balance' THEN amount ELSE 0 END), 0) as opening_total")
                ->selectRaw("COALESCE(SUM(CASE WHEN type = 'in' THEN amount ELSE 0 END), 0) as in_total")
                ->selectRaw("COALESCE(SUM(CASE WHEN type = 'out' THEN amount ELSE 0 END), 0) as out_total")
                ->where('store_id', $storeId)
                ->first();

            return round(
                (float) ($totals->opening_total ?? 0)
                + (float) ($totals->in_total ?? 0)
                - (float) ($totals->out_total ?? 0),
                2
            );
        });
    }

    public function invalidateCashBalance(int $storeId): void
    {
        Cache::forget("cash:balance:{$storeId}");
    }

    public function getCustomerBalance(int $customerId): float
    {
        $key = "balance:customer:{$customerId}";

        return (float) Cache::remember($key, self::COMPUTED_TTL_SECONDS, function () use ($customerId) {
            $balance = FinancialTransaction::withoutGlobalScopes()
                ->where('party_type', 'customer')
                ->where('party_id', $customerId)
                ->selectRaw("COALESCE(SUM(CASE WHEN type = 'debit' THEN amount ELSE -amount END), 0) as balance")
                ->value('balance');

            return round((float) $balance, 2);
        });
    }

    public function getSupplierBalance(int $supplierId): float
    {
        $key = "balance:supplier:{$supplierId}";

        return (float) Cache::remember($key, self::COMPUTED_TTL_SECONDS, function () use ($supplierId) {
            $balance = FinancialTransaction::withoutGlobalScopes()
                ->where('party_type', 'supplier')
                ->where('party_id', $supplierId)
                ->selectRaw("COALESCE(SUM(CASE WHEN type = 'debit' THEN amount ELSE -amount END), 0) as balance")
                ->value('balance');

            return round((float) $balance, 2);
        });
    }

    public function invalidateCustomerBalance(int $customerId): void
    {
        Cache::forget("balance:customer:{$customerId}");
    }

    public function invalidateSupplierBalance(int $supplierId): void
    {
        Cache::forget("balance:supplier:{$supplierId}");
    }

    public function getCategories(int $storeId): array
    {
        $key = "store:{$storeId}:categories";

        return Cache::remember($key, self::STATIC_TTL_SECONDS, function () use ($storeId) {
            $productCounts = Product::withoutGlobalScopes()
                ->selectRaw('category_id, COUNT(*) as products_count')
                ->where('store_id', $storeId)
                ->whereNull('deleted_at')
                ->groupBy('category_id');

            return Category::withoutGlobalScopes()
                ->leftJoinSub($productCounts, 'pc', function ($join) {
                    $join->on('pc.category_id', '=', 'categories.id');
                })
                ->where('store_id', $storeId)
                ->whereNull('deleted_at')
                ->orderBy('name')
                ->get([
                    'categories.id',
                    'categories.store_id',
                    'categories.name',
                    DB::raw('COALESCE(pc.products_count, 0) as products_count'),
                    'categories.created_at',
                    'categories.updated_at',
                ])
                ->map(fn($category) => [
                    'id' => (int) $category->id,
                    'store_id' => (int) $category->store_id,
                    'name' => $category->name,
                    'products_count' => (int) $category->products_count,
                    'created_at' => optional($category->created_at)->toJSON(),
                    'updated_at' => optional($category->updated_at)->toJSON(),
                ])
                ->toArray();
        });
    }

    public function getProductsDropdown(int $storeId): array
    {
        $key = "store:{$storeId}:products:dropdown";

        return Cache::remember($key, self::STATIC_TTL_SECONDS, function () use ($storeId) {
            return ProductVariant::withoutGlobalScopes()
                ->where('store_id', $storeId)
                ->where('is_active', true)
                ->whereNull('deleted_at')
                ->with(['product:id,name,category_id', 'product.category:id,name'])
                ->orderBy('product_id')
                ->orderBy('name')
                ->get(['id', 'store_id', 'product_id', 'name', 'sale_price'])
                ->map(fn(ProductVariant $variant) => [
                    'id' => $variant->id,
                    'product_id' => $variant->product_id,
                    'name' => ($variant->product?->name ?? '') . ' — ' . $variant->name,
                    'category_id' => $variant->product?->category_id,
                    'category' => $variant->product?->category?->name,
                    'sale_price' => $variant->sale_price,
                    'current_stock' => $variant->current_stock,
                ])
                ->toArray();
        });
    }

    public function getProductsCount(int $storeId): int
    {
        $key = "store:{$storeId}:products:count";

        return (int) Cache::remember($key, self::STATIC_TTL_SECONDS, function () use ($storeId) {
            return Product::withoutGlobalScopes()
                ->where('store_id', $storeId)
                ->whereNull('deleted_at')
                ->count();
        });
    }

    public function invalidateCategories(int $storeId): void
    {
        Cache::forget("store:{$storeId}:categories");
    }

    public function invalidateProductsDropdown(int $storeId): void
    {
        Cache::forget("store:{$storeId}:products:dropdown");
        Cache::forget("store:{$storeId}:products:count");
    }
}
