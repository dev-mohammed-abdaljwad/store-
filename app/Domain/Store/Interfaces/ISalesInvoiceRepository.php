<?php
namespace App\Domain\Store\Interfaces;
use App\Models\SalesInvoice;
interface ISalesInvoiceRepository
{
    public function findById(string $id, string $storeId): ?SalesInvoice;

    /** @return SalesInvoice[] */
    public function findByStore(string $storeId, array $filters = []): array;

    /** @return SalesInvoice[] فواتير عميل معين */
    public function findByCustomer(string $customerId, string $storeId): array;

    public function save(SalesInvoice $invoice): SalesInvoice;
    public function update(string $id, SalesInvoice $invoice): SalesInvoice;

    /** توليد رقم فاتورة تلقائي */
    public function generateInvoiceNumber(string $storeId): string;
}
