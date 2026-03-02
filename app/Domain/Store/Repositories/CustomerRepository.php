<?php
namespace App\Domain\Store\Repositories;

use App\Domain\Store\Interfaces\ICustomerRepository;
use App\Models\Customer;

class CustomerRepository implements ICustomerRepository
{
    public function findById(string $id, string $storeId): ?Customer
    {
        return Customer::where('id', $id)->where('store_id', $storeId)->first();
    }

    public function findByStore(string $storeId): array
    {
        return Customer::where('store_id', $storeId)->get()->all();
    }

    public function save(Customer $customer): Customer
    {
        $customer->save();
        return $customer;
    }

    public function update(string $id, Customer $customer): Customer
    {
        $existing = $this->findById($id, $customer->store_id);
        if ($existing) {
            $existing->fill($customer->toArray());
            $existing->save();
        }
        return $existing;
    }

    public function delete(string $id, string $storeId): void
    {
        Customer::where('id', $id)->where('store_id', $storeId)->delete();
    }

    public function existsByPhone(string $phone, string $storeId, ?string $excludeId = null): bool
    {
        $query = Customer::where('phone', $phone)->where('store_id', $storeId);
        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }
        return $query->exists();
    }
}
