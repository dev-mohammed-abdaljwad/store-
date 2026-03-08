<?php

namespace App\Domain\Store\DTOs;

final class CreateSalesInvoiceDTO
{
    /** @param InvoiceItemDTO[] $items */
    public function __construct(
        public readonly string $storeId,
        public readonly string $invoiceNumber,
        public readonly string $customerId,
        public readonly array  $items,
        public readonly float  $paidAmount,
        public readonly string $createdBy,
        public readonly ?string $notes = null,
    ) {}

    public static function fromArray(array $data, string $storeId, string $createdBy): self
    {
        $items = array_map(
            fn(array $i) => new InvoiceItemDTO(
                variantId: (int) $i['variant_id'],
                quantity: (float) $i['quantity'],
                unitPrice: (float) $i['unit_price'],
            ),
            $data['items'] ?? []
        );

        return new self(
            storeId: $storeId,
            invoiceNumber: $data['invoice_number'],
            customerId: $data['customer_id'],
            items: $items,
            paidAmount: (float) ($data['paid_amount'] ?? 0),
            createdBy: $createdBy,
            notes: $data['notes'] ?? null,
        );
    }
}
