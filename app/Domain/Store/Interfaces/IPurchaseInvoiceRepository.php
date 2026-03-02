<?php
namespace App\Domain\Store\Interfaces;
use App\Models\PurchaseInvoice;
interface IPurchaseInvoiceRepository
{
    public function findById(string $id, string $storeId): ?PurchaseInvoice;

    /** @return PurchaseInvoice[] */
    public function findByStore(string $storeId, array $filters = []): array;

    /** @return PurchaseInvoice[] فواتير مورد معين */
    public function findBySupplier(string $supplierId, string $storeId): array;

    public function save(PurchaseInvoice $invoice): PurchaseInvoice;
    public function update(string $id, PurchaseInvoice $invoice): PurchaseInvoice;
    public function generateInvoiceNumber(string $storeId): string;
}
