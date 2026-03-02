<?php
namespace App\Domain\Store\Interfaces;
use App\Models\Product;
interface IProductRepository
{
    public function findById(string $id, string $storeId): ?Product;

    /** @return Product[] */
    public function findByStore(string $storeId): array;

    /** @return Product[] المنتجات اللي وصلت لحد التنبيه */
    public function findLowStock(string $storeId): array;

    public function save(Product $product): Product;
    public function update(string $id, Product $product): Product;
    public function delete(string $id, string $storeId): void;
    public function existsBySku(string $sku, string $storeId, ?string $excludeId = null): bool;
}
