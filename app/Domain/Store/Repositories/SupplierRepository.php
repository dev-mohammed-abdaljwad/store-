<?php
namespace App\Domain\Store\Repositories;

use App\Domain\Store\Interfaces\ISupplierRepository;
use App\Models\Supplier;

class SupplierRepository implements ISupplierRepository
{
    public function findById(string $id, string $storeId): ?Supplier
    {
        return Supplier::where('id', $id)->where('store_id', $storeId)->first();
    }

    public function findByStore(string $storeId): array
    {
        return Supplier::where('store_id', $storeId)->get()->all();
    }

    public function save(Supplier $supplier): Supplier
    {
        $supplier->save();
        return $supplier;
    }

    public function update(string $id, Supplier $supplier): Supplier
    {
        $existing = $this->findById($id, $supplier->store_id);
        if ($existing) {
            $existing->fill($supplier->toArray());
            $existing->save();
        }
        return $existing;
    }

    public function delete(string $id, string $storeId): void
    {
        Supplier::where('id', $id)->where('store_id', $storeId)->delete();
    }

    public function existsByPhone(string $phone, string $storeId, ?string $excludeId = null): bool
    {
        $query = Supplier::where('phone', $phone)->where('store_id', $storeId);
        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }
        return $query->exists();
    }
}
