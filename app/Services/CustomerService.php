<?php

namespace App\Services;

use App\Models\Customer;
use Illuminate\Validation\ValidationException;

class CustomerService
{
    private $customerRepository;
    private $financialRepository;

    public function __construct(
        \App\Domain\Store\Interfaces\ICustomerRepository $customerRepository,
        \App\Domain\Store\Interfaces\IFinancialRepository $financialRepository
    ) {
        $this->customerRepository = $customerRepository;
        $this->financialRepository = $financialRepository;
    }

    public function create(array $data, int $storeId): Customer
    {
        $customer = new Customer([
            'store_id' => $storeId,
            'name'     => $data['name'],
            'phone'    => $data['phone'] ?? null,
            'address'  => $data['address'] ?? null,
            'notes'    => $data['notes'] ?? null,
        ]);
        return $this->customerRepository->save($customer);
    }

    public function update(int $id, array $data, int $storeId): Customer
    {
        $customer = $this->customerRepository->findById($id, $storeId);
        if (!$customer) {
            throw ValidationException::withMessages([
                'customer' => 'العميل غير موجود.',
            ]);
        }
        $customer->fill([
            'name'    => $data['name']    ?? $customer->name,
            'phone'   => $data['phone']   ?? $customer->phone,
            'address' => $data['address'] ?? $customer->address,
            'notes'   => $data['notes']   ?? $customer->notes,
        ]);
        return $this->customerRepository->update($id, $customer);
    }

    public function delete(int $id, int $storeId): void
    {
        $customer = $this->customerRepository->findById($id, $storeId);
        if (!$customer) {
            throw ValidationException::withMessages([
                'customer' => 'العميل غير موجود.',
            ]);
        }
        // TODO: Check for confirmed sales invoices using repository if needed
        $this->customerRepository->delete($id, $storeId);
    }

    /**
     * كشف حساب العميل.
     */
    public function getStatement(
        int $customerId,
        int $storeId,
        ?string $from = null,
        ?string $to = null,
        int $perPage = 10,
    ): array
    {
        $customer = $this->customerRepository->findById($customerId, $storeId);

        if (!$customer) {
            throw ValidationException::withMessages([
                'customer' => 'العميل غير موجود.',
            ]);
        }

        $balance = $this->financialRepository->getBalance(
            (string) $customerId,
            \App\Domain\Store\Enums\PartyType::CUSTOMER,
            (string) $storeId
        );

        $statement = $this->financialRepository->getStatement(
            (string) $customerId,
            \App\Domain\Store\Enums\PartyType::CUSTOMER,
            (string) $storeId,
            $from,
            $to,
            $perPage,
        );

        return [
            'customer'  => $customer,
            'balance'   => round($balance, 2),
            'statement' => $statement,
        ];
    }
}
