<?php

namespace App\Services;

use App\Models\Supplier;
use Illuminate\Validation\ValidationException;

class SupplierService
{
    private $supplierRepository;
    private $financialRepository;

    public function __construct(
        \App\Domain\Store\Interfaces\ISupplierRepository $supplierRepository,
        \App\Domain\Store\Interfaces\IFinancialRepository $financialRepository
    ) {
        $this->supplierRepository = $supplierRepository;
        $this->financialRepository = $financialRepository;
    }

    public function create(array $data, int $storeId): Supplier
    {
        $supplier = new Supplier([
            'store_id' => $storeId,
            'name'     => $data['name'],
            'phone'    => $data['phone']   ?? null,
            'address'  => $data['address'] ?? null,
            'notes'    => $data['notes']   ?? null,
        ]);
        return $this->supplierRepository->save($supplier);
    }

    public function update(int $id, array $data): Supplier
    {
        $supplier = $this->supplierRepository->findById($id, $data['store_id']);
        if (!$supplier) {
            throw ValidationException::withMessages([
                'supplier' => 'المورد غير موجود.',
            ]);
        }
        $supplier->fill([
            'name'    => $data['name']    ?? $supplier->name,
            'phone'   => $data['phone']   ?? $supplier->phone,
            'address' => $data['address'] ?? $supplier->address,
            'notes'   => $data['notes']   ?? $supplier->notes,
        ]);
        return $this->supplierRepository->update($id, $supplier);
    }

    public function delete(int $id, string $storeId): void
    {
        $supplier = $this->supplierRepository->findById($id, $storeId);
        if (!$supplier) {
            throw ValidationException::withMessages([
                'supplier' => 'المورد غير موجود.',
            ]);
        }
        // TODO: Check for confirmed purchase invoices using repository if needed
        $this->supplierRepository->delete($id, $storeId);
    }

    public function getStatement(
        int $supplierId,
        string $storeId,
        ?string $from = null,
        ?string $to = null,
        int $perPage = 10,
    ): array
    {
        $supplier = $this->supplierRepository->findById($supplierId, $storeId);

        if (!$supplier) {
            throw ValidationException::withMessages([
                'supplier' => 'المورد غير موجود.',
            ]);
        }

        $balance = $this->financialRepository->getBalance(
            (string) $supplierId,
            \App\Domain\Store\Enums\PartyType::SUPPLIER,
            $storeId
        );

        $statement = $this->financialRepository->getStatement(
            (string) $supplierId,
            \App\Domain\Store\Enums\PartyType::SUPPLIER,
            $storeId,
            $from,
            $to,
            $perPage,
        );

        return [
            'supplier'  => $supplier,
            'balance'   => round($balance, 2),
            'statement' => $statement,
        ];
    }
}
