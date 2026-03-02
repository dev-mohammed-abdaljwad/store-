<?php
namespace App\Domain\Store\Repositories;

use App\Domain\Store\Interfaces\IProductRepository;
use App\Models\Product;

class ProductRepository implements IProductRepository
{
    public function findById(string $id, string $storeId): ?Product
    {
        return Product::where('id', $id)->where('store_id', $storeId)->first();
    }

    public function findByStore(string $storeId): array
    {
        return Product::where('store_id', $storeId)->get()->all();
    }

    public function findLowStock(string $storeId): array
    {
        return Product::where('store_id', $storeId)
            ->whereColumn('low_stock_threshold', '>', 'stock')
            ->get()->all();
    }

    public function save(Product $product): Product
    {
        $product->save();
        return $product;
    }

    public function update(string $id, Product $product): Product
    {
        $existing = $this->findById($id, $product->store_id);
        if ($existing) {
            $existing->fill($product->toArray());
            $existing->save();
        }
        return $existing;
    }

    public function delete(string $id, string $storeId): void
    {
        Product::where('id', $id)->where('store_id', $storeId)->delete();
    }

    public function existsBySku(string $sku, string $storeId, ?string $excludeId = null): bool
    {
        $query = Product::where('sku', $sku)->where('store_id', $storeId);
        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }
        return $query->exists();
    }
}
