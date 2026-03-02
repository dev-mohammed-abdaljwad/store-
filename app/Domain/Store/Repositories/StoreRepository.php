<?php
namespace App\Domain\Store\Repositories;

use App\Domain\Store\Interfaces\IStoreRepository;
use App\Models\Store;

class StoreRepository implements IStoreRepository
{
    public function findById(string $id): ?Store
    {
        return Store::find($id);
    }

    public function findAll(): array
    {
        return Store::all()->all();
    }

    public function save(Store $store): Store
    {
        $store->save();
        return $store;
    }

    public function update(string $id, Store $store): Store
    {
        $existing = $this->findById($id);
        if ($existing) {
            $existing->fill($store->toArray());
            $existing->save();
        }
        return $existing;
    }

    public function toggleStatus(string $id, bool $isActive): void
    {
        $store = $this->findById($id);
        if ($store) {
            $store->is_active = $isActive;
            $store->save();
        }
    }
}
