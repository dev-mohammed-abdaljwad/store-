<?php
namespace App\Domain\Store\Repositories;

use App\Domain\Store\Interfaces\IPurchaseInvoiceRepository;
use App\Models\PurchaseInvoice;

class PurchaseInvoiceRepository implements IPurchaseInvoiceRepository
{
    public function findById(string $id, string $storeId): ?PurchaseInvoice
    {
        return PurchaseInvoice::where('id', $id)->where('store_id', $storeId)->first();
    }

    public function findByStore(string $storeId, array $filters = []): array
    {
        $query = PurchaseInvoice::where('store_id', $storeId);
        foreach ($filters as $key => $value) {
            $query->where($key, $value);
        }
        return $query->get()->all();
    }

    public function findBySupplier(string $supplierId, string $storeId): array
    {
        return PurchaseInvoice::where('supplier_id', $supplierId)->where('store_id', $storeId)->get()->all();
    }

    public function save(PurchaseInvoice $invoice): PurchaseInvoice
    {
        $invoice->save();
        return $invoice;
    }

    public function update(string $id, PurchaseInvoice $invoice): PurchaseInvoice
    {
        $existing = $this->findById($id, $invoice->store_id);
        if ($existing) {
            $existing->fill($invoice->toArray());
            $existing->save();
        }
        return $existing;
    }

    public function generateInvoiceNumber(string $storeId): string
    {
        $last = PurchaseInvoice::where('store_id', $storeId)->orderByDesc('id')->first();
        return $last ? (string)($last->id + 1) : '1';
    }
}
