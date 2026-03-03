<?php
namespace App\Services;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Support\Facades\DB;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;

class ProductService
{
    public function __construct(private CacheService $cacheService) {}

    public function create(array $data, int $storeId): Product
    {
        return DB::transaction(function () use ($data, $storeId) {
            $product = Product::create([
                'store_id'    => $storeId,
                'category_id' => $data['category_id'],
                'name'        => $data['name'],
            ]);

            Category::query()
                ->whereKey($product->category_id)
                ->increment('products_count');

            $this->cacheService->invalidateProductsDropdown($storeId);
            $this->cacheService->invalidateCategories($storeId);

            return $product;
        });
    }

    public function addVariant(int $productId, array $data, int $storeId): ProductVariant
    {
        Product::where('store_id', $storeId)->findOrFail($productId);

        return DB::transaction(function () use ($productId, $data, $storeId) {
            $variant = ProductVariant::create([
                'store_id' => $storeId,
                'product_id' => $productId,
                'name' => $data['name'],
                'sku' => $data['sku'] ?? null,
                'purchase_price' => $data['purchase_price'],
                'sale_price' => $data['sale_price'],
                'low_stock_threshold' => $data['low_stock_threshold'] ?? 0,
                'is_active' => $data['is_active'] ?? true,
            ]);

            $this->cacheService->invalidateProductsDropdown($storeId);

            return $variant;
        });
    }

    public function updateVariant(int $variantId, array $data, int $storeId): ProductVariant
    {
        $variant = ProductVariant::where('store_id', $storeId)->findOrFail($variantId);

        return DB::transaction(function () use ($variant, $data, $storeId) {
            $variant->update([
                'name' => $data['name'] ?? $variant->name,
                'sku' => $data['sku'] ?? $variant->sku,
                'purchase_price' => $data['purchase_price'] ?? $variant->purchase_price,
                'sale_price' => $data['sale_price'] ?? $variant->sale_price,
                'low_stock_threshold' => $data['low_stock_threshold'] ?? $variant->low_stock_threshold,
                'is_active' => $data['is_active'] ?? $variant->is_active,
            ]);

            $this->cacheService->invalidateProductsDropdown($storeId);

            return $variant->fresh();
        });
    }

    public function deleteVariant(int $variantId, int $storeId): void
    {
        $variant = ProductVariant::where('store_id', $storeId)->findOrFail($variantId);

        if ($variant->stockMovements()->exists()) {
            throw ValidationException::withMessages([
                'variant' => 'لا يمكن حذف هذا الحجم — له حركات مخزون مسجلة.',
            ]);
        }

        $variant->delete();
        $this->cacheService->invalidateProductsDropdown($storeId);
    }

    public function update(int $id, array $data): Product
    {
        return DB::transaction(function () use ($id, $data) {
            $product = Product::findOrFail($id);
            $storeId = (int) $product->store_id;
            $oldCategoryId = (int) $product->category_id;

            $product->update($data);

            $newCategoryId = (int) $product->category_id;

            if ($oldCategoryId !== $newCategoryId) {
                Category::query()->whereKey($oldCategoryId)->where('products_count', '>', 0)->decrement('products_count');
                Category::query()->whereKey($newCategoryId)->increment('products_count');
            }

            $this->cacheService->invalidateProductsDropdown($storeId);
            $this->cacheService->invalidateCategories($storeId);

            return $product->fresh();
        });
    }

    public function delete(int $id): void
    {
        DB::transaction(function () use ($id) {
            $product = Product::findOrFail($id);
            $storeId = (int) $product->store_id;

            Category::query()
                ->whereKey($product->category_id)
                ->where('products_count', '>', 0)
                ->decrement('products_count');

            $product->delete();

            $this->cacheService->invalidateProductsDropdown($storeId);
            $this->cacheService->invalidateCategories($storeId);
        });
    }

    public function listForDropdown(int $storeId): array
    {
        return $this->cacheService->getProductsDropdown($storeId);
    }

    public function deleteCategory(int $categoryId, int $storeId): void
    {
        $category = Category::findOrFail($categoryId);
        $count    = (int) $category->products_count;

        if ($count > 0) {
            throw ValidationException::withMessages([
                'category' => "لا يمكن حذف التصنيف — يحتوي على {$count} منتجات.",
            ]);
        }

        $category->delete();
    }

    /**
     * قائمة المنتجات مع المخزون الحالي وتنبيه الانخفاض.
     */
    public function listWithStock(int $storeId, int $perPage = 10, bool $withTotal = false): LengthAwarePaginator
    {
        $products = Product::with(['category:id,name', 'variants'])
            ->where('store_id', $storeId)
            ->orderBy('name');

        $paginated = ($withTotal ? $products->paginate($perPage) : $products->paginate($perPage))->withQueryString();

        $transformed = $paginated->getCollection()->map(function (Product $p) {
            return [
                'id' => $p->id,
                'name' => $p->name,
                'category' => $p->category?->name,
                'variants' => $p->variants->map(fn(ProductVariant $v) => [
                    'id' => $v->id,
                    'name' => $v->name,
                    'sku' => $v->sku,
                    'purchase_price' => $v->purchase_price,
                    'sale_price' => $v->sale_price,
                    'current_stock' => $v->current_stock,
                    'low_stock_threshold' => $v->low_stock_threshold,
                    'is_low_stock' => $v->isLowStock(),
                ])->values(),
            ];
        });

        return $paginated->setCollection($transformed);
    }
}