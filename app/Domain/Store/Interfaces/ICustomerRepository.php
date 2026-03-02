<?php
namespace App\Domain\Store\Interfaces;
use App\Models\Customer;
interface ICustomerRepository
{
    public function findById(string $id, string $storeId): ?Customer;

    /** @return Customer[] */
    public function findByStore(string $storeId): array;

    public function save(Customer $customer): Customer;
    public function update(string $id, Customer $customer): Customer;
    public function delete(string $id, string $storeId): void;
    public function existsByPhone(string $phone, string $storeId, ?string $excludeId = null): bool;
}
