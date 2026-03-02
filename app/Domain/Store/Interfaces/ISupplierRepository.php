<?php
namespace App\Domain\Store\Interfaces;
use App\Models\Supplier;
interface ISupplierRepository
{
    public function findById(string $id, string $storeId): ?Supplier;

    /** @return Supplier[] */
    public function findByStore(string $storeId): array;

    public function save(Supplier $supplier): Supplier;
    public function update(string $id, Supplier $supplier): Supplier;
    public function delete(string $id, string $storeId): void;
    public function existsByPhone(string $phone, string $storeId, ?string $excludeId = null): bool;
}  
