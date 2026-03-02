<?php
namespace App\Domain\Store\Interfaces;
use App\Models\Store;
interface IStoreRepository
{
    public function findById(string $id): ?Store;
    public function findAll(): array;
    public function save(Store $store): Store;
    public function update(string $id, Store $store): Store;
    public function toggleStatus(string $id, bool $isActive): void;
}
