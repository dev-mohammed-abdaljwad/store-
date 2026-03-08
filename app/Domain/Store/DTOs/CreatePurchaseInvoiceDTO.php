<?php

namespace App\Domain\Store\DTOs;

class CreatePurchaseInvoiceDTO
{
    /** @param PurchaseItemDTO[] $items */
    public function __construct(
        public readonly string $storeId,
        public readonly string $invoiceNumber,
        public readonly string $supplierId,
        public readonly array  $items,
        public readonly float  $paidAmount,
        public readonly string $createdBy,
        public readonly ?string $notes = null,
    ) {}

    public static function fromArray(array $data, string $storeId, string $createdBy): self
    {
        $items = array_map(
            fn(array $i) => new PurchaseItemDTO(
                variantId: (int) $i['variant_id'],
                orderedQuantity: (float) ($i['ordered_quantity'] ?? $i['quantity'] ?? 0),
                receivedQuantity: (float) ($i['received_quantity'] ?? $i['quantity'] ?? 0),
                unitPrice: (float) $i['unit_price'],
            ),
            $data['items'] ?? []
        );

        return new self(
            storeId: $storeId,
            invoiceNumber: $data['invoice_number'],
            supplierId: $data['supplier_id'],
            items: $items,
            paidAmount: (float) ($data['paid_amount'] ?? 0),
            createdBy: $createdBy,
            notes: $data['notes'] ?? null,
        );
    }
}
