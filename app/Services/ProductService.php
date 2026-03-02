<?php
namespace App\Services;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProductService
{
    public function create(array $data, int $storeId): Product
    {
        return DB::transaction(function () use ($data, $storeId) {
            $product = Product::create([
                'store_id'            => $storeId,
                'category_id'         => $data['category_id'],
                'name'                => $data['name'],
                'sku'                 => $data['sku'] ?? null,
                'unit'                => $data['unit'],
                'purchase_price'      => $data['purchase_price'] ?? 0,
                'sale_price'          => $data['sale_price'] ?? 0,
                'low_stock_threshold' => $data['low_stock_threshold'] ?? 0,
            ]);

            Category::query()
                ->whereKey($product->category_id)
                ->increment('products_count');

            return $product;
        });
    }

    public function update(int $id, array $data): Product
    {
        return DB::transaction(function () use ($id, $data) {
            $product = Product::findOrFail($id);
            $oldCategoryId = (int) $product->category_id;

            $product->update($data);

            $newCategoryId = (int) $product->category_id;

            if ($oldCategoryId !== $newCategoryId) {
                Category::query()->whereKey($oldCategoryId)->where('products_count', '>', 0)->decrement('products_count');
                Category::query()->whereKey($newCategoryId)->increment('products_count');
            }

            return $product->fresh();
        });
    }

    public function delete(int $id): void
    {
        DB::transaction(function () use ($id) {
            $product = Product::findOrFail($id);

            Category::query()
                ->whereKey($product->category_id)
                ->where('products_count', '>', 0)
                ->decrement('products_count');

            $product->delete();
        });
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
    public function listWithStock(int $storeId): array
    {
        $products = Product::with('category')
                           ->where('store_id', $storeId)
                           ->get();

        return $products->map(function (Product $p) {
            return [
                'id'                  => $p->id,
                'name'                => $p->name,
                'sku'                 => $p->sku,
                'category'            => $p->category->name,
                'unit'                => $p->unit,
                'sale_price'          => $p->sale_price,
                'current_stock'       => $p->current_stock,
                'low_stock_threshold' => $p->low_stock_threshold,
                'is_low_stock'        => $p->isLowStock(),
            ];
        })->toArray();
    }
}